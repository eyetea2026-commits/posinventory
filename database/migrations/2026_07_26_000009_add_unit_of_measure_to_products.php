<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('Product', function (Blueprint $table) {
            $table->string('UnitOfMeasure', 20)->nullable()->after('SKU');
        });
    }

    public function down()
    {
        Schema::table('Product', function (Blueprint $table) {
            $table->dropColumn('UnitOfMeasure');
        });
    }
};
