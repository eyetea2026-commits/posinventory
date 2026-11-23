<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PurchaseOrder', function (Blueprint $table) {
            $table->timestamps();
        });

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
