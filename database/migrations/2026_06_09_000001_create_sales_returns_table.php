<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('SalesReturn', function (Blueprint $table) {
            $table->increments('SalesReturnID');
            $table->unsignedInteger('SalesTransactionID');
            $table->unsignedInteger('ProductID');
            $table->integer('Quantity');
            $table->string('Reason', 255);
            $table->string('Status', 20)->default('pending');
            $table->date('ReturnDate');
            $table->unsignedBigInteger('ApprovedBy')->nullable();

            $table->foreign('SalesTransactionID')->references('SalesTransactionID')->on('SalesTransaction')->onDelete('cascade');
            $table->foreign('ProductID')->references('ProductID')->on('Product')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('SalesReturn');
    }
};
