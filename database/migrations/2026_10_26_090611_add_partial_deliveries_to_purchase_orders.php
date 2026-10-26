<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A purchase order stays open after a short delivery: more deliveries
     * can be received against it until every line is received, or the
     * owner cancels what's still owed (the order is then Closed).
     *
     * - PurchaseOrderItem.CancelledQuantity: the part of a line the owner
     *   cancelled (never delivered).
     * - PurchaseOrder.RemainderCancel*: why/who/when the rest was cancelled.
     * - StockReceivingDelivery(+Item): one row per delivery (date, receipt
     *   number, who received it, quantities), so a second delivery no longer
     *   overwrites the first.
     */
    public function up(): void
    {
        Schema::table('PurchaseOrderItem', function (Blueprint $table) {
            $table->integer('CancelledQuantity')->default(0)->after('ReceivedQuantity');
        });

        Schema::table('PurchaseOrder', function (Blueprint $table) {
            $table->string('RemainderCancelReason', 255)->nullable();
            $table->unsignedBigInteger('RemainderCancelledBy')->nullable();
            $table->dateTime('RemainderCancelledAt')->nullable();
        });

        Schema::create('StockReceivingDelivery', function (Blueprint $table) {
            $table->id('StockReceivingDeliveryID');
            $table->unsignedBigInteger('StockReceivingBatchID');
            $table->unsignedInteger('PurchaseOrderID');
            $table->string('ReceiptNumber', 50)->nullable()->unique();
            $table->unsignedBigInteger('ReceivedBy')->nullable();
            $table->dateTime('ReceivedAt');
            $table->timestamps();

            $table->foreign('StockReceivingBatchID')->references('StockReceivingBatchID')->on('StockReceivingBatch')->cascadeOnDelete();
            $table->foreign('PurchaseOrderID')->references('PurchaseOrderID')->on('PurchaseOrder')->cascadeOnDelete();
            $table->foreign('ReceivedBy')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('StockReceivingDeliveryItem', function (Blueprint $table) {
            $table->id('StockReceivingDeliveryItemID');
            $table->unsignedBigInteger('StockReceivingDeliveryID');
            $table->unsignedInteger('PurchaseOrderItemID');
            $table->unsignedInteger('ProductID');
            $table->integer('Quantity');

            $table->foreign('StockReceivingDeliveryID')->references('StockReceivingDeliveryID')->on('StockReceivingDelivery')->cascadeOnDelete();
            $table->foreign('PurchaseOrderItemID')->references('PurchaseOrderItemID')->on('PurchaseOrderItem')->cascadeOnDelete();
        });

        // Deliveries already completed become the first entry of their
        // history (receipt number, who, when, what was received).
        $completed = DB::table('StockReceivingBatch')->where('Status', 'completed')->get();
        foreach ($completed as $batch) {
            $items = DB::table('PurchaseOrderItem')->where('PurchaseOrderID', $batch->PurchaseOrderID)->where('ReceivedQuantity', '>', 0)->get();
            if ($items->isEmpty()) {
                continue;
            }
            $deliveryId = DB::table('StockReceivingDelivery')->insertGetId([
                'StockReceivingBatchID' => $batch->StockReceivingBatchID,
                'PurchaseOrderID' => $batch->PurchaseOrderID,
                'ReceiptNumber' => $batch->ReceiptNumber ?? null,
                'ReceivedBy' => $batch->ReceivedBy,
                'ReceivedAt' => $batch->CompletedAt ?? $batch->updated_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ], 'StockReceivingDeliveryID');
            foreach ($items as $item) {
                DB::table('StockReceivingDeliveryItem')->insert([
                    'StockReceivingDeliveryID' => $deliveryId,
                    'PurchaseOrderItemID' => $item->PurchaseOrderItemID,
                    'ProductID' => $item->ProductID,
                    'Quantity' => $item->ReceivedQuantity,
                ]);
            }
        }

        // Orders that were short-delivered before this change were closed off
        // after one delivery; reopen them so the rest can still be received
        // (or cancelled by the owner).
        $partialOrderIds = DB::table('PurchaseOrder')->where('Status', 'partially_received')->pluck('PurchaseOrderID');
        DB::table('StockReceivingBatch')->whereIn('PurchaseOrderID', $partialOrderIds)
            ->update(['Status' => 'pending', 'CompletedAt' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('StockReceivingDeliveryItem');
        Schema::dropIfExists('StockReceivingDelivery');

        Schema::table('PurchaseOrder', function (Blueprint $table) {
            $table->dropColumn(['RemainderCancelReason', 'RemainderCancelledBy', 'RemainderCancelledAt']);
        });

        Schema::table('PurchaseOrderItem', function (Blueprint $table) {
            $table->dropColumn('CancelledQuantity');
        });
    }
};
