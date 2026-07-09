<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Make BrandID nullable in Product table
        Schema::table('Product', function (Blueprint $table) {
            $table->unsignedInteger('BrandID')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('Product', function (Blueprint $table) {
            $table->unsignedInteger('BrandID')->change();
        });
    }
};