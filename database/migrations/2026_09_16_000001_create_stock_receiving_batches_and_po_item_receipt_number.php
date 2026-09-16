<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per Purchase Order that has been printed and sent into the
        // receiving queue — a PO can have at most one batch (UNIQUE), which
        // is what makes printing idempotent at the database level even if
        // the print action somehow fires twice concurrently.
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

        // Receipt Number is entered per ordered line (alongside the
        // already-existing ReceivedQuantity) when a batch is completed via
        // "Add to Inventory" — kept on the same row as Quantity/
        // ReceivedQuantity rather than a new table, matching how those two
        // already live together here.
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
