<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Database-level backstop for the SalesReturnController::approve() race
    // fix: even with the application-level lock-and-recheck in place, this
    // makes "one damage record per (return, product) pair" a guarantee the
    // database itself enforces, not just something the code is careful
    // about. SalesReturnID is nullable (most damage records aren't
    // return-originated at all) — MySQL's unique index treats multiple NULLs
    // as distinct, so non-return-sourced rows are unaffected.
    public function up(): void
    {
        Schema::table('DamagedProduct', function (Blueprint $table) {
            $table->unique(['SalesReturnID', 'ProductID'], 'damaged_product_return_product_unique');
        });
    }

    public function down(): void
    {
        Schema::table('DamagedProduct', function (Blueprint $table) {
            $table->dropUnique('damaged_product_return_product_unique');
        });
    }
};
