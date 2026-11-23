<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ProductSupplier', function (Blueprint $table) {
            $table->increments('ProductSupplierID');
            $table->unsignedInteger('ProductID');
            $table->unsignedInteger('SupplierID');
            $table->decimal('CostPrice', 10, 2);
            $table->unsignedTinyInteger('IsPreferred')->nullable();

            $table->foreign('ProductID')->references('ProductID')->on('Product')->onDelete('cascade');
            $table->foreign('SupplierID')->references('SupplierID')->on('Supplier')->onDelete('cascade');

            $table->unique(['ProductID', 'SupplierID']);
            $table->unique(['ProductID', 'IsPreferred']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ProductSupplier');
    }
};
