<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('PurchaseOrder', function (Blueprint $table) {
            $table->unsignedBigInteger('CreatedBy')->nullable()->after('SupplierID');
            $table->foreign('CreatedBy')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('PurchaseOrder', function (Blueprint $table) {
            $table->dropForeign(['CreatedBy']);
            $table->dropColumn('CreatedBy');
        });
    }
};
