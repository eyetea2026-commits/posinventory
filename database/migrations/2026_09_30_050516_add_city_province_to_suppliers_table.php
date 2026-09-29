<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Additive 1NF fix: adds structured City/Province fields alongside the
// existing free-text Address, rather than replacing it. Real production
// supplier addresses are full street addresses ("201 RLC Bldg., Sobrecarey
// St. cor. Sta. Ana Ave., Davao City 8000") or don't describe a single
// location at all ("Cebu-based supplier serving Butuan, Bayugan, Gingoog
// and Surigao City") -- forcing every existing row into a rigid City +
// Province split would lose real detail or garble that data. City/Province
// are nullable and left blank on existing rows; staff can fill them in
// going forward via the Add/Edit Supplier form.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Supplier', function (Blueprint $table) {
            $table->string('City', 100)->nullable()->after('Address');
            $table->string('Province', 100)->nullable()->after('City');
        });
    }

    public function down(): void
    {
        Schema::table('Supplier', function (Blueprint $table) {
            $table->dropColumn(['City', 'Province']);
        });
    }
};
