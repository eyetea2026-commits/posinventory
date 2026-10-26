<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * How many of one ordered product arrived in one delivery.
 */
class StockReceivingDeliveryItem extends Model
{
    public $timestamps = false;

    protected $table = 'StockReceivingDeliveryItem';

    protected $primaryKey = 'StockReceivingDeliveryItemID';

    protected $fillable = [
        'StockReceivingDeliveryID',
        'PurchaseOrderItemID',
        'ProductID',
        'Quantity',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }
}
