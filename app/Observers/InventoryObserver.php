<?php

namespace App\Observers;

use App\Models\Inventory;
use App\Models\User;
use App\Notifications\LowStockAlert;
use Illuminate\Support\Facades\Notification;

class InventoryObserver
{
    // Single choke point for the low-stock alert: every code path that
    // changes stock (sale, refund, replacement, receiving, adjustment,
    // damage) ends in Inventory::save(), so notifying here instead of at
    // each call site avoids duplicating the trigger condition everywhere.
    public function saved(Inventory $inventory): void
    {
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

        Notification::send($admins, new LowStockAlert($product, $inventory->Quantity, $inventory->Status));
    }
}
