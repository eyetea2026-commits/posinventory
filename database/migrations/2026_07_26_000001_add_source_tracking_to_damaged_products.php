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

        // Backfill existing rows: a non-null SalesReturnID means the record
        // came from the customer-return diversion flow; every other
        // pre-existing row was created via the manual "Add Damage Record"
        // form (stock-adjustment-originated records don't exist yet — that
        // path is introduced alongside this migration).
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
