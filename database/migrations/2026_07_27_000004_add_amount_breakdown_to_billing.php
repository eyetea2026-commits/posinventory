<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
