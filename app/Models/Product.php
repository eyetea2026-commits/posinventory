<?php

namespace App\Models;

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
        'SKU',
        'UnitOfMeasure',
        'Barcode',
        'Price',
        'CostPrice',
        'Description',
        'BrandID',
        'CategoryID',
    ];

    // Store policy: every product's selling price is derived from its cost
    // at a fixed 45% profit margin -- (Price - Cost) / Price = 0.45, so
    // Price = Cost / (1 - 0.45). Selling price is never entered directly.
    const PROFIT_MARGIN = 0.45;

    public static function computeSellingPrice(float $costPrice): float
    {
        return round($costPrice / (1 - self::PROFIT_MARGIN), 2);
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
