<?php

namespace App\Models;

use App\Casts\Quantity;
use Illuminate\Database\Eloquent\Model;

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
        'BaseQuantity',
        'DamagedQuantity',
        'UnitCost',
        'SellingPrice',
    ];

    protected $casts = [
        'BaseQuantity' => Quantity::class,
        'DamagedQuantity' => 'integer',
        'UnitCost' => 'decimal:2',
        'SellingPrice' => 'decimal:2',
    ];

    public function delivery()
    {
        return $this->belongsTo(StockReceivingDelivery::class, 'StockReceivingDeliveryID', 'StockReceivingDeliveryID');
    }

    public function marginPercent(): ?float
    {
        if ($this->UnitCost === null || (float) $this->SellingPrice <= 0) {
            return null;
        }

        return round(((float) $this->SellingPrice - (float) $this->UnitCost) / (float) $this->SellingPrice * 100, 1);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'PurchaseOrderItemID', 'PurchaseOrderItemID');
    }
}
