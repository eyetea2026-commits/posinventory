<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->string('RefundMethod', 20)->nullable()->after('ApprovedBy');
            $table->decimal('RefundAmount', 10, 2)->nullable()->after('RefundMethod');
            $table->string('RefundAccountNumber', 50)->nullable()->after('RefundAmount');
            $table->date('RefundDate')->nullable()->after('RefundAccountNumber');
        });
    }

    public function down()
    {
        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->dropColumn(['RefundMethod', 'RefundAmount', 'RefundAccountNumber', 'RefundDate']);
        });
    }
};