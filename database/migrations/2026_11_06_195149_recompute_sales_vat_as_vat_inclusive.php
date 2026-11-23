<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('SalesTransaction')->whereNotNull('TotalAmount')
            ->update(['VatAmount' => DB::raw('ROUND(TotalAmount * 12.0 / 112, 2)')]);
    }

    public function down(): void
    {
        DB::table('SalesTransaction')->whereNotNull('TotalAmount')
            ->update(['VatAmount' => DB::raw('ROUND(TotalAmount * 0.12, 2)')]);
    }
};
