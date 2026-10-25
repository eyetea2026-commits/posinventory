<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Categories whose products can't be returned or refunded once sold
     * (cut, crimped or used items: RJ45, coaxial cable, fiber connectors).
     *
     * @var list<string>
     */
    private const NON_RETURNABLE_CATEGORIES = ['CONSUMABLES', 'WIRE', 'FIBER CONNECTOR'];

    /**
     * Products can be tagged "Not returnable / refundable"; the ones in the
     * categories above start tagged.
     */
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
