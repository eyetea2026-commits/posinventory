<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->string('Remarks', 500)->nullable()->after('Reason');
        });
    }

    public function down()
    {
        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->dropColumn('Remarks');
        });
    }
};
