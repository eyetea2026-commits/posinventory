<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductSerial;
use Illuminate\Support\Collection;

class SerialNumbers
{
    private static string $removalStatus = ProductSerial::STATUS_REMOVED;

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

    public static function returnToStock(int $salesTransactionId, int $productId, int $quantity, ?int $salesReturnId = null): void
    {
        if ($salesReturnId && ProductSerial::where('SalesReturnID', $salesReturnId)->where('ProductID', $productId)->exists()) {
            ProductSerial::where('SalesReturnID', $salesReturnId)
                ->where('ProductID', $productId)
                ->where('Status', ProductSerial::STATUS_SOLD)
                ->limit($quantity)
                ->get()
                ->each(fn (ProductSerial $serial) => $serial->update([
                    'Status' => ProductSerial::STATUS_IN_STOCK,
                    'SalesTransactionID' => null,
                    'SalesReturnID' => null,
                    'StatusDate' => now(),
                ]));

            return;
        }

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
