<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Brand becomes category-scoped: every Brand row now belongs to exactly
     * one Category (e.g. "Samsung" under "TV" is a different row from
     * "Samsung" under a different category). BrandNameNormalized mirrors
     * BrandName lowercased/trimmed and is what the composite unique index
     * is actually built on -- comparing already-normalized text keeps
     * duplicate detection case-insensitive regardless of the connection's
     * collation (unlike a plain unique index on BrandName + CategoryID,
     * which only happens to be case-insensitive under MySQL's default
     * utf8mb4_unicode_ci collation and would silently stop being so on any
     * other driver/collation, e.g. the sqlite connection the test suite
     * uses).
     */
    public function up(): void
    {
        Schema::table('Brand', function (Blueprint $table) {
            $table->unsignedInteger('CategoryID')->nullable()->after('BrandName');
            $table->string('BrandNameNormalized', 100)->nullable()->after('CategoryID');
        });

        // Backfill existing brands: assign each to whichever Category its
        // products appear under most often (a brand created before this
        // migration may span several categories in practice), falling back
        // to the first Category on record for a brand with no products yet.
        $fallbackCategoryId = DB::table('Category')->orderBy('CategoryID')->value('CategoryID');

        foreach (DB::table('Brand')->get() as $brand) {
            $topCategoryId = DB::table('Product')
                ->where('BrandID', $brand->BrandID)
                ->whereNotNull('CategoryID')
                ->select('CategoryID', DB::raw('COUNT(*) as total'))
                ->groupBy('CategoryID')
                ->orderByDesc('total')
                ->value('CategoryID');

            $resolvedCategoryId = $topCategoryId ?? $fallbackCategoryId;

            if ($resolvedCategoryId === null) {
                // No Category exists anywhere yet (a brand-new install) and
                // this brand has no products to infer one from -- nothing
                // meaningful to preserve under the new category-scoped
                // Brand rules, so drop the orphaned row rather than leave
                // it without a category the NOT NULL constraint below
                // would otherwise reject.
                DB::table('Brand')->where('BrandID', $brand->BrandID)->delete();

                continue;
            }

            DB::table('Brand')->where('BrandID', $brand->BrandID)->update([
                'CategoryID' => $resolvedCategoryId,
                'BrandNameNormalized' => mb_strtolower(trim($brand->BrandName)),
            ]);
        }

        Schema::table('Brand', function (Blueprint $table) {
            $table->unsignedInteger('CategoryID')->nullable(false)->change();
            $table->string('BrandNameNormalized', 100)->nullable(false)->change();
            $table->foreign('CategoryID')->references('CategoryID')->on('Category')->onDelete('cascade');
            $table->unique(['BrandNameNormalized', 'CategoryID'], 'brand_name_normalized_category_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Brand', function (Blueprint $table) {
            $table->dropUnique('brand_name_normalized_category_unique');
            $table->dropForeign(['CategoryID']);
            $table->dropColumn(['CategoryID', 'BrandNameNormalized']);
        });
    }
};
