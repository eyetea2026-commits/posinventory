<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // PurchaseOrder never tracked created_at/updated_at (predates that
    // convention in this schema) and PurchaseDate is a plain DATE column
    // the admin freely edits as the order's business date — neither can
    // tell two purchase orders created on the same calendar day apart, so
    // "newest first" silently fell back to whatever order the database
    // happened to return them in. Adding real timestamps gives the listing
    // an actual creation moment to sort by, independent of PurchaseDate.
    public function up(): void
    {
        Schema::table('PurchaseOrder', function (Blueprint $table) {
            $table->timestamps();
        });

        // Backfill existing rows from PurchaseDate — the closest
        // approximation available for history that predates this column;
        // still orders them correctly relative to each other by day, just
        // without the sub-day precision that was never recorded.
        DB::table('PurchaseOrder')->whereNull('created_at')->update([
            'created_at' => DB::raw('PurchaseDate'),
            'updated_at' => DB::raw('PurchaseDate'),
        ]);
    }

    public function down(): void
    {
        Schema::table('PurchaseOrder', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }
};
