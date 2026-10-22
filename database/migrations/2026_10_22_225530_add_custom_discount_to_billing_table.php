<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A custom percentage discount given at the POS with an Administrator's
     * approval. DiscountAmount keeps the sale's whole discount (promos plus
     * this); these columns record the custom part and who approved it.
     */
    public function up(): void
    {
        Schema::table('Billing', function (Blueprint $table) {
            $table->decimal('CustomDiscountPercent', 5, 2)->nullable()->after('DiscountAmount');
            $table->decimal('CustomDiscountAmount', 10, 2)->nullable()->after('CustomDiscountPercent');
            $table->unsignedBigInteger('CustomDiscountApprovedBy')->nullable()->after('CustomDiscountAmount');
        });
    }

    public function down(): void
    {
        Schema::table('Billing', function (Blueprint $table) {
            $table->dropColumn(['CustomDiscountPercent', 'CustomDiscountAmount', 'CustomDiscountApprovedBy']);
        });
    }
};
