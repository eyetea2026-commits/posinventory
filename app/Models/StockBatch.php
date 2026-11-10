<?php

namespace App\Models;

use App\Casts\Quantity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One lot of a product's stock: when it came in, from where, at what unit
 * cost and selling price, and how much of it is still on hand. Sales and
 * stock removals take from the oldest lot first (FIFO, App\Services\StockBatches).
 */
class StockBatch extends Model
{
    public const SOURCE_PURCHASE_ORDER = 'Purchase Order';

    // The older single-product Stock Receiving form.
    public const SOURCE_STOCK_RECEIVING = 'Stock Receiving';

    // Stock that was already on hand before batches were tracked.
    public const SOURCE_OPENING_STOCK = 'Opening Stock';

    public const SOURCE_SUPPLIER_REPLACEMENT = 'Supplier Replacement';

    // A damage record cancelled: the pieces were fine after all.
    public const SOURCE_RESTORED = 'Restored from Damage';

    // A refunded piece put back on the shelf beyond what its sale took.
    public const SOURCE_CUSTOMER_RETURN = 'Customer Return';

    // Any other increase, at the product's Cost Price.
    public const SOURCE_STOCK_ADDED = 'Stock Added';

    protected $table = 'StockBatch';

    protected $primaryKey = 'StockBatchID';

    protected $fillable = [
        'BatchNumber',
        'ProductID',
        'Source',
        'SupplierID',
        'PurchaseOrderID',
        'StockReceivingDeliveryItemID',
        'ReceivingID',
        'DamageID',
        'ReceiptNumber',
        'ReceivedAt',
        'QuantityReceived',
        'QuantityRemaining',
        'UnitCost',
        'SellingPrice',
    ];

    protected function casts(): array
    {
        return [
            'ReceivedAt' => 'datetime',
            'QuantityReceived' => Quantity::class,
            'QuantityRemaining' => Quantity::class,
            'UnitCost' => 'decimal:2',
            'SellingPrice' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (StockBatch $batch) {
            if (! $batch->BatchNumber) {
                $batch->BatchNumber = self::formatNumber($batch->StockBatchID);
                $batch->saveQuietly();
            }
        });
    }

    public static function formatNumber(int $id): string
    {
        return 'BATCH-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'SupplierID', 'SupplierID');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'PurchaseOrderID', 'PurchaseOrderID');
    }

    public function deliveryItem(): BelongsTo
    {
        return $this->belongsTo(StockReceivingDeliveryItem::class, 'StockReceivingDeliveryItemID', 'StockReceivingDeliveryItemID');
    }

    /**
     * The product's batches in the order FIFO sells them: oldest first.
     *
     * @param  Builder<StockBatch>  $query
     */
    public function scopeFifo(Builder $query): void
    {
        $query->orderBy('ReceivedAt')->orderBy('StockBatchID');
    }

    /**
     * What this batch's sold pieces brought in, less what they cost:
     * each sale line's price after its discount, per base unit, minus the
     * batch's unit cost, for the pieces not refunded back onto it.
     */
    public function realizedProfit(): float
    {
        return round($this->allocations
            ->filter(fn (StockBatchAllocation $allocation) => $allocation->salesItem !== null)
            ->sum(function (StockBatchAllocation $allocation) {
                $line = $allocation->salesItem;
                $perBaseUnit = (float) $line->BaseQuantity > 0
                    ? ((float) $line->Quantity * (float) $line->UnitPrice - (float) ($line->DiscountAmount ?? 0)) / (float) $line->BaseQuantity
                    : 0;

                return (float) $allocation->netQuantity() * ($perBaseUnit - (float) $allocation->UnitCost);
            }), 2);
    }

    /**
     * Pieces of this batch sold and not refunded back onto it: whatever
     * left the batch other than through a stock adjustment / removal, so
     * pieces sold before batches were tracked count too.
     */
    public function soldQuantity(): int|float
    {
        $removed = $this->allocations
            ->where('Reason', '!=', StockBatchAllocation::REASON_SALE)
            ->sum(fn (StockBatchAllocation $allocation) => (float) $allocation->netQuantity());

        return Quantity::normalize(max(0, (float) $this->QuantityReceived - (float) $this->QuantityRemaining - $removed));
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(StockBatchAllocation::class, 'StockBatchID', 'StockBatchID');
    }

    /**
     * The batch's margin: (Selling Price - Unit Cost) / Selling Price.
     */
    public function marginPercent(): ?float
    {
        if ((float) $this->SellingPrice <= 0) {
            return null;
        }

        return round(((float) $this->SellingPrice - (float) $this->UnitCost) / (float) $this->SellingPrice * 100, 1);
    }

    /**
     * What is left of it, at its own cost.
     */
    public function remainingValue(): float
    {
        return round((float) $this->QuantityRemaining * (float) $this->UnitCost, 2);
    }
}
