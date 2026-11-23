<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
