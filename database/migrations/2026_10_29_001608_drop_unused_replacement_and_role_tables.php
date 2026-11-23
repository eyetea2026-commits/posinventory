<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
