<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A product photo, stored small (see App\Services\ProductImage) on the
     * public disk. Optional: products without one show a placeholder.
     */
    public function up(): void
    {
        Schema::table('Product', function (Blueprint $table) {
            $table->string('ImagePath', 255)->nullable()->after('Description');
        });
    }

    public function down(): void
    {
        Schema::table('Product', function (Blueprint $table) {
            $table->dropColumn('ImagePath');
        });
    }
};
