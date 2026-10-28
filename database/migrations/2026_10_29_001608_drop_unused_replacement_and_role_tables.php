<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two tables nothing uses any more. Replacement held the item given
     * back for a return, but returns have been refund-only since
     * 2026-07-19. Role was the first roles table; the app has always read
     * Laravel's `roles` table instead. A table that still holds rows is
     * left in place rather than dropped with its data.
     */
    public function up(): void
    {
        foreach (['Replacement', 'Role'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException("The {$table} table still has records; it was not dropped.");
            }
        }

        Schema::dropIfExists('Replacement');
        Schema::dropIfExists('Role');
    }

    public function down(): void
    {
        Schema::create('Role', function (Blueprint $table) {
            $table->increments('RoleID');
            $table->string('RoleName', 50);
        });

        Schema::create('Replacement', function (Blueprint $table) {
            $table->increments('ReplacementID');
            $table->unsignedInteger('SalesReturnID')->unique();
            $table->unsignedInteger('ReplacementProductID');
            $table->integer('Quantity');
            $table->unsignedBigInteger('ProcessedBy')->nullable();
            $table->date('ReplacementDate');
            $table->string('SlipNumber', 50)->nullable()->unique();
            $table->string('Notes', 255)->nullable();

            $table->foreign('SalesReturnID')->references('SalesReturnID')->on('SalesReturn')->onDelete('cascade');
            $table->foreign('ReplacementProductID')->references('ProductID')->on('Product')->onDelete('restrict');
            $table->foreign('ProcessedBy')->references('id')->on('users')->nullOnDelete();
        });
    }
};
