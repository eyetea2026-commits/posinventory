<?php

use App\Models\Product;
use App\Models\SalesItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        SalesReturn::whereNotNull('ProductID')->each(function (SalesReturn $return) {
            if ($return->items()->exists()) {
                return;
            }

            $salesItem = SalesItem::where('SalesTransactionID', $return->SalesTransactionID)
                ->where('ProductID', $return->ProductID)
                ->first();

            $unitPrice = $salesItem?->UnitPrice
                ?? Product::where('ProductID', $return->ProductID)->value('Price')
                ?? 0;

            SalesReturnItem::create([
                'SalesReturnID' => $return->SalesReturnID,
                'ProductID' => $return->ProductID,
                'Quantity' => $return->Quantity,
                'UnitPrice' => $unitPrice,
                'Reason' => $return->Reason,
            ]);
        });
    }

    public function down() {}
};
