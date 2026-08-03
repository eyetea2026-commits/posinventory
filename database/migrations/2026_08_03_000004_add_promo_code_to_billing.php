<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Frozen at sale time, same reasoning as Billing's existing
    // Subtotal/DiscountAmount/VatAmount columns: a promo's PromoCode is
    // editable after the fact (Edit Promo modal), so a receipt reading it
    // live off the Discount row could show a code the cashier never actually
    // typed. DiscountID itself is safe to keep reading live for everything
    // else — the promo it points at can never be deleted while a Billing row
    // still references it (DiscountController::destroy() blocks that).
    public function up(): void
    {
        Schema::table('Billing', function (Blueprint $table) {
            $table->string('PromoCode', 30)->nullable()->after('DiscountAmount');
        });
    }

    public function down(): void
    {
        Schema::table('Billing', function (Blueprint $table) {
            $table->dropColumn('PromoCode');
        });
    }
};
