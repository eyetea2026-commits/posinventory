<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('SalesTransaction', function (Blueprint $table) {
            $table->dateTime('SalesTransactionDate')->nullable(false)->change();
        });

        Schema::table('SalesTransaction', function (Blueprint $table) {
            $table->dropForeign(['StaffID']);
            $table->foreign('StaffID')->references('StaffID')->on('Staff')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::table('SalesTransaction', function (Blueprint $table) {
            $table->dropForeign(['StaffID']);
            $table->foreign('StaffID')->references('StaffID')->on('Staff')->onDelete('cascade');
        });

        Schema::table('SalesTransaction', function (Blueprint $table) {
            $table->date('SalesTransactionDate')->nullable(false)->change();
        });
    }
};
