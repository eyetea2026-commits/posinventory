<?php

namespace App\Services;

use App\Casts\Quantity;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockBatchAllocation;

/**
 * FIFO with batch tracking and batch-level costing.
 *
 * Runs after every stock save (InventoryObserver), like SerialNumbers, so
 * the batches always add up to the stock on hand:
 * - stock that went up becomes a new batch, with the cost / supplier /
 *   receipt the caller gave in receivingAs() (else the product's Cost Price);
 * - stock that went down is taken from the oldest batch first, each piece at
 *   its own batch's cost, recorded for the sale line / adjustment given in
 *   takingFor();
 * - a refund that restocks (returningFrom()) puts the pieces back on the
 *   batches its sale took them from.
 */
class StockBatches
{
    /** @var array<string, mixed>|null */
    private static ?array $incoming = null;

    /** @var array<string, mixed>|null */
    private static ?array $outgoing = null;

    private static ?int $returningSalesItemId = null;

    /**
     * Stock this callback adds becomes a batch with these attributes
     * (Source, UnitCost, SupplierID, PurchaseOrderID, ReceiptNumber...).
     *
     * @template T
     *
     * @param  array<string, mixed>  $attributes
     * @param  callable(): T  $callback
     * @return T
     */
    public static function receivingAs(array $attributes, callable $callback)
    {
        return self::with('incoming', $attributes, $callback);
    }

    /**
     * Stock this callback takes out is recorded with these attributes
     * (Reason, SalesItemID or StockAdjustmentID).
     *
     * @template T
     *
     * @param  array<string, mixed>  $attributes
     * @param  callable(): T  $callback
     * @return T
     */
    public static function takingFor(array $attributes, callable $callback)
    {
        return self::with('outgoing', $attributes, $callback);
    }

    /**
     * Stock this callback adds back goes to the batches this sale line
     * took it from (latest first).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function returningFrom(?int $salesItemId, callable $callback)
    {
        return self::with('returningSalesItemId', $salesItemId, $callback);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private static function with(string $property, mixed $value, callable $callback)
    {
        $previous = self::${$property};
        self::${$property} = $value;

        try {
            return $callback();
        } finally {
            self::${$property} = $previous;
        }
    }

    public static function syncWithStock(Inventory $inventory): void
    {
        $onHand = max(0, (float) $inventory->Quantity);
        $inBatches = (float) StockBatch::where('ProductID', $inventory->ProductID)->sum('QuantityRemaining');
        $difference = round($onHand - $inBatches, 2);

        if ($difference > 0) {
            self::add((int) $inventory->ProductID, $difference);
        } elseif ($difference < 0) {
            self::take((int) $inventory->ProductID, -$difference);
        }
    }

    /**
     * The FIFO cost of what a sale line took (less what a refund put back).
     */
    public static function costOfSalesItem(int $salesItemId): float
    {
        return round(StockBatchAllocation::where('SalesItemID', $salesItemId)->get()
            ->sum(fn (StockBatchAllocation $allocation) => (float) $allocation->Quantity * (float) $allocation->UnitCost), 2);
    }

    private static function add(int $productId, float $quantity): void
    {
        if (self::$returningSalesItemId) {
            $quantity = self::putBack(self::$returningSalesItemId, $quantity);
            if ($quantity <= 0) {
                return;
            }
        }

        $product = Product::find($productId);
        $attributes = self::$incoming ?? [];

        StockBatch::create(array_merge([
            'Source' => self::$returningSalesItemId ? StockBatch::SOURCE_CUSTOMER_RETURN : StockBatch::SOURCE_STOCK_ADDED,
            'ReceivedAt' => now(),
            'UnitCost' => round((float) ($product?->CostPrice ?? 0), 2),
            'SellingPrice' => $product?->Price,
        ], array_filter($attributes, fn ($value) => $value !== null), [
            'ProductID' => $productId,
            'QuantityReceived' => Quantity::normalize($quantity),
            'QuantityRemaining' => Quantity::normalize($quantity),
        ]));
    }

    /**
     * Back onto the sale line's own batches; returns what didn't fit.
     */
    private static function putBack(int $salesItemId, float $quantity): float
    {
        $allocations = StockBatchAllocation::where('SalesItemID', $salesItemId)
            ->orderByDesc('StockBatchAllocationID')
            ->lockForUpdate()
            ->get();

        foreach ($allocations as $allocation) {
            $open = round((float) $allocation->Quantity - (float) $allocation->ReturnedQuantity, 2);
            if ($quantity <= 0 || $open <= 0) {
                continue;
            }

            $back = min($open, $quantity);
            $allocation->update(['ReturnedQuantity' => (float) $allocation->ReturnedQuantity + $back]);
            $batch = StockBatch::lockForUpdate()->find($allocation->StockBatchID);
            $batch?->update(['QuantityRemaining' => (float) $batch->QuantityRemaining + $back]);
            $quantity = round($quantity - $back, 2);
        }

        return $quantity;
    }

    /**
     * Oldest batch first.
     */
    private static function take(int $productId, float $quantity): void
    {
        $batches = StockBatch::where('ProductID', $productId)
            ->where('QuantityRemaining', '>', 0)
            ->orderBy('ReceivedAt')
            ->orderBy('StockBatchID')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($quantity <= 0) {
                break;
            }

            $taken = min((float) $batch->QuantityRemaining, $quantity);
            $batch->update(['QuantityRemaining' => (float) $batch->QuantityRemaining - $taken]);

            StockBatchAllocation::create(array_merge(['Reason' => StockBatchAllocation::REASON_REMOVED], self::$outgoing ?? [], [
                'StockBatchID' => $batch->StockBatchID,
                'ProductID' => $productId,
                'Quantity' => Quantity::normalize($taken),
                'UnitCost' => $batch->UnitCost,
            ]));

            $quantity = round($quantity - $taken, 2);
        }
    }
}
