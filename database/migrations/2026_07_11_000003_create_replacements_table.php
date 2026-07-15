<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('Replacement', function (Blueprint $table) {
            $table->increments('ReplacementID');
            $table->unsignedInteger('SalesReturnID');
            $table->unsignedInteger('ReplacementProductID');
            $table->integer('Quantity');
            $table->unsignedBigInteger('ProcessedBy')->nullable();
            $table->date('ReplacementDate');
            $table->string('SlipNumber', 50)->nullable()->unique();
            $table->string('Notes', 255)->nullable();

            $table->foreign('SalesReturnID')->references('SalesReturnID')->on('SalesReturn')->onDelete('cascade');
            $table->foreign('ReplacementProductID')->references('ProductID')->on('Product')->onDelete('restrict');
            $table->foreign('ProcessedBy')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('Replacement');
    }
};
