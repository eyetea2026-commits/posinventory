<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ProductSerial', function (Blueprint $table) {
            $table->unsignedInteger('StockAdjustmentID')->nullable()->after('SalesTransactionID');
            $table->index('StockAdjustmentID');
        });
    }

    public function down(): void
    {
        Schema::table('ProductSerial', function (Blueprint $table) {
            $table->dropIndex(['StockAdjustmentID']);
            $table->dropColumn('StockAdjustmentID');
        });
    }
};
