<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('StockReceiving', function (Blueprint $table) {
            $table->unsignedInteger('PurchaseOrderID')->nullable()->after('ProductID');
            $table->unsignedInteger('PurchaseOrderItemID')->nullable()->after('PurchaseOrderID');

            // Ad-hoc receiving (stock arriving with no formal PO) must keep
            // working unchanged — both columns stay null for that case.
            $table->foreign('PurchaseOrderID')->references('PurchaseOrderID')->on('PurchaseOrder')->nullOnDelete();
            $table->foreign('PurchaseOrderItemID')->references('PurchaseOrderItemID')->on('PurchaseOrderItem')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('StockReceiving', function (Blueprint $table) {
            $table->dropForeign(['PurchaseOrderID']);
            $table->dropForeign(['PurchaseOrderItemID']);
            $table->dropColumn(['PurchaseOrderID', 'PurchaseOrderItemID']);
        });
    }
};
