<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('SalesReturnItem', function (Blueprint $table) {
            $table->increments('SalesReturnItemID');
            $table->unsignedInteger('SalesReturnID');
            $table->unsignedInteger('ProductID');
            $table->integer('Quantity');
            $table->decimal('UnitPrice', 10, 2);
            $table->string('Reason', 255);

            $table->foreign('SalesReturnID')->references('SalesReturnID')->on('SalesReturn')->onDelete('cascade');
            $table->foreign('ProductID')->references('ProductID')->on('Product')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('SalesReturnItem');
    }
};
