<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // StartDate/EndDate are the WHERE clause on every "is this promo
    // currently active/expired" query in the Discount module — the active
    // Promo Discount List, the "Choose Promo Discount" dropdown, the
    // Applied Discount/Promo List, History, and the assign-time overlap
    // check all filter on one or both of these columns. Neither had an
    // index, so every one of those was a full table scan.
    public function up(): void
    {
        Schema::table('Discount', function (Blueprint $table) {
            $table->index('StartDate');
            $table->index('EndDate');
        });
    }

    public function down(): void
    {
        Schema::table('Discount', function (Blueprint $table) {
            $table->dropIndex(['StartDate']);
            $table->dropIndex(['EndDate']);
        });
    }
};
