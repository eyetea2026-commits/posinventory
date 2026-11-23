<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Brand', function (Blueprint $table) {
            $table->unsignedInteger('CategoryID')->nullable()->after('BrandName');
            $table->string('BrandNameNormalized', 100)->nullable()->after('CategoryID');
        });

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

    public function down(): void
    {
        Schema::table('Brand', function (Blueprint $table) {
            $table->dropUnique('brand_name_normalized_category_unique');
            $table->dropForeign(['CategoryID']);
            $table->dropColumn(['CategoryID', 'BrandNameNormalized']);
        });
    }
};
