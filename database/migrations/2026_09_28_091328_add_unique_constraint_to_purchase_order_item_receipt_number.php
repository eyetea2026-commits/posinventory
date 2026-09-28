<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PurchaseOrderItem', function (Blueprint $table) {
            // Multiple NULLs (a line with no receipt number entered yet) are
            // still allowed under a unique index — only two equal non-null
            // values collide.
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
