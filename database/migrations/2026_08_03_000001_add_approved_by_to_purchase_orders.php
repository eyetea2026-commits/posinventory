<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('PurchaseOrder', function (Blueprint $table) {
            $table->unsignedBigInteger('ApprovedBy')->nullable()->after('CreatedBy');
            $table->foreign('ApprovedBy')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('PurchaseOrder', function (Blueprint $table) {
            $table->dropForeign(['ApprovedBy']);
            $table->dropColumn('ApprovedBy');
        });
    }
};
