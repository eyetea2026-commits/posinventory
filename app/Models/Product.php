<?php

namespace App\Models;

use App\Casts\Quantity;
use App\Services\ProductImage;
use App\Services\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        // in UnitOfMeasure (the base unit: piece, meter...); PurchaseUnit is
        // a bigger unit holding QuantityPerPurchaseUnit of it (1 roll =
        // 5,000 meters); PurchaseUnitPrice, when set, also sells it by the
        // purchase unit. Price and CostPrice are per base unit.
        'UnitOfMeasure',
        'PurchaseUnit',
        'QuantityPerPurchaseUnit',
        'PurchaseUnitPrice',
        'AllowsFractionalQuantity',
        'SKU',
        'Barcode',
        'Price',
        'CostPrice',
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
        'AllowsFractionalQuantity' => 'boolean',
    ];

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

    public static function computeSellingPrice(float $costPrice): float
    {
        return round($costPrice / (1 - self::PROFIT_MARGIN), 2);
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
        return filled($this->PurchaseUnit)
            && $this->PurchaseUnit !== $this->baseUnit()
            && (float) $this->QuantityPerPurchaseUnit > 0;
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
     * How many base units one purchase unit holds (1 when it has none).
     */
    public function quantityPerOrderUnit(): int|float
    {
        return $this->hasPurchaseUnit() ? $this->QuantityPerPurchaseUnit : 1;
    }

    public function sellsByPurchaseUnit(): bool
    {
        return $this->hasPurchaseUnit() && $this->PurchaseUnitPrice !== null;
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
                // Serial-numbered pieces are always sold whole.
                'fractional' => $this->AllowsFractionalQuantity && ! $tracksSerialNumbers,
            ],
        ];

        if ($this->sellsByPurchaseUnit() && ! $tracksSerialNumbers) {
            $units[$this->PurchaseUnit] = [
                'unit' => $this->PurchaseUnit,
                'plural' => UnitOfMeasure::plural($this->PurchaseUnit),
                'quantity_per_unit' => $this->QuantityPerPurchaseUnit,
                'price' => (float) $this->PurchaseUnitPrice,
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
    // buy this from": the preferred supplier if one is designated, else the
    // sole known supplier if there's exactly one, else null (caller must
    // ask the admin to pick). Consumed by the auto-reorder flow and the
    // intelligent Damage-record form.
    public function resolveReorderSupplier(): ?ProductSupplier
    {
        $suppliers = $this->relationLoaded('suppliers') ? $this->suppliers : $this->suppliers()->get();

        $preferred = $suppliers->firstWhere('IsPreferred', 1);
        if ($preferred) {
            return $preferred;
        }

        return $suppliers->count() === 1 ? $suppliers->first() : null;
    }
}
