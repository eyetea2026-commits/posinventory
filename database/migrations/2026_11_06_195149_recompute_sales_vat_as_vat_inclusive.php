<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * POS prices include VAT, so a sale's VAT is the VAT inside its total
     * (Total × 12/112), not 12% of it. Past sales are recomputed the same
     * way so every report adds up: Total − VAT = Net Sales (VAT excluded),
     * and Net Sales + 12% = Total. The totals themselves don't change.
     */
    public function up(): void
    {
        DB::table('SalesTransaction')->whereNotNull('TotalAmount')
            ->update(['VatAmount' => DB::raw('ROUND(TotalAmount * 12.0 / 112, 2)')]);
    }

    /**
     * Back to the old figure, 12% of the total.
     */
    public function down(): void
    {
        DB::table('SalesTransaction')->whereNotNull('TotalAmount')
            ->update(['VatAmount' => DB::raw('ROUND(TotalAmount * 0.12, 2)')]);
    }
};
