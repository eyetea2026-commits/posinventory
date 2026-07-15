<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('PurchaseOrder', function (Blueprint $table) {
            $table->date('ExpectedDeliveryDate')->nullable()->after('PurchaseDate');
        });
    }

    public function down()
    {
        Schema::table('PurchaseOrder', function (Blueprint $table) {
            $table->dropColumn('ExpectedDeliveryDate');
        });
    }
};
