<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('Product', 'Description')) {
            Schema::table('Product', function (Blueprint $table) {
                $table->text('Description')->nullable()->after('Model');
            });
        }

        if (! Schema::hasColumn('Product', 'CostPrice')) {
            Schema::table('Product', function (Blueprint $table) {
                $table->decimal('CostPrice', 10, 2)->nullable()->default(0)->after('Price');
            });
        }

        if (! Schema::hasColumn('Inventory', 'ReorderThreshold')) {
            Schema::table('Inventory', function (Blueprint $table) {
                $table->integer('ReorderThreshold')->nullable()->default(50)->after('Quantity');
            });
        }

        if (! Schema::hasColumn('Category', 'Description')) {
            Schema::table('Category', function (Blueprint $table) {
                $table->text('Description')->nullable()->after('CategoryName');
            });
        }
    }

    public function down()
    {
        Schema::table('Product', function (Blueprint $table) {
            $table->dropColumn(['Description', 'CostPrice']);
        });

        Schema::table('Inventory', function (Blueprint $table) {
            $table->dropColumn('ReorderThreshold');
        });

        Schema::table('Category', function (Blueprint $table) {
            $table->dropColumn('Description');
        });
    }
};
