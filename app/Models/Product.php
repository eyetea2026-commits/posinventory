<?php

namespace App\Models;

use App\Casts\Quantity;
use App\Services\ProductImage;
use App\Services\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'Product';

    protected $primaryKey = 'ProductID';

    protected $fillable = [
        'ProductName',
        'Model',
        'UnitOfMeasure',
        'PurchaseUnit',
        'QuantityPerPurchaseUnit',
        'SellsByPurchaseUnit',
        'AllowsFractionalQuantity',
        'SKU',
        'Barcode',
        'Price',
        'CostPrice',
        'ProfitMargin',
        'Description',
        'ImagePath',
        'BrandID',
        'CategoryID',
        'IsActive',
        'IsReturnable',
    ];

    protected $casts = [
        'IsActive' => 'boolean',
        'IsReturnable' => 'boolean',
        'QuantityPerPurchaseUnit' => Quantity::class,
        'SellsByPurchaseUnit' => 'boolean',
        'AllowsFractionalQuantity' => 'boolean',
        'ProfitMargin' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if ($product->ProfitMargin === null) {
                $product->ProfitMargin = self::marginFor((float) $product->Price, (float) $product->CostPrice);
            }
        });
    }

    public static function marginFor(float $price, float $cost): float
    {
        return $price > 0 && $cost > 0 ? max(0.0, round(($price - $cost) / $price * 100, 4)) : self::PROFIT_MARGIN * 100;
    }

    public function scopeActive($query)
    {
        return $query->where('IsActive', true);
    }

    const PROFIT_MARGIN = 0.45;

    const MAX_PRICE = 99999.99;

    public static function computeSellingPrice(float $costPrice, ?float $marginPercent = null): float
    {
        $margin = ($marginPercent ?? self::PROFIT_MARGIN * 100) / 100;

        return $margin < 1 ? round($costPrice / (1 - $margin), 2) : 0.0;
    }

    public function sellingPriceForCost(?float $cost): ?float
    {
        return $cost !== null && $cost > 0 ? self::computeSellingPrice($cost, $this->ProfitMargin) : null;
    }

    public function stockBatches()
    {
        return $this->hasMany(StockBatch::class, 'ProductID', 'ProductID');
    }

    public function priceTiers(): array
    {
        $batches = $this->relationLoaded('stockBatches')
            ? $this->stockBatches
            : $this->stockBatches()->where('QuantityRemaining', '>', 0)->get();

        $tiers = [];
        foreach ($batches->filter(fn (StockBatch $batch) => (float) $batch->QuantityRemaining > 0)->sortBy([['ReceivedAt', 'asc'], ['StockBatchID', 'asc']]) as $batch) {
            $price = round((float) ($batch->SellingPrice ?? $this->Price), 2);
            $last = count($tiers) - 1;
            if ($last >= 0 && $tiers[$last]['price'] === $price) {
                $tiers[$last]['quantity'] = Quantity::normalize($tiers[$last]['quantity'] + (float) $batch->QuantityRemaining);
            } else {
                $tiers[] = ['quantity' => Quantity::normalize($batch->QuantityRemaining), 'price' => $price];
            }
        }

        return $tiers;
    }

    public function refreshSellingPrice(): void
    {
        $next = $this->stockBatches()
            ->where('QuantityRemaining', '>', 0)
            ->whereNotNull('SellingPrice')
            ->fifo()
            ->value('SellingPrice');
        $price = $next !== null ? (float) $next : $this->sellingPriceForCost((float) $this->CostPrice);

        if ($price !== null && round((float) $this->Price, 2) !== round($price, 2)) {
            self::whereKey($this->ProductID)->update(['Price' => $price]);
            $this->Price = $price;
        }
    }

    public function repriceStockBatches(): void
    {
        $this->stockBatches()->where('QuantityRemaining', '>', 0)->get()
            ->each(fn (StockBatch $batch) => $batch->update(['SellingPrice' => $this->sellingPriceForCost((float) $batch->UnitCost)]));
        $this->refreshSellingPrice();
    }

    public static function generateSku(int $productId): string
    {
        return 'SKU-'.str_pad((string) $productId, 6, '0', STR_PAD_LEFT);
    }

    public function getImageUrlAttribute(): ?string
    {
        return ProductImage::url($this->ImagePath);
    }

    public function baseUnit(): string
    {
        return $this->UnitOfMeasure ?: UnitOfMeasure::DEFAULT;
    }

    public function hasPurchaseUnit(): bool
    {
        return filled($this->PurchaseUnit) && $this->PurchaseUnit !== $this->baseUnit();
    }

    public function purchaseUnitSize(): int|float|null
    {
        return $this->hasPurchaseUnit() && (float) $this->QuantityPerPurchaseUnit > 1 ? $this->QuantityPerPurchaseUnit : null;
    }

    public function orderUnit(): string
    {
        return $this->hasPurchaseUnit() ? $this->PurchaseUnit : $this->baseUnit();
    }

    public function quantityPerOrderUnit(): int|float
    {
        return $this->purchaseUnitSize() ?? 1;
    }

    public function sellsByPurchaseUnit(): bool
    {
        return $this->SellsByPurchaseUnit && $this->purchaseUnitSize() !== null;
    }

    public function purchaseUnitPrice(): ?float
    {
        $size = $this->purchaseUnitSize();

        return $size === null ? null : round((float) $this->Price * $size, 2);
    }

    public function sellingUnits(): array
    {
        $tracksSerialNumbers = $this->tracksSerialNumbers();
        $units = [
            $this->baseUnit() => [
                'unit' => $this->baseUnit(),
                'plural' => UnitOfMeasure::plural($this->baseUnit()),
                'quantity_per_unit' => 1,
                'price' => (float) $this->Price,
                'tiers' => $this->relationLoaded('stockBatches') ? $this->priceTiers() : null,
                'fractional' => $this->AllowsFractionalQuantity && ! $tracksSerialNumbers,
            ],
        ];

        if ($this->sellsByPurchaseUnit() && ! $tracksSerialNumbers) {
            $units[$this->PurchaseUnit] = [
                'unit' => $this->PurchaseUnit,
                'plural' => UnitOfMeasure::plural($this->PurchaseUnit),
                'quantity_per_unit' => $this->QuantityPerPurchaseUnit,
                'price' => $this->purchaseUnitPrice(),
                'fractional' => false,
            ];
        }

        return $units;
    }

    public function formatQuantity(int|float|null $quantity): string
    {
        return UnitOfMeasure::format($quantity, $this->baseUnit());
    }

    public function stockLabel(int|float|null $quantity): string
    {
        $quantity = (float) $quantity;
        $perPurchaseUnit = (float) $this->quantityPerOrderUnit();

        if (! $this->hasPurchaseUnit() || $perPurchaseUnit <= 1 || $quantity <= 0) {
            return $this->formatQuantity($quantity);
        }

        $wholeUnits = $quantity / $perPurchaseUnit;
        if ($quantity >= $perPurchaseUnit && abs($wholeUnits - round($wholeUnits)) < 0.00001) {
            return UnitOfMeasure::format(round($wholeUnits), $this->PurchaseUnit, true).' ('.$this->formatQuantity($quantity).')';
        }

        return $this->formatQuantity($quantity).' remaining';
    }

    public function priceLabel(?string $unit = null): string
    {
        $sellingUnit = $this->sellingUnits()[$unit ?? $this->baseUnit()] ?? null;
        $price = $sellingUnit['price'] ?? (float) $this->Price;

        return '₱'.number_format($price, 2).'/'.($sellingUnit['unit'] ?? $this->baseUnit());
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'BrandID', 'BrandID');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'CategoryID', 'CategoryID');
    }

    public function inventory()
    {
        return $this->hasOne(Inventory::class, 'ProductID', 'ProductID');
    }

    public function serials()
    {
        return $this->hasMany(ProductSerial::class, 'ProductID', 'ProductID');
    }

    public function tracksSerialNumbers(): bool
    {
        return (bool) ($this->category ?? $this->category()->first())?->TracksSerialNumbers;
    }

    public function stockReceivings()
    {
        return $this->hasMany(StockReceiving::class, 'ProductID', 'ProductID');
    }

    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class, 'ProductID', 'ProductID');
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'ProductID', 'ProductID');
    }

    public function salesItems()
    {
        return $this->hasMany(SalesItem::class, 'ProductID', 'ProductID');
    }

    public function damagedProducts()
    {
        return $this->hasMany(DamagedProduct::class, 'ProductID', 'ProductID');
    }

    public function salesReturns()
    {
        return $this->hasMany(SalesReturn::class, 'ProductID', 'ProductID');
    }

    public function suppliers()
    {
        return $this->hasMany(ProductSupplier::class, 'ProductID', 'ProductID');
    }

    public function costHistory()
    {
        return $this->hasMany(ProductCostHistory::class, 'ProductID', 'ProductID');
    }

    public function resolveReorderSupplier(): ?ProductSupplier
    {
        $suppliers = $this->relationLoaded('suppliers') ? $this->suppliers : $this->suppliers()->with('supplier')->get();

        $active = $suppliers->filter(fn (ProductSupplier $link) => ($link->supplier?->Status ?? Supplier::STATUS_ACTIVE) === Supplier::STATUS_ACTIVE);
        $candidates = $active->isNotEmpty() ? $active : $suppliers;
        if ($candidates->isEmpty()) {
            return null;
        }

        $lowestPrice = $candidates->min(fn (ProductSupplier $link) => $this->supplierPrice($link));
        $cheapest = $candidates->filter(fn (ProductSupplier $link) => $this->supplierPrice($link) === $lowestPrice)->values();
        if ($cheapest->count() === 1) {
            return $cheapest->first();
        }

        $lastDeliveredId = $this->lastDeliveredSupplierId();
        $current = $lastDeliveredId === null ? null : $cheapest->firstWhere('SupplierID', $lastDeliveredId);

        return $current ?? $cheapest->firstWhere('IsPreferred', 1);
    }

    public function supplierPrice(ProductSupplier $link): float
    {
        return round((float) ($link->CostPrice ?? $this->CostPrice ?? 0), 2);
    }

    private ?array $lastDeliveredSupplier = null;

    public function lastDeliveredSupplierId(): ?int
    {
        if ($this->lastDeliveredSupplier === null) {
            self::preloadLastDeliveredSuppliers([$this]);
        }

        return $this->lastDeliveredSupplier[0];
    }

    public static function preloadLastDeliveredSuppliers(iterable $products): void
    {
        $products = collect($products);
        $productIds = $products->map(fn (Product $product) => (int) $product->ProductID)->unique()->values()->all();
        if ($productIds === []) {
            return;
        }

        $deliveries = DB::table('StockReceivingDeliveryItem')
            ->join('StockReceivingDelivery', 'StockReceivingDelivery.StockReceivingDeliveryID', '=', 'StockReceivingDeliveryItem.StockReceivingDeliveryID')
            ->join('PurchaseOrder', 'PurchaseOrder.PurchaseOrderID', '=', 'StockReceivingDelivery.PurchaseOrderID')
            ->whereIn('StockReceivingDeliveryItem.ProductID', $productIds)
            ->select('StockReceivingDeliveryItem.ProductID', 'PurchaseOrder.SupplierID', 'StockReceivingDelivery.ReceivedAt as received_at')
            ->get();

        $singleReceipts = DB::table('StockReceiving')
            ->whereIn('ProductID', $productIds)
            ->whereNotNull('SupplierID')
            ->select('ProductID', 'SupplierID', 'DateReceived as received_at')
            ->get();

        $latest = array_fill_keys($productIds, null);
        $latestAt = [];
        foreach ($deliveries->concat($singleReceipts) as $row) {
            $at = Carbon::parse($row->received_at);
            $productId = (int) $row->ProductID;
            if (! isset($latestAt[$productId]) || $at->greaterThanOrEqualTo($latestAt[$productId])) {
                $latestAt[$productId] = $at;
                $latest[$productId] = (int) $row->SupplierID;
            }
        }

        foreach ($products as $product) {
            $product->lastDeliveredSupplier = [$latest[(int) $product->ProductID] ?? null];
        }
    }
}
