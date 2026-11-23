<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('Product', 'ProfitMargin')) {
            Schema::table('Product', function (Blueprint $table) {
                $table->decimal('ProfitMargin', 8, 4)->default(45)->after('CostPrice');
            });
        }

        foreach (DB::table('Product')->get(['ProductID', 'Price', 'CostPrice']) as $product) {
            $batches = DB::table('StockBatch')
                ->where('ProductID', $product->ProductID)
                ->where('QuantityRemaining', '>', 0)
                ->orderBy('ReceivedAt')
                ->orderBy('StockBatchID')
                ->get(['StockBatchID', 'UnitCost']);

            $price = (float) $product->Price;
            $cost = (float) $product->CostPrice ?: (float) ($batches->first()->UnitCost ?? 0);
            $margin = $price > 0 && $cost > 0 ? max(0.0, round(($price - $cost) / $price * 100, 4)) : 45.0;

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
