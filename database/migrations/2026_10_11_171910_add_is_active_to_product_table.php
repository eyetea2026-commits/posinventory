<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Products are retired by marking them inactive instead of being
     * deleted, so their sales/stock/PO history is never lost. Every existing
     * product starts active.
     */
    public function up(): void
    {
        Schema::table('Product', function (Blueprint $table) {
            $table->boolean('IsActive')->default(true)->after('CategoryID');
        });
    }

    public function down(): void
    {
        Schema::table('Product', function (Blueprint $table) {
            $table->dropColumn('IsActive');
        });
    }
};
