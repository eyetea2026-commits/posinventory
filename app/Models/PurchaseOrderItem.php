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
}
