<?php

use App\Models\DamagedProduct;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('DamagedProduct', function (Blueprint $table) {
            $table->unsignedInteger('StockAdjustmentID')->nullable()->after('SalesReturnID');
            $table->foreign('StockAdjustmentID')->references('AdjustmentID')->on('StockAdjustment')->nullOnDelete();
            $table->string('SourceModule', 30)->nullable()->after('StockAdjustmentID');
            $table->string('ImagePath', 255)->nullable()->after('Remarks');
        });

        DamagedProduct::whereNull('SourceModule')->whereNotNull('SalesReturnID')->update(['SourceModule' => 'customer_return']);
        DamagedProduct::whereNull('SourceModule')->update(['SourceModule' => 'manual']);
    }

    public function down()
    {
        Schema::table('DamagedProduct', function (Blueprint $table) {
            $table->dropForeign(['StockAdjustmentID']);
            $table->dropColumn(['StockAdjustmentID', 'SourceModule', 'ImagePath']);
        });
    }
};
