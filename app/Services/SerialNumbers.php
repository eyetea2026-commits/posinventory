<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductSerial;
use Illuminate\Support\Collection;

/**
 * Keeps one "In Stock" serial number per piece for products whose category
 * tracks serial numbers. Runs after every stock save (InventoryObserver):
 * stock that went up gets new serials; stock that went down retires the
 * oldest ones, unless the caller already marked the exact pieces (a POS
 * sale marks the scanned serials Sold before saving the stock).
 */
class SerialNumbers
{
    private static string $removalStatus = ProductSerial::STATUS_REMOVED;

    /**
     * Pieces this callback takes out of stock are recorded with $status
     * (e.g. "Adjusted Out") instead of the generic "Removed from Stock".
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function removingAs(string $status, callable $callback)
    {
        $previous = self::$removalStatus;
        self::$removalStatus = $status;

        try {
            return $callback();
        } finally {
            self::$removalStatus = $previous;
        }
    }

    public static function syncWithStock(Inventory $inventory): void
    {
        $product = Product::with('category')->find($inventory->ProductID);
        if (! $product || ! $product->tracksSerialNumbers()) {
            return;
        }

        $inStock = ProductSerial::where('ProductID', $product->ProductID)
            ->where('Status', ProductSerial::STATUS_IN_STOCK)
            ->count();
        $difference = max(0, (int) $inventory->Quantity) - $inStock;

        if ($difference > 0) {
            self::create($product->ProductID, $difference);
        } elseif ($difference < 0) {
            ProductSerial::where('ProductID', $product->ProductID)
                ->where('Status', ProductSerial::STATUS_IN_STOCK)
                ->orderBy('ProductSerialID')
                ->limit(-$difference)
                ->get()
                ->each(fn (ProductSerial $serial) => $serial->update([
                    'Status' => self::$removalStatus,
                    'StatusDate' => now(),
                ]));
        }
    }

    /**
     * @return Collection<int, ProductSerial>
     */
    public static function create(int $productId, int $count): Collection
    {
        $serials = collect();

        for ($i = 0; $i < $count; $i++) {
            $serial = ProductSerial::create([
                'ProductID' => $productId,
                'Status' => ProductSerial::STATUS_IN_STOCK,
                'StatusDate' => now(),
            ]);
            $serial->update(['SerialNumber' => ProductSerial::formatNumber($serial->ProductSerialID)]);
            $serials->push($serial);
        }

        return $serials;
    }

    /**
     * A refunded piece goes back on the shelf with its own sticker: the
     * serials sold on that sale return to stock (latest first) before the
     * stock is saved, so no new numbers are made for them.
     */
    public static function returnToStock(int $salesTransactionId, int $productId, int $quantity): void
    {
        ProductSerial::where('SalesTransactionID', $salesTransactionId)
            ->where('ProductID', $productId)
            ->where('Status', ProductSerial::STATUS_SOLD)
            ->orderByDesc('ProductSerialID')
            ->limit($quantity)
            ->get()
            ->each(fn (ProductSerial $serial) => $serial->update([
                'Status' => ProductSerial::STATUS_IN_STOCK,
                'StatusDate' => now(),
            ]));
    }
}
