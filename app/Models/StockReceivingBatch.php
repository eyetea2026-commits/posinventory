<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockReceivingBatch extends Model
{
    protected $table = 'StockReceivingBatch';
    protected $primaryKey = 'StockReceivingBatchID';

    protected $fillable = [
        'PurchaseOrderID',
        'Status',
        'ReceivedBy',
        'CompletedAt',
    ];

    protected $casts = [
        'CompletedAt' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'PurchaseOrderID', 'PurchaseOrderID');
    }

    public function receivedByUser()
    {
        return $this->belongsTo(User::class, 'ReceivedBy', 'id');
    }
}
