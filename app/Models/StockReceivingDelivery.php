<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One delivery received against a purchase order (an order can arrive in
 * several deliveries): its receipt number, who received it, when, and the
 * quantities (items).
 */
class StockReceivingDelivery extends Model
{
    protected $table = 'StockReceivingDelivery';

    protected $primaryKey = 'StockReceivingDeliveryID';

    protected $fillable = [
        'StockReceivingBatchID',
        'PurchaseOrderID',
        'ReceiptNumber',
        'ReceivedBy',
        'ReceivedAt',
    ];

    protected function casts(): array
    {
        return [
            'ReceivedAt' => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(StockReceivingDeliveryItem::class, 'StockReceivingDeliveryID', 'StockReceivingDeliveryID');
    }

    public function receivedByUser()
    {
        return $this->belongsTo(User::class, 'ReceivedBy', 'id');
    }
}
