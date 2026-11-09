<?php

namespace App\Models;

use App\Casts\Quantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How much one sale line or stock removal took from one batch, at that
 * batch's unit cost (FIFO).
 */
class StockBatchAllocation extends Model
{
    public const REASON_SALE = 'Sale';

    public const REASON_STOCK_ADJUSTMENT = 'Stock Adjustment';

    // Any other decrease (e.g. a damage record that took stock).
    public const REASON_REMOVED = 'Removed from Stock';

    protected $table = 'StockBatchAllocation';

    protected $primaryKey = 'StockBatchAllocationID';

    protected $fillable = [
        'StockBatchID',
        'ProductID',
        'Reason',
        'SalesItemID',
        'StockAdjustmentID',
        'Quantity',
        'ReturnedQuantity',
        'UnitCost',
    ];

    protected function casts(): array
    {
        return [
            'Quantity' => Quantity::class,
            'ReturnedQuantity' => Quantity::class,
            'UnitCost' => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'StockBatchID', 'StockBatchID');
    }

    public function salesItem(): BelongsTo
    {
        return $this->belongsTo(SalesItem::class, 'SalesItemID', 'SalesItemID');
    }

    /**
     * Still out of the batch (not put back by a refund).
     */
    public function netQuantity(): int|float
    {
        return Quantity::normalize((float) $this->Quantity - (float) $this->ReturnedQuantity);
    }
}
