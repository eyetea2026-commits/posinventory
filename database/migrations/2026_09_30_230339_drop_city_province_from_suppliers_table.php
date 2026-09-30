<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reverses 2026_09_30_050516_add_city_province_to_suppliers_table.php.
// City/Province turned out redundant with the existing free-text Address
// field and were never actually adopted — removed rather than left unused.
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
