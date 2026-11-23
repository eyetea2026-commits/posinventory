<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('Inventory', function (Blueprint $table) {
            $table->boolean('AutoReorderTriggered')->default(false)->after('ReorderThreshold');

            $table->unsignedInteger('LastRestockPurchaseOrderId')->nullable()->after('AutoReorderTriggered');
        });
    }

    public function down()
    {
        Schema::table('Inventory', function (Blueprint $table) {
            $table->dropColumn(['AutoReorderTriggered', 'LastRestockPurchaseOrderId']);
        });
    }
};
