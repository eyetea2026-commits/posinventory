<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('DamagedProduct', function (Blueprint $table) {
            $table->unsignedInteger('SalesReturnID')->nullable()->after('ProductID');
            $table->foreign('SalesReturnID')->references('SalesReturnID')->on('SalesReturn')->nullOnDelete();
        });

        // A return-originated damage record may not have a resolvable
        // supplier at creation time, so this must accept NULL.
        Schema::table('DamagedProduct', function (Blueprint $table) {
            $table->unsignedInteger('SupplierID')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('DamagedProduct', function (Blueprint $table) {
            $table->dropForeign(['SalesReturnID']);
            $table->dropColumn('SalesReturnID');
        });

        Schema::table('DamagedProduct', function (Blueprint $table) {
            $table->unsignedInteger('SupplierID')->nullable(false)->change();
        });
    }
};
