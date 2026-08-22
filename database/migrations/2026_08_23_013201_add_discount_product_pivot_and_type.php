<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Moves promo-to-product assignment from a single Discount.ProductID
    // column to a many-to-many pivot, so one promo can be assigned to
    // several specific products (decided in a separate "Apply" step after
    // creation) instead of exactly one chosen at creation time.
    //
    // Discount.ProductID itself is deliberately left in place, untouched —
    // it stays nullable and keeps its existing nullOnDelete FK, exactly per
    // the redesign migration's own historical-Billing-integrity reasoning.
    // It simply becomes vestigial for new rows going forward (always null),
    // while old rows and the Billing/Discount FK chain are undisturbed.
    public function up(): void
    {
        Schema::create('DiscountProduct', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('DiscountID');
            $table->unsignedInteger('ProductID');
            $table->timestamps();

            $table->foreign('DiscountID')->references('DiscountID')->on('Discount')->cascadeOnDelete();
            $table->foreign('ProductID')->references('ProductID')->on('Product')->cascadeOnDelete();
            $table->unique(['DiscountID', 'ProductID']);
        });

        Schema::table('Discount', function (Blueprint $table) {
            // Only 'percentage' is wired into any computation right now —
            // matches every example in the spec this shipped for. The
            // column exists (with 'fixed' selectable in the admin form) so
            // a fixed-amount type can be wired up later without another
            // migration, but its math doesn't ship this round.
            $table->string('DiscountType', 20)->default('percentage')->after('DiscountRate');
        });

        // Backfill: every pre-existing promo that already has a single
        // ProductID keeps working identically through the new pivot-based
        // lookup, with zero manual re-work required after this deploys.
        $existingAssignments = DB::table('Discount')
            ->whereNotNull('ProductID')
            ->select('DiscountID', 'ProductID')
            ->get();

        $now = now();
        foreach ($existingAssignments as $row) {
            DB::table('DiscountProduct')->insert([
                'DiscountID' => $row->DiscountID,
                'ProductID' => $row->ProductID,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('Discount', function (Blueprint $table) {
            $table->dropColumn('DiscountType');
        });

        Schema::dropIfExists('DiscountProduct');
    }
};
