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
        // Arrived damaged (order line's unit): counted as delivered, never
        // added to stock; it went to the Damage module.
        'DamagedQuantity',
        // Per base unit, as they were when this batch arrived.
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

    /**
     * This batch's margin: (Selling Price - Unit Cost) / Selling Price.
     */
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

    /**
     * The order line this delivery was for: Quantity is in its unit.
     */
    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'PurchaseOrderItemID', 'PurchaseOrderItemID');
    }
}
