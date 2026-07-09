<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('SalesTransaction', function (Blueprint $table) {
            $table->increments('SalesTransactionID');
            $table->string('CustomerName', 100);
            $table->date('SalesTransactionDate');
            $table->unsignedInteger('StaffID');
            $table->foreign('StaffID')->references('StaffID')->on('Staff')->onDelete('cascade');
        });

        Schema::create('SalesItem', function (Blueprint $table) {
            $table->increments('SalesItemID');
            $table->integer('Quantity');
            $table->decimal('UnitPrice', 10, 2);
            $table->unsignedInteger('ProductID');
            $table->unsignedInteger('SalesTransactionID');
            $table->foreign('ProductID')->references('ProductID')->on('Product')->onDelete('cascade');
            $table->foreign('SalesTransactionID')->references('SalesTransactionID')->on('SalesTransaction')->onDelete('cascade');
        });

        Schema::create('Discount', function (Blueprint $table) {
            $table->increments('DiscountID');
            $table->string('DiscountRate', 20);
        });

        Schema::create('Billing', function (Blueprint $table) {
            $table->increments('BillingID');
            $table->string('CustomerName', 100);
            $table->string('VatApplied', 20);
            $table->decimal('BillingAmount', 10, 2);
            $table->date('BillingDate');
            $table->unsignedInteger('DiscountID');
            $table->unsignedInteger('SalesTransactionID');
            $table->foreign('DiscountID')->references('DiscountID')->on('Discount')->onDelete('cascade');
            $table->foreign('SalesTransactionID')->references('SalesTransactionID')->on('SalesTransaction')->onDelete('cascade');
        });

        Schema::create('Payment', function (Blueprint $table) {
            $table->increments('PaymentID');
            $table->decimal('PaymentAmount', 10, 2);
            $table->string('PaymentMethod', 50);
            $table->string('ReceiptNumber', 50);
            $table->unsignedInteger('BillingID');
            $table->foreign('BillingID')->references('BillingID')->on('Billing')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('Payment');
        Schema::dropIfExists('Billing');
        Schema::dropIfExists('Discount');
        Schema::dropIfExists('SalesItem');
        Schema::dropIfExists('SalesTransaction');
    }
};
