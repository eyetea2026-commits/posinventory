<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('SalesItem', function (Blueprint $table) {
            $table->decimal('DiscountAmount', 10, 2)->nullable()->after('UnitPrice');
        });
    }

    public function down(): void
    {
        Schema::table('SalesItem', function (Blueprint $table) {
            $table->dropColumn('DiscountAmount');
        });
    }
};
