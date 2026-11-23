<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('StockReceivingDeliveryItem', function (Blueprint $table) {
            $table->decimal('BaseQuantity', 12, 2)->nullable()->after('Quantity');
        });

        DB::table('StockReceivingDeliveryItem')->orderBy('StockReceivingDeliveryItemID')->get()->each(function ($line) {
            $perUnit = (float) (DB::table('PurchaseOrderItem')->where('PurchaseOrderItemID', $line->PurchaseOrderItemID)->value('QuantityPerUnit') ?: 1);
            DB::table('StockReceivingDeliveryItem')->where('StockReceivingDeliveryItemID', $line->StockReceivingDeliveryItemID)
                ->update(['BaseQuantity' => round($line->Quantity * $perUnit, 2)]);
        });
    }

    public function down(): void
    {
        Schema::table('StockReceivingDeliveryItem', function (Blueprint $table) {
            $table->dropColumn('BaseQuantity');
        });
    }
};
