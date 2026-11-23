<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->timestamps();
        });

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
