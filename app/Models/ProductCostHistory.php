<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCostHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'ProductCostHistory';
    protected $primaryKey = 'ProductCostHistoryID';

    protected $fillable = [
        'ProductID',
        'SupplierID',
        'OldCostPrice',
        'NewCostPrice',
        'ChangedBy',
        'ChangedAt',
        'Source',
    ];

    const SOURCE_PRODUCT_UPDATE = 'product_update';
    const SOURCE_SUPPLIER_PIVOT_UPDATE = 'supplier_pivot_update';
    const SOURCE_PO_RECEIVING = 'po_receiving';

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'SupplierID', 'SupplierID');
    }

    // Central write path for every cost change, regardless of which of the
    // three independent code paths (Product edit, ProductSupplier pivot
    // edit, PO creation) triggered it — only logs when the value actually
    // changed, so re-saving a form with an unchanged cost doesn't spam
    // history with no-op entries.
    public static function log(Product $product, ?Supplier $supplier, ?float $old, float $new, string $source): void
    {
        if ($old !== null && (float) $old === (float) $new) {
            return;
        }

        self::create([
            'ProductID' => $product->ProductID,
            'SupplierID' => $supplier?->SupplierID,
            'OldCostPrice' => $old,
            'NewCostPrice' => $new,
            'ChangedBy' => auth()->id(),
            'ChangedAt' => now(),
            'Source' => $source,
        ]);
    }
}
