<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NON_RETURNABLE_CATEGORIES = ['CONSUMABLES', 'WIRE', 'FIBER CONNECTOR'];

    public function up(): void
    {
        Schema::table('Product', function (Blueprint $table) {
            $table->boolean('IsReturnable')->default(true);
        });

        $categoryIds = DB::table('Category')
            ->whereIn(DB::raw('UPPER(TRIM(CategoryName))'), self::NON_RETURNABLE_CATEGORIES)
            ->pluck('CategoryID');

        DB::table('Product')->whereIn('CategoryID', $categoryIds)->update(['IsReturnable' => false]);
    }

    public function down(): void
    {
        Schema::table('Product', function (Blueprint $table) {
            $table->dropColumn('IsReturnable');
        });
    }
};
