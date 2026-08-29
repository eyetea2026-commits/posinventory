<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'PurchaseOrderItem';
    protected $primaryKey = 'PurchaseOrderItemID';

    protected $fillable = [
        'Quantity',
        'ReceivedQuantity',
        'CostPriceAtOrder',
        'PurchaseOrderID',
        'ProductID',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'PurchaseOrderID', 'PurchaseOrderID');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->Quantity - $this->ReceivedQuantity);
    }

    // The financial value of this PO line — what was ORDERED at the price
    // agreed for this order, not what has arrived so far. Using
    // ReceivedQuantity here made every line show ₱0.00 until receiving
    // started, which isn't what a Line Total is for; Received/Remaining
    // already have their own columns for tracking fulfillment progress.
    public function getLineTotalAttribute(): float
    {
        return $this->Quantity * $this->CostPriceAtOrder;
    }
}
