<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
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
        DB::statement('SELECT 1');
    }
};
