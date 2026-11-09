<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Re-Order Point is computed now (App\Services\ReorderPoint) and is
 * never below 10, so a new stock row starts at 10 instead of 50.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Inventory', function (Blueprint $table) {
            $table->integer('ReorderThreshold')->nullable()->default(10)->change();
        });
    }

    public function down(): void
    {
        Schema::table('Inventory', function (Blueprint $table) {
            $table->integer('ReorderThreshold')->nullable()->default(50)->change();
        });
    }
};
