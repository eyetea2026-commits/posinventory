<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Product', function (Blueprint $table) {
            if (! Schema::hasColumn('Product', 'SKU')) {
                $table->string('SKU', 100)->nullable()->unique()->after('CategoryID');
            }
        });

        DB::table('Product')->whereNull('SKU')->orderBy('ProductID')->get(['ProductID'])->each(function ($product) {
            DB::table('Product')->where('ProductID', $product->ProductID)->update([
                'SKU' => 'SKU-'.str_pad((string) $product->ProductID, 6, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('Product', function (Blueprint $table) {
            if (Schema::hasColumn('Product', 'SKU')) {
                $table->dropUnique(['SKU']);
                $table->dropColumn('SKU');
            }
        });
    }
};
