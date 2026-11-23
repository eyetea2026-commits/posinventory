<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Supplier', function (Blueprint $table) {
            $table->dropColumn(['City', 'Province']);
        });
    }

    public function down(): void
    {
        Schema::table('Supplier', function (Blueprint $table) {
            $table->string('City', 100)->nullable()->after('Address');
            $table->string('Province', 100)->nullable()->after('City');
        });
    }
};
