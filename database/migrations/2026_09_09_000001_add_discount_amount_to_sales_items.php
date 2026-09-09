<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // SalesItem.UnitPrice has always been the product's full, undiscounted
    // price — a promo's discount was only ever recorded as one aggregate
    // Billing.DiscountAmount for the whole transaction, with no way to tell
    // which line(s) it came from. That made a return/refund on a
    // promo'd item refund the full price, overpaying the customer.
    // This records each line's own discount amount at sale time so a
    // partial-quantity return can prorate correctly. Null on every
    // historical row — those sales' original per-line discount was never
    // captured and can't be reconstructed after the fact, so refunds on
    // them keep falling back to full-price (documented at the call site).
    public function up(): void
    {
        Schema::table('SalesItem', function (Blueprint $table) {
            $table->decimal('DiscountAmount', 10, 2)->nullable()->after('UnitPrice');
        });
    }

    public function down(): void
    {
        Schema::table('SalesItem', function (Blueprint $table) {
            $table->dropColumn('DiscountAmount');
        });
    }
};
