<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'Inventory';
    protected $primaryKey = 'InventoryID';

    protected $fillable = [
        'Quantity',
        'Status',
        'ReorderThreshold',
        'AutoReorderTriggered',
        'LastRestockPurchaseOrderId',
        'ProductID',
    ];

    protected $casts = [
        'AutoReorderTriggered' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    // The single source of truth for the stored Inventory.Status string
    // (Out of Stock / Low Stock / Available) -- the value InventoryObserver
    // reads to decide whether to fire LowStockAlert. Several call sites used
    // to reimplement this with a hardcoded "<=10" cutoff instead of the
    // product's own ReorderThreshold, which could leave a product sitting
    // well under its real threshold marked "Available" and silently
    // suppress the alert. Not to be confused with ProductController /
    // InventoryController's resolveStockStatus(), a separate 4-tier
    // (+ "Replenish") vocabulary computed live for display only and never
    // written to this column.
    public static function resolveStatus(int $quantity, ?int $reorderThreshold): string
    {
        if ($quantity <= 0) {
            return 'Out of Stock';
        }

        return $quantity <= ($reorderThreshold ?? 50) ? 'Low Stock' : 'Available';
    }
}
