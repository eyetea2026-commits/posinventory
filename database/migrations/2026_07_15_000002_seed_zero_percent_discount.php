<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Billing.DiscountID is NOT NULL, so every sale needs a real Discount
    // row even when no discount applies. Previously the cashier checkout
    // endpoint lazily firstOrCreate()'d a matching row per sale (and for
    // any arbitrary rate a cashier typed); now that POS only offers
    // admin-managed discounts, a permanent 0% "No Discount" baseline row
    // needs to exist up front instead.
    public function up(): void
    {
        DB::table('Discount')->updateOrInsert(['DiscountRate' => 0], []);
    }

    public function down(): void
    {
        // Leave the row in place — other Billing records may reference it.
    }
};
