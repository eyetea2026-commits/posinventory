<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // "No discount" should mean no Discount row at all, not a forced 0%
    // baseline. Billing.DiscountID was NOT NULL with onDelete('cascade'),
    // which is why the 0% row seeded in 2026_07_15_000002 could never be
    // deleted without destroying the sales it was attached to. Make the
    // column nullable, reattach existing 0%-billings to NULL, and retire
    // the 0% row now that nothing references it.
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
        // Schema only — the 0%-row/data cleanup above is intentionally not
        // reversed, same as the seed migration this undoes.
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
