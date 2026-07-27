<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The receipt view used to recompute Subtotal/DiscountAmount/VatAmount
    // live from SalesItem + the Discount's CURRENT rate every time it was
    // viewed, instead of reading back what was actually charged at sale
    // time. That's wrong twice over: it doesn't match BillingAmount if the
    // rounding logic ever changes, and if an admin edits a Discount's rate
    // after the fact, every historical receipt using that discount would
    // silently start showing a different amount than what the customer
    // actually paid. These columns are nullable so existing rows (which
    // have no way to recover this breakdown after the fact) just fall back
    // to the old recompute behavior in the view.
    public function up(): void
    {
        Schema::table('Billing', function (Blueprint $table) {
            $table->decimal('Subtotal', 10, 2)->nullable()->after('BillingAmount');
            $table->decimal('DiscountAmount', 10, 2)->nullable()->after('Subtotal');
            $table->decimal('VatAmount', 10, 2)->nullable()->after('DiscountAmount');
        });
    }

    public function down(): void
    {
        Schema::table('Billing', function (Blueprint $table) {
            $table->dropColumn(['Subtotal', 'DiscountAmount', 'VatAmount']);
        });
    }
};
