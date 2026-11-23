<?php

namespace App\Services;

use App\Casts\Quantity;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockBatchAllocation;

class StockBatches
{
    private static ?array $incoming = null;

    private static ?array $outgoing = null;

    private static ?array $returningSalesItemIds = null;

    public static function receivingAs(array $attributes, callable $callback)
    {
        return self::with('incoming', $attributes, $callback);
    }

    public static function takingFor(array $attributes, callable $callback)
    {
        return self::with('outgoing', $attributes, $callback);
    }

    public static function returningFrom(int|array|null $salesItemIds, callable $callback)
    {
        return self::with('returningSalesItemIds', $salesItemIds === null ? null : (array) $salesItemIds, $callback);
    }

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

        if ($difference != 0) {
            Product::find($inventory->ProductID)?->refreshSellingPrice();
        }
    }

    public static function priceSlices(Product $product, float $quantity): array
    {
        $batches = StockBatch::where('ProductID', $product->ProductID)
            ->where('QuantityRemaining', '>', 0)
            ->fifo()
            ->lockForUpdate()
            ->get();

        $slices = [];
        foreach ($batches as $batch) {
            if ($quantity <= 0) {
                break;
            }
            $taken = min((float) $batch->QuantityRemaining, $quantity);
            self::addSlice($slices, $taken, round((float) ($batch->SellingPrice ?? $product->Price), 2));
            $quantity = round($quantity - $taken, 2);
        }
        if ($quantity > 0) {
            self::addSlice($slices, $quantity, round((float) $product->Price, 2));
        }

        return $slices;
    }

    private static function addSlice(array &$slices, float $quantity, float $price): void
    {
        $last = count($slices) - 1;
        if ($last >= 0 && $slices[$last]['price'] === $price) {
            $slices[$last]['quantity'] = Quantity::normalize($slices[$last]['quantity'] + $quantity);

            return;
        }
        $slices[] = ['quantity' => Quantity::normalize($quantity), 'price' => $price];
    }

    public static function costOfSalesItem(int $salesItemId): float
    {
        return round(StockBatchAllocation::where('SalesItemID', $salesItemId)->get()
            ->sum(fn (StockBatchAllocation $allocation) => (float) $allocation->Quantity * (float) $allocation->UnitCost), 2);
    }

    private static function add(int $productId, float $quantity): void
    {
        if (self::$returningSalesItemIds) {
            $quantity = self::putBack(self::$returningSalesItemIds, $quantity);
            if ($quantity <= 0) {
                return;
            }
        }

        $product = Product::find($productId);
        $attributes = array_merge([
            'Source' => self::$returningSalesItemIds ? StockBatch::SOURCE_CUSTOMER_RETURN : StockBatch::SOURCE_STOCK_ADDED,
            'ReceivedAt' => now(),
            'UnitCost' => round((float) ($product?->CostPrice ?? 0), 2),
        ], array_filter(self::$incoming ?? [], fn ($value) => $value !== null));

        $costForPrice = isset(self::$incoming['UnitCost']) ? (float) self::$incoming['UnitCost'] : (float) ($product?->CostPrice ?? 0);
        StockBatch::create(array_merge($attributes, [
            'ProductID' => $productId,
            'SellingPrice' => $product?->sellingPriceForCost($costForPrice),
            'QuantityReceived' => Quantity::normalize($quantity),
            'QuantityRemaining' => Quantity::normalize($quantity),
        ]));
    }

    private static function putBack(array $salesItemIds, float $quantity): float
    {
        $allocations = StockBatchAllocation::whereIn('SalesItemID', $salesItemIds)
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
