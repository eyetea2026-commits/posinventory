<?php

namespace App\Models;

use App\Casts\Quantity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockBatch extends Model
{
    public const SOURCE_PURCHASE_ORDER = 'Purchase Order';

    public const SOURCE_STOCK_RECEIVING = 'Stock Receiving';

    public const SOURCE_OPENING_STOCK = 'Opening Stock';

    public const SOURCE_SUPPLIER_REPLACEMENT = 'Supplier Replacement';

    public const SOURCE_RESTORED = 'Restored from Damage';

    public const SOURCE_CUSTOMER_RETURN = 'Customer Return';

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

    public function scopeFifo(Builder $query): void
    {
        $query->orderBy('ReceivedAt')->orderBy('StockBatchID');
    }

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

    public function marginPercent(): ?float
    {
        if ((float) $this->SellingPrice <= 0) {
            return null;
        }

        return round(((float) $this->SellingPrice - (float) $this->UnitCost) / (float) $this->SellingPrice * 100, 1);
    }

    public function remainingValue(): float
    {
        return round((float) $this->QuantityRemaining * (float) $this->UnitCost, 2);
    }
}
