<?php

namespace App\Models;

use App\Casts\Quantity;
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
        // In the order line's unit (2 rolls); BaseQuantity is what it added
        // to stock (2,000 meters - roll lengths are entered on receiving).
        'Quantity',
        'BaseQuantity',
    ];

    protected $casts = [
        'BaseQuantity' => Quantity::class,
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    /**
     * The order line this delivery was for: Quantity is in its unit.
     */
    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'PurchaseOrderItemID', 'PurchaseOrderItemID');
    }
}
