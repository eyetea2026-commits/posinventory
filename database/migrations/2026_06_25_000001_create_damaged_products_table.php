<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('DamagedProduct', function (Blueprint $table) {
            $table->increments('DamageID');
            $table->integer('Quantity');
            $table->string('Description', 500);
            $table->date('DateRecorded');
            $table->unsignedInteger('ProductID');
            $table->unsignedInteger('SupplierID');
            $table->foreign('ProductID')->references('ProductID')->on('Product')->onDelete('cascade');
            $table->foreign('SupplierID')->references('SupplierID')->on('Supplier')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('DamagedProduct');
    }
};