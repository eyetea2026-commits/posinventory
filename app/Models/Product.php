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
        // Units of measurement (App\Services\UnitOfMeasure): stock is kept
        // in UnitOfMeasure (the base unit: piece, meter...); it's bought by
        // PurchaseUnit (roll, box), whose size - QuantityPerPurchaseUnit -
        // is the last one received at Stock Receiving (1 roll = 5,000
        // meters). SellsByPurchaseUnit also sells it by the roll, at Price x
        // that size. Price and CostPrice are per base unit.
        'UnitOfMeasure',
        'PurchaseUnit',
        'QuantityPerPurchaseUnit',
        'SellsByPurchaseUnit',
        'AllowsFractionalQuantity',
        'SKU',
        'Barcode',
        // The price of the stock batch that sells next (FIFO); each batch is
        // priced from its own cost at ProfitMargin (StockBatches).
        'Price',
        'CostPrice',
        // Percent: Selling Price = Cost / (1 - ProfitMargin / 100).
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
        // false: "Not returnable / refundable" (consumables, wires, fiber
        // connectors) - the Cashier can't put it on a return request.
        'IsReturnable' => 'boolean',
        'QuantityPerPurchaseUnit' => Quantity::class,
        'SellsByPurchaseUnit' => 'boolean',
        'AllowsFractionalQuantity' => 'boolean',
        'ProfitMargin' => 'float',
    ];

    /**
     * A product saved with a Selling Price but no Profit Margin keeps that
     * price: the margin is taken from it.
     */
    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if ($product->ProfitMargin === null) {
                $product->ProfitMargin = self::marginFor((float) $product->Price, (float) $product->CostPrice);
            }
        });
    }

    /**
     * The margin (percent) a Selling Price gives over a Cost Price; the
     * store default when either is missing.
     */
    public static function marginFor(float $price, float $cost): float
    {
        return $price > 0 && $cost > 0 ? round(($price - $cost) / $price * 100, 4) : self::PROFIT_MARGIN * 100;
    }

    /**
     * Only products that can still be sold or ordered. Inactive products
     * stay in the database (and in history/reports) but are hidden from the
     * POS and from new Purchase Orders.
     */
    public function scopeActive($query)
    {
        return $query->where('IsActive', true);
    }

    // Store policy: every product's selling price defaults to a fixed 45%
    // profit margin derived from its cost -- (Price - Cost) / Price = 0.45,
    // so Price = Cost / (1 - 0.45). An admin can still enter a Price
    // directly on create/update (ProductController) to override this
    // default for one product; the formula only applies when none is given.
    const PROFIT_MARGIN = 0.45;

    // Cost and Selling Price: up to 5 whole-peso digits.
    const MAX_PRICE = 99999.99;

    /**
     * Selling Price = Cost / (1 - Profit Margin); the store default margin
     * when none is given.
     */
    public static function computeSellingPrice(float $costPrice, ?float $marginPercent = null): float
    {
        $margin = ($marginPercent ?? self::PROFIT_MARGIN * 100) / 100;

        return $margin < 1 ? round($costPrice / (1 - $margin), 2) : 0.0;
    }

    /**
     * What a piece bought at this cost sells for, at this product's margin;
     * null when the cost isn't known.
     */
    public function sellingPriceForCost(?float $cost): ?float
    {
        return $cost !== null && $cost > 0 ? self::computeSellingPrice($cost, $this->ProfitMargin) : null;
    }

    public function stockBatches()
    {
        return $this->hasMany(StockBatch::class, 'ProductID', 'ProductID');
    }

    /**
     * What the stock on hand sells for, oldest batch first (FIFO): each
     * batch at its own price, batches at the same price merged.
     *
     * @return list<array{quantity: int|float, price: float}>
     */
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

    /**
     * The Selling Price follows the batch that sells next; with nothing in
     * stock, the Cost Price at the margin. Kept as it is when neither is known.
     */
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

    /**
     * After the margin changed: the batches still in stock are priced again.
     */
    public function repriceStockBatches(): void
    {
        $this->stockBatches()->where('QuantityRemaining', '>', 0)->get()
            ->each(fn (StockBatch $batch) => $batch->update(['SellingPrice' => $this->sellingPriceForCost((float) $batch->UnitCost)]));
        $this->refreshSellingPrice();
    }

    // SKU is system-assigned, never typed in by staff — it's derived from
    // the product's own ProductID, which only exists after the row is
    // inserted, so this is always called right after Product::create().
    public static function generateSku(int $productId): string
    {
        return 'SKU-'.str_pad((string) $productId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Public URL of the product photo, or null when it has none (pages then
     * show a placeholder).
     */
    public function getImageUrlAttribute(): ?string
    {
        return ProductImage::url($this->ImagePath);
    }

    /**
     * The unit stock is counted in ("piece", "meter").
     */
    public function baseUnit(): string
    {
        return $this->UnitOfMeasure ?: UnitOfMeasure::DEFAULT;
    }

    public function hasPurchaseUnit(): bool
    {
        return filled($this->PurchaseUnit) && $this->PurchaseUnit !== $this->baseUnit();
    }

    /**
     * How many base units one purchase unit held in the last delivery (1
     * roll = 5,000 meters): entered at Stock Receiving, not on the product.
     * Null until the first delivery.
     */
    public function purchaseUnitSize(): int|float|null
    {
        return $this->hasPurchaseUnit() && (float) $this->QuantityPerPurchaseUnit > 1 ? $this->QuantityPerPurchaseUnit : null;
    }

    /**
     * The unit it's ordered from suppliers in: the purchase unit, or the
     * base unit when it has none.
     */
    public function orderUnit(): string
    {
        return $this->hasPurchaseUnit() ? $this->PurchaseUnit : $this->baseUnit();
    }

    /**
     * How many base units one ordered unit adds (the last roll size; 1 when
     * it has no purchase unit or none has been received yet).
     */
    public function quantityPerOrderUnit(): int|float
    {
        return $this->purchaseUnitSize() ?? 1;
    }

    /**
     * Sold by the roll/box too - once a delivery has told the roll size.
     */
    public function sellsByPurchaseUnit(): bool
    {
        return $this->SellsByPurchaseUnit && $this->purchaseUnitSize() !== null;
    }

    /**
     * The price of a whole roll/box: the price per meter/piece x how many
     * the last roll held (₱15 x 1,000 meters = ₱15,000). Null until a roll
     * has been received.
     */
    public function purchaseUnitPrice(): ?float
    {
        $size = $this->purchaseUnitSize();

        return $size === null ? null : round((float) $this->Price * $size, 2);
    }

    /**
     * The units the POS sells it in, each with its price and how many base
     * units one of it takes from stock.
     *
     * @return array<string, array{unit: string, plural: string, quantity_per_unit: int|float, price: float, fractional: bool}>
     */
    public function sellingUnits(): array
    {
        $tracksSerialNumbers = $this->tracksSerialNumbers();
        $units = [
            $this->baseUnit() => [
                'unit' => $this->baseUnit(),
                'plural' => UnitOfMeasure::plural($this->baseUnit()),
                'quantity_per_unit' => 1,
                'price' => (float) $this->Price,
                // Each piece sells at its own batch's price (FIFO); listed
                // where the batches were loaded (the POS), not on every page.
                'tiers' => $this->relationLoaded('stockBatches') ? $this->priceTiers() : null,
                // Serial-numbered pieces are always sold whole.
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

    /**
     * "4,999 meters", "1 piece".
     */
    public function formatQuantity(int|float|null $quantity): string
    {
        return UnitOfMeasure::format($quantity, $this->baseUnit());
    }

    /**
     * Stock as shown on product cards: "1 Roll (5,000 meters)" while it's
     * whole rolls, "4,999 meters remaining" once one is opened, or just
     * "12 pieces" for a product without a purchase unit.
     */
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

    /**
     * "₱10.00/meter".
     */
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

    /**
     * One row per piece (SN00001...), in stock or not.
     */
    public function serials()
    {
        return $this->hasMany(ProductSerial::class, 'ProductID', 'ProductID');
    }

    /**
     * Each piece gets its own serial number (its category says so); the
     * serial sticker is then that piece's barcode at the POS.
     */
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

    // The single decision-tree used everywhere a product needs "who do we
    // buy this from" (panel suggestion: by the item's current price): the
    // active supplier with the lowest current price (its own Cost Price,
    // else the product's). On a tie, the one that delivered it last, else
    // the preferred one; still tied, null (caller must ask the admin to
    // pick). Consumed by the auto-reorder flow, Create Purchase Order and
    // the Damage-record form.
    public function resolveReorderSupplier(): ?ProductSupplier
    {
        $suppliers = $this->relationLoaded('suppliers') ? $this->suppliers : $this->suppliers()->with('supplier')->get();

        // Inactive suppliers aren't ordered from (unless there's no other).
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

    /**
     * A supplier's current price for this product, per base unit: its own
     * Cost Price, else the product's.
     */
    public function supplierPrice(ProductSupplier $link): float
    {
        return round((float) ($link->CostPrice ?? $this->CostPrice ?? 0), 2);
    }

    /** @var array{0: int|null}|null The last-delivered SupplierID, once looked up. */
    private ?array $lastDeliveredSupplier = null;

    /**
     * The SupplierID of this product's latest delivery (Stock Receiving),
     * or null when it was never received from a supplier.
     */
    public function lastDeliveredSupplierId(): ?int
    {
        if ($this->lastDeliveredSupplier === null) {
            self::preloadLastDeliveredSuppliers([$this]);
        }

        return $this->lastDeliveredSupplier[0];
    }

    /**
     * Looks up the last-delivered supplier of many products in two queries
     * (lists that resolve a supplier for every product).
     *
     * @param  iterable<Product>  $products
     */
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

        // Received one line at a time (the older Stock Receiving form).
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
