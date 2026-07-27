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

    public function getLineTotalAttribute(): float
    {
        return $this->ReceivedQuantity * $this->CostPriceAtOrder;
    }
}
