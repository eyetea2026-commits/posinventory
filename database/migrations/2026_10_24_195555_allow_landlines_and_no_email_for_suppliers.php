<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Supplier', function (Blueprint $table) {
            $table->string('ContactNumber', 50)->change();
            $table->string('Email', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('Supplier', function (Blueprint $table) {
            $table->string('ContactNumber', 20)->change();
            $table->string('Email', 100)->nullable(false)->change();
        });
    }
};
