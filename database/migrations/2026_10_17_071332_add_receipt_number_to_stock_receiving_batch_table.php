<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One Receipt Number per delivery (the whole Purchase Order), instead of
     * one per product line. Deliveries already recorded keep the first
     * Receipt Number entered on any of their lines.
     */
    public function up(): void
    {
        Schema::table('StockReceivingBatch', function (Blueprint $table) {
            $table->string('ReceiptNumber', 50)->nullable()->unique()->after('Status');
        });

        $batches = DB::table('StockReceivingBatch')->select('StockReceivingBatchID', 'PurchaseOrderID')->get();

        foreach ($batches as $batch) {
            $receiptNumber = DB::table('PurchaseOrderItem')
                ->where('PurchaseOrderID', $batch->PurchaseOrderID)
                ->whereNotNull('ReceiptNumber')
                ->where('ReceiptNumber', '!=', '')
                ->orderBy('PurchaseOrderItemID')
                ->value('ReceiptNumber');

            if ($receiptNumber !== null) {
                DB::table('StockReceivingBatch')
                    ->where('StockReceivingBatchID', $batch->StockReceivingBatchID)
                    ->update(['ReceiptNumber' => $receiptNumber]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('StockReceivingBatch', function (Blueprint $table) {
            $table->dropUnique(['ReceiptNumber']);
            $table->dropColumn('ReceiptNumber');
        });
    }
};
