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
        // Give every pre-existing SalesReturn row (which only ever had one
        // product) a matching SalesReturnItem, so application code can read
        // $salesReturn->items uniformly for old and new rows alike instead
        // of branching on whether the header ProductID is set.
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

    public function down()
    {
        // Irreversible: backfilled rows are indistinguishable from
        // genuinely-created single-item SalesReturnItem rows.
    }
};
