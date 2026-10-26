<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Units of measurement: stock is kept in each product's base unit
     * (Product.UnitOfMeasure: piece, meter...), it can be bought by a bigger
     * purchase unit (1 roll = 5,000 meters, 1 box = 100 pieces) and sold by
     * the base unit or, at its own price, by the purchase unit.
     *
     * - Product: PurchaseUnit, QuantityPerPurchaseUnit, PurchaseUnitPrice,
     *   AllowsFractionalQuantity (1.5 meters); UnitOfMeasure becomes required.
     * - Stock quantities become decimal(12,2) so 1.5 meters can be sold,
     *   adjusted, returned or recorded as damaged.
     * - SalesItem: Unit sold + BaseQuantity (what left the stock).
     * - SalesReturnItem: Unit + BaseQuantity (what goes back / to Damage).
     * - PurchaseOrderItem: Unit ordered + QuantityPerUnit at order time.
     *
     * Every existing row is a piece: Unit 'piece', BaseQuantity = Quantity,
     * QuantityPerUnit 1, so nothing already recorded changes.
     */
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
