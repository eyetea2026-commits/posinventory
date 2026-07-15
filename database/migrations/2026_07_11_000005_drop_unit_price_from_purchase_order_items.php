<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('PurchaseOrderItem', function (Blueprint $table) {
            $table->dropColumn('UnitPrice');
        });
    }

    public function down()
    {
        Schema::table('PurchaseOrderItem', function (Blueprint $table) {
            $table->decimal('UnitPrice', 10, 2)->default(0);
        });
    }
};
