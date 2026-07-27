<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ProductCostHistory', function (Blueprint $table) {
            $table->increments('ProductCostHistoryID');
            $table->unsignedInteger('ProductID');
            $table->unsignedInteger('SupplierID')->nullable();
            $table->decimal('OldCostPrice', 10, 2)->nullable();
            $table->decimal('NewCostPrice', 10, 2);
            $table->unsignedBigInteger('ChangedBy')->nullable();
            $table->dateTime('ChangedAt');
            $table->string('Source', 30);

            $table->foreign('ProductID')->references('ProductID')->on('Product')->onDelete('cascade');
            $table->foreign('SupplierID')->references('SupplierID')->on('Supplier')->nullOnDelete();
            $table->foreign('ChangedBy')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ProductCostHistory');
    }
};
