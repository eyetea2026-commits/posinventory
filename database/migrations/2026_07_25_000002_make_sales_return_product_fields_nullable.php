<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Drop the ProductID FK before widening the column to nullable, then
        // re-add it — new multi-item refund requests leave ProductID/Quantity/
        // Reason null on the header and store their lines in SalesReturnItem
        // instead. Old rows and replacement-type rows keep these populated.
        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->dropForeign(['ProductID']);
        });

        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->unsignedInteger('ProductID')->nullable()->change();
            $table->integer('Quantity')->nullable()->change();
            $table->string('Reason', 255)->nullable()->change();
        });

        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->foreign('ProductID')->references('ProductID')->on('Product')->onDelete('cascade');
        });
    }

    public function down()
    {
        // Irreversible: any row that relied on the nullable columns being
        // null (new multi-item requests) would need a ProductID/Quantity/
        // Reason backfilled from its items before this could revert safely.
        DB::statement('SELECT 1');
    }
};
