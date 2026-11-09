<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Automatic selling price (panel suggestion): each product keeps its own
 * Profit Margin, and every stock batch is priced from its own purchase cost:
 *   Selling Price = Batch Unit Cost / (1 - Profit Margin)
 * The POS sells each piece at the price of the batch it comes from (FIFO).
 *
 * Each product's margin is taken from its current Selling and Cost Price
 * (45% when either is missing), the batches still in stock are priced
 * with it, and the product's price becomes the price of the batch that
 * sells next.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Product', function (Blueprint $table) {
            // Percent, e.g. 45.0000. Four decimals keep an existing price exact.
            $table->decimal('ProfitMargin', 8, 4)->default(45)->after('CostPrice');
        });

        foreach (DB::table('Product')->get(['ProductID', 'Price', 'CostPrice']) as $product) {
            $batches = DB::table('StockBatch')
                ->where('ProductID', $product->ProductID)
                ->where('QuantityRemaining', '>', 0)
                ->orderBy('ReceivedAt')
                ->orderBy('StockBatchID')
                ->get(['StockBatchID', 'UnitCost']);

            // Without a Cost Price, the cost of the batch that sells next.
            $price = (float) $product->Price;
            $cost = (float) $product->CostPrice ?: (float) ($batches->first()->UnitCost ?? 0);
            $margin = $price > 0 && $cost > 0 ? round(($price - $cost) / $price * 100, 4) : 45.0;

            DB::table('Product')->where('ProductID', $product->ProductID)->update(['ProfitMargin' => $margin]);

            foreach ($batches as $batch) {
                DB::table('StockBatch')->where('StockBatchID', $batch->StockBatchID)->update([
                    'SellingPrice' => (float) $batch->UnitCost > 0 ? round((float) $batch->UnitCost / (1 - $margin / 100), 2) : null,
                ]);
            }

            $nextPrice = DB::table('StockBatch')
                ->where('ProductID', $product->ProductID)
                ->where('QuantityRemaining', '>', 0)
                ->whereNotNull('SellingPrice')
                ->orderBy('ReceivedAt')
                ->orderBy('StockBatchID')
                ->value('SellingPrice');

            if ($nextPrice !== null) {
                DB::table('Product')->where('ProductID', $product->ProductID)->update(['Price' => $nextPrice]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('Product', function (Blueprint $table) {
            $table->dropColumn('ProfitMargin');
        });
    }
};
