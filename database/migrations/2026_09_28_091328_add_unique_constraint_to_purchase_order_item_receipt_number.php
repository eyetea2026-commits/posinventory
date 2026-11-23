<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PurchaseOrderItem', function (Blueprint $table) {
            $table->unique('ReceiptNumber');
        });
    }

    public function down(): void
    {
        Schema::table('PurchaseOrderItem', function (Blueprint $table) {
            $table->dropUnique(['ReceiptNumber']);
        });
    }
};
