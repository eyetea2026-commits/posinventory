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
            // Only ever 1 or NULL — never 0. MySQL treats multiple NULLs in a
            // unique index as distinct, so (ProductID, IsPreferred) permits
            // any number of non-preferred rows per product while rejecting a
            // second IsPreferred=1 row for the same product — DB-level
            // "at most one preferred supplier" with no partial-index needed.
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
