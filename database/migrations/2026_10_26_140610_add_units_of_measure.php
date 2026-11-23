<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('Product')->whereNull('UnitOfMeasure')->orWhere('UnitOfMeasure', '')->update(['UnitOfMeasure' => 'piece']);

        Schema::table('Product', function (Blueprint $table) {
            $table->string('UnitOfMeasure', 20)->default('piece')->nullable(false)->change();
            $table->string('PurchaseUnit', 20)->nullable()->after('UnitOfMeasure');
            $table->decimal('QuantityPerPurchaseUnit', 12, 2)->default(1)->after('PurchaseUnit');
            $table->decimal('PurchaseUnitPrice', 10, 2)->nullable()->after('QuantityPerPurchaseUnit');
            $table->boolean('AllowsFractionalQuantity')->default(false)->after('PurchaseUnitPrice');
        });

        Schema::table('Inventory', function (Blueprint $table) {
            $table->decimal('Quantity', 12, 2)->change();
        });

        Schema::table('StockAdjustment', function (Blueprint $table) {
            $table->decimal('QuantityAdjust', 12, 2)->change();
        });

        Schema::table('DamagedProduct', function (Blueprint $table) {
            $table->decimal('Quantity', 12, 2)->change();
        });

        Schema::table('SalesItem', function (Blueprint $table) {
            $table->decimal('Quantity', 12, 2)->change();
            $table->string('Unit', 20)->default('piece')->after('Quantity');
            $table->decimal('BaseQuantity', 12, 2)->default(0)->after('Unit');
        });
        DB::table('SalesItem')->update(['BaseQuantity' => DB::raw('Quantity')]);

        Schema::table('SalesReturnItem', function (Blueprint $table) {
            $table->decimal('Quantity', 12, 2)->change();
            $table->string('Unit', 20)->default('piece')->after('Quantity');
            $table->decimal('BaseQuantity', 12, 2)->default(0)->after('Unit');
        });
        DB::table('SalesReturnItem')->update(['BaseQuantity' => DB::raw('Quantity')]);

        Schema::table('PurchaseOrderItem', function (Blueprint $table) {
            $table->string('Unit', 20)->default('piece')->after('Quantity');
            $table->decimal('QuantityPerUnit', 12, 2)->default(1)->after('Unit');
        });
    }

    public function down(): void
    {
        Schema::table('PurchaseOrderItem', function (Blueprint $table) {
            $table->dropColumn(['Unit', 'QuantityPerUnit']);
        });

        Schema::table('SalesReturnItem', function (Blueprint $table) {
            $table->dropColumn(['Unit', 'BaseQuantity']);
            $table->integer('Quantity')->change();
        });

        Schema::table('SalesItem', function (Blueprint $table) {
            $table->dropColumn(['Unit', 'BaseQuantity']);
            $table->integer('Quantity')->change();
        });

        Schema::table('DamagedProduct', function (Blueprint $table) {
            $table->integer('Quantity')->change();
        });

        Schema::table('StockAdjustment', function (Blueprint $table) {
            $table->integer('QuantityAdjust')->change();
        });

        Schema::table('Inventory', function (Blueprint $table) {
            $table->integer('Quantity')->change();
        });

        Schema::table('Product', function (Blueprint $table) {
            $table->dropColumn(['PurchaseUnit', 'QuantityPerPurchaseUnit', 'PurchaseUnitPrice', 'AllowsFractionalQuantity']);
            $table->string('UnitOfMeasure', 20)->nullable()->default(null)->change();
        });
    }
};
