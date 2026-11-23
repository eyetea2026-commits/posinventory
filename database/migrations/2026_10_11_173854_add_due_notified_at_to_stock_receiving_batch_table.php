<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('StockReceivingBatch', function (Blueprint $table) {
            $table->dateTime('DueNotifiedAt')->nullable()->after('CompletedAt');
        });
    }

    public function down(): void
    {
        Schema::table('StockReceivingBatch', function (Blueprint $table) {
            $table->dropColumn('DueNotifiedAt');
        });
    }
};
