<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('StockReceivingBatch', function (Blueprint $table) {
            $table->id('StockReceivingBatchID');
            $table->unsignedInteger('PurchaseOrderID')->unique();
            $table->string('Status', 20)->default('pending');
            $table->unsignedBigInteger('ReceivedBy')->nullable();
            $table->dateTime('CompletedAt')->nullable();
            $table->timestamps();

            $table->foreign('PurchaseOrderID')->references('PurchaseOrderID')->on('PurchaseOrder')->cascadeOnDelete();
            $table->foreign('ReceivedBy')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('PurchaseOrderItem', function (Blueprint $table) {
            $table->string('ReceiptNumber', 50)->nullable()->after('ReceivedQuantity');
        });
    }

    public function down(): void
    {
        Schema::table('PurchaseOrderItem', function (Blueprint $table) {
            $table->dropColumn('ReceiptNumber');
        });

        Schema::dropIfExists('StockReceivingBatch');
    }
};
