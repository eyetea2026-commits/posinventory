<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The price of a roll/box is no longer typed in: it's the price per
     * meter/piece x how many the last received roll held. Only whether the
     * product is also sold by the roll is kept (SellsByPurchaseUnit).
     */
    public function up(): void
    {
        Schema::table('Product', function (Blueprint $table) {
            $table->boolean('SellsByPurchaseUnit')->default(false)->after('QuantityPerPurchaseUnit');
        });
        DB::table('Product')->whereNotNull('PurchaseUnitPrice')->update(['SellsByPurchaseUnit' => true]);

        Schema::table('Product', function (Blueprint $table) {
            $table->dropColumn('PurchaseUnitPrice');
        });
    }

    public function down(): void
    {
        Schema::table('Product', function (Blueprint $table) {
            $table->decimal('PurchaseUnitPrice', 10, 2)->nullable()->after('QuantityPerPurchaseUnit');
        });
        DB::table('Product')->where('SellsByPurchaseUnit', true)
            ->update(['PurchaseUnitPrice' => DB::raw('ROUND(Price * QuantityPerPurchaseUnit, 2)')]);

        Schema::table('Product', function (Blueprint $table) {
            $table->dropColumn('SellsByPurchaseUnit');
        });
    }
};
