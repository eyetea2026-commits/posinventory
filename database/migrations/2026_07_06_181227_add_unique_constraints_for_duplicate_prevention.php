<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('Category', function (Blueprint $table) {
            $table->unique('CategoryName');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('name');
            $table->unique('contact_number');
        });

        Schema::table('Supplier', function (Blueprint $table) {
            $table->unique('SupplierName');
            $table->unique('Email');
        });

        Schema::table('Discount', function (Blueprint $table) {
            $table->unique('DiscountRate');
        });

        Schema::table('StockReceiving', function (Blueprint $table) {
            $table->unique('ReceiptNumber');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Category', function (Blueprint $table) {
            $table->dropUnique(['CategoryName']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropUnique(['contact_number']);
        });

        Schema::table('Supplier', function (Blueprint $table) {
            $table->dropUnique(['SupplierName']);
            $table->dropUnique(['Email']);
        });

        Schema::table('Discount', function (Blueprint $table) {
            $table->dropUnique(['DiscountRate']);
        });

        Schema::table('StockReceiving', function (Blueprint $table) {
            $table->dropUnique(['ReceiptNumber']);
        });
    }
};
