<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
