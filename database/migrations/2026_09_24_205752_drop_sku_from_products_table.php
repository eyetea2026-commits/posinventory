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
                // SQLite (used in tests) doesn't drop an index tied to a
                // column when the column itself is dropped — leaves it
                // dangling and referencing a column that no longer exists.
                // MySQL handles this implicitly, but dropping the unique
                // index explicitly first is the portable way to do it.
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
