<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->unsignedInteger('StaffID')->nullable()->after('ApprovedBy');
            $table->string('CustomerName', 100)->nullable()->after('StaffID');
        });
    }

    public function down()
    {
        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->dropColumn(['StaffID', 'CustomerName']);
        });
    }
};