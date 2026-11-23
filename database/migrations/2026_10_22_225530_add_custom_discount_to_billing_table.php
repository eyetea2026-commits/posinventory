<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
