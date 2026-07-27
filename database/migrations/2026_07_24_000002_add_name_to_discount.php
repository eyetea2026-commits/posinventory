<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Discount', function (Blueprint $table) {
            $table->string('Name', 100)->nullable()->after('DiscountRate');
        });
    }

    public function down(): void
    {
        Schema::table('Discount', function (Blueprint $table) {
            $table->dropColumn('Name');
        });
    }
};
