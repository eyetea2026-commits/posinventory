<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('PurchaseOrderItem', function (Blueprint $table) {
            $table->integer('ReceivedQuantity')->default(0)->after('Quantity');
            $table->decimal('CostPriceAtOrder', 10, 2)->default(0)->after('ReceivedQuantity');
        });
    }

    public function down()
    {
        Schema::table('PurchaseOrderItem', function (Blueprint $table) {
            $table->dropColumn(['ReceivedQuantity', 'CostPriceAtOrder']);
        });
    }
};
