<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ProductSupplier', function (Blueprint $table) {
            $table->decimal('CostPrice', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('ProductSupplier')->whereNull('CostPrice')->update(['CostPrice' => 0]);

        Schema::table('ProductSupplier', function (Blueprint $table) {
            $table->decimal('CostPrice', 10, 2)->nullable(false)->change();
        });
    }
};
