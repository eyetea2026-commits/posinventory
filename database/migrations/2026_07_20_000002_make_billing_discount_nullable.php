<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Billing', function (Blueprint $table) {
            $table->dropForeign(['DiscountID']);
        });

        Schema::table('Billing', function (Blueprint $table) {
            $table->unsignedInteger('DiscountID')->nullable()->change();
        });

        Schema::table('Billing', function (Blueprint $table) {
            $table->foreign('DiscountID')->references('DiscountID')->on('Discount')->onDelete('set null');
        });

        DB::table('Billing as b')
            ->join('Discount as d', 'b.DiscountID', '=', 'd.DiscountID')
            ->where('d.DiscountRate', 0)
            ->update(['b.DiscountID' => null]);

        DB::table('Discount')->where('DiscountRate', 0)->delete();
    }

    public function down(): void
    {
        Schema::table('Billing', function (Blueprint $table) {
            $table->dropForeign(['DiscountID']);
        });

        Schema::table('Billing', function (Blueprint $table) {
            $table->unsignedInteger('DiscountID')->nullable(false)->change();
        });

        Schema::table('Billing', function (Blueprint $table) {
            $table->foreign('DiscountID')->references('DiscountID')->on('Discount')->onDelete('cascade');
        });
    }
};
