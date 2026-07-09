<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('Role', function (Blueprint $table) {
            $table->increments('RoleID');
            $table->string('RoleName', 50);
        });

        Schema::create('Brand', function (Blueprint $table) {
            $table->increments('BrandID');
            $table->string('BrandName', 100);
        });

        Schema::create('Category', function (Blueprint $table) {
            $table->increments('CategoryID');
            $table->string('CategoryName', 100);
        });

        Schema::create('Product', function (Blueprint $table) {
            $table->increments('ProductID');
            $table->string('ProductName', 100);
            $table->string('Model', 100);
            $table->decimal('Price', 10, 2);
            $table->unsignedInteger('BrandID');
            $table->unsignedInteger('CategoryID');
            $table->foreign('BrandID')->references('BrandID')->on('Brand')->onDelete('cascade');
            $table->foreign('CategoryID')->references('CategoryID')->on('Category')->onDelete('cascade');
        });

        Schema::create('Inventory', function (Blueprint $table) {
            $table->increments('InventoryID');
            $table->integer('Quantity');
            $table->string('Status', 20);
            $table->unsignedInteger('ProductID');
            $table->foreign('ProductID')->references('ProductID')->on('Product')->onDelete('cascade');
        });

        Schema::create('Supplier', function (Blueprint $table) {
            $table->increments('SupplierID');
            $table->string('SupplierName', 100);
            $table->string('ContactNumber', 20);
            $table->string('Email', 100);
            $table->string('Address', 255);
        });

        Schema::create('PurchaseOrder', function (Blueprint $table) {
            $table->increments('PurchaseOrderID');
            $table->date('PurchaseDate');
            $table->string('Status', 20);
            $table->unsignedInteger('SupplierID');
            $table->foreign('SupplierID')->references('SupplierID')->on('Supplier')->onDelete('cascade');
        });

        Schema::create('PurchaseOrderItem', function (Blueprint $table) {
            $table->increments('PurchaseOrderItemID');
            $table->integer('Quantity');
            $table->decimal('UnitPrice', 10, 2);
            $table->unsignedInteger('PurchaseOrderID');
            $table->unsignedInteger('ProductID');
            $table->foreign('PurchaseOrderID')->references('PurchaseOrderID')->on('PurchaseOrder')->onDelete('cascade');
            $table->foreign('ProductID')->references('ProductID')->on('Product')->onDelete('cascade');
        });

        Schema::create('StockReceiving', function (Blueprint $table) {
            $table->increments('ReceivingID');
            $table->integer('Quantity');
            $table->date('DateReceived');
            $table->string('ReceiptNumber', 50);
            $table->unsignedInteger('ProductID');
            $table->unsignedInteger('SupplierID');
            $table->foreign('ProductID')->references('ProductID')->on('Product')->onDelete('cascade');
            $table->foreign('SupplierID')->references('SupplierID')->on('Supplier')->onDelete('cascade');
        });

        Schema::create('StockAdjustment', function (Blueprint $table) {
            $table->increments('AdjustmentID');
            $table->integer('QuantityAdjust');
            $table->string('Reason', 255);
            $table->date('Date');
            $table->unsignedInteger('ProductID');
            $table->foreign('ProductID')->references('ProductID')->on('Product')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('StockAdjustment');
        Schema::dropIfExists('StockReceiving');
        Schema::dropIfExists('PurchaseOrderItem');
        Schema::dropIfExists('PurchaseOrder');
        Schema::dropIfExists('Supplier');
        Schema::dropIfExists('Inventory');
        Schema::dropIfExists('Product');
        Schema::dropIfExists('Category');
        Schema::dropIfExists('Brand');
        Schema::dropIfExists('Role');
    }
};
