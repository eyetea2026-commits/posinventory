<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
