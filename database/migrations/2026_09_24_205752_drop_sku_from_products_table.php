<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Product', function (Blueprint $table) {
            if (Schema::hasColumn('Product', 'SKU')) {
                $table->dropUnique(['SKU']);
                $table->dropColumn('SKU');
            }
        });
    }

    public function down(): void
    {
        Schema::table('Product', function (Blueprint $table) {
            if (! Schema::hasColumn('Product', 'SKU')) {
                $table->string('SKU', 100)->nullable()->unique()->after('CategoryID');
            }
        });
    }
};
