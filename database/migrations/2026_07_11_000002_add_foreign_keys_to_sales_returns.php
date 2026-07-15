<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->foreign('ApprovedBy')->references('id')->on('users')->nullOnDelete();
            $table->foreign('ProcessedBy')->references('id')->on('users')->nullOnDelete();
            $table->foreign('StaffID')->references('StaffID')->on('Staff')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->dropForeign(['ApprovedBy']);
            $table->dropForeign(['ProcessedBy']);
            $table->dropForeign(['StaffID']);
        });
    }
};
