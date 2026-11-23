<?php

namespace App\Models;

use App\Casts\Quantity;
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
        'Quantity' => Quantity::class,
        'AutoReorderTriggered' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    public static function resolveStatus(int|float $quantity, ?int $reorderThreshold): string
    {
        if ($quantity <= 0) {
            return 'Out of Stock';
        }

        return $quantity <= ($reorderThreshold ?? 50) ? 'Low Stock' : 'Available';
    }
}
