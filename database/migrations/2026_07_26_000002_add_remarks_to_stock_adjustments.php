<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('StockAdjustment', function (Blueprint $table) {
            $table->text('Remarks')->nullable()->after('Reason');
        });
    }

    public function down()
    {
        Schema::table('StockAdjustment', function (Blueprint $table) {
            $table->dropColumn('Remarks');
        });
    }
};
