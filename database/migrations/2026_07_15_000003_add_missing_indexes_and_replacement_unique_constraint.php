<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->index('Status');
        });

        Schema::table('DamagedProduct', function (Blueprint $table) {
            $table->index('Status');
            $table->index('DateRecorded');
            $table->index('deleted_at');
        });

        Schema::table('Replacement', function (Blueprint $table) {
            // Enforce the 1:1 relationship (SalesReturn::replacement() /
            // Replacement::salesReturn() are modeled as hasOne/belongsTo) at
            // the database layer, not just in application-level status
            // guards, which a race condition could otherwise slip past.
            $table->unique('SalesReturnID');
        });
    }

    public function down(): void
    {
        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->dropIndex(['Status']);
        });

        Schema::table('DamagedProduct', function (Blueprint $table) {
            $table->dropIndex(['Status']);
            $table->dropIndex(['DateRecorded']);
            $table->dropIndex(['deleted_at']);
        });

        Schema::table('Replacement', function (Blueprint $table) {
            $table->dropUnique(['SalesReturnID']);
        });
    }
};
