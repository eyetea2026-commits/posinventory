<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// SalesReturn has never tracked exactly when a return request was submitted
// — ReturnDate is a DATE column (no time component), and the model disabled
// Eloquent's created_at/updated_at entirely. The Damage Record "Requested
// By" section needs a real submission timestamp, not a fabricated one.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->timestamps();
        });

        // Backfill existing rows so historical records don't show a blank
        // timestamp — midnight of the known ReturnDate is the closest true
        // information available for rows that predate this column.
        DB::table('SalesReturn')->whereNull('created_at')->update([
            'created_at' => DB::raw('ReturnDate'),
            'updated_at' => DB::raw('ReturnDate'),
        ]);
    }

    public function down(): void
    {
        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }
};
