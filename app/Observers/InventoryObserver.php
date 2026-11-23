<?php

namespace App\Observers;

use App\Models\Inventory;
use App\Models\User;
use App\Notifications\LowStockAlert;
use App\Services\SerialNumbers;
use App\Services\StockBatches;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class InventoryObserver
{
    public function saved(Inventory $inventory): void
    {
        SerialNumbers::syncWithStock($inventory);
        StockBatches::syncWithStock($inventory);

        if (! $inventory->wasChanged('Status')) {
            return;
        }

        if (! in_array($inventory->Status, ['Low Stock', 'Out of Stock'], true)) {
            return;
        }

        $admins = User::admins();

        if ($admins->isEmpty()) {
            return;
        }

        $product = $inventory->product ?? $inventory->product()->first();

        if (! $product) {
            return;
        }

        try {
            Notification::send($admins, new LowStockAlert($product, $inventory->Quantity, $inventory->Status));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch LowStockAlert notification', [
                'product_id' => $product->ProductID,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
