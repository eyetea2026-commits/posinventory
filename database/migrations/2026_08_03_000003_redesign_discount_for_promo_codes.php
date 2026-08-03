<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Redesigns Discount from a system-wide percentage rate into a
    // product-specific promo code. Existing rows are left in place with
    // ProductID/PromoCode/StartDate/EndDate null rather than migrated or
    // deleted — they're historical "general discount" policies that don't
    // fit the new per-product model, and Billing.DiscountID (nullable,
    // ON DELETE SET NULL) already references some of them, so deleting
    // would lose real historical receipt data for zero benefit. The new
    // admin UI simply never selects/shows a row with a null ProductID as
    // an active promo going forward.
    public function up(): void
    {
        // Left over from the old system-wide-rate design: a UNIQUE
        // constraint on DiscountRate alone, which made "two different
        // products both 20% off" impossible under the new per-product
        // model. Promo codes get their own uniqueness constraint instead
        // (PromoCode, below).
        Schema::table('Discount', function (Blueprint $table) {
            $table->dropUnique(['DiscountRate']);
        });

        Schema::table('Discount', function (Blueprint $table) {
            $table->unsignedInteger('ProductID')->nullable()->after('DiscountID');
            $table->foreign('ProductID')->references('ProductID')->on('Product')->nullOnDelete();

            $table->string('PromoCode', 30)->nullable()->unique()->after('Name');
            $table->text('Description')->nullable()->after('PromoCode');
            $table->date('StartDate')->nullable()->after('Description');
            $table->date('EndDate')->nullable()->after('StartDate');
            // Admin-controlled: 'active' or 'inactive' only. "Expired" is
            // never stored — it's computed at read time from EndDate (see
            // Discount::getEffectiveStatusAttribute()) so it can't drift out
            // of sync with the clock the way a stored value could without a
            // scheduler, which this host doesn't reliably run one of.
            $table->string('Status', 20)->default('active')->after('EndDate');

            $table->unsignedBigInteger('CreatedBy')->nullable()->after('Status');
            $table->foreign('CreatedBy')->references('id')->on('users')->nullOnDelete();

            // Real audit timestamps (the table previously had none at all)
            // plus a soft delete — a promo can be referenced by historical
            // Billing rows via DiscountID, so "delete" must not remove the
            // row a past receipt still needs to resolve, the same reasoning
            // DamagedProduct's own SoftDeletes already follows.
            $table->timestamps();
            $table->softDeletes();
        });

        // DiscountRate was a free-text string column ("20"); every existing
        // value is a plain integer/decimal string, so this is a lossless
        // reinterpretation, not a data transformation.
        Schema::table('Discount', function (Blueprint $table) {
            $table->decimal('DiscountRate', 5, 2)->default(0)->change();
        });

        // Backfill created_at/updated_at for any pre-existing row — midnight
        // today is the closest true information available for rows that
        // predate this column, matching the SalesReturn timestamps backfill.
        \Illuminate\Support\Facades\DB::table('Discount')->whereNull('created_at')->update([
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('Discount', function (Blueprint $table) {
            $table->string('DiscountRate', 20)->change();
        });

        Schema::table('Discount', function (Blueprint $table) {
            $table->dropForeign(['ProductID']);
            $table->dropForeign(['CreatedBy']);
            $table->dropColumn(['ProductID', 'PromoCode', 'Description', 'StartDate', 'EndDate', 'Status', 'CreatedBy', 'created_at', 'updated_at', 'deleted_at']);
        });

        Schema::table('Discount', function (Blueprint $table) {
            $table->unique('DiscountRate');
        });
    }
};
