<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('DamagedProduct', function (Blueprint $table) {
            $table->string('Status', 25)->default('pending')->after('Description');
            $table->unsignedInteger('PurchaseOrderID')->nullable()->after('SupplierID');
            $table->string('DamageType', 50)->nullable()->after('Status');
            $table->text('InspectionNotes')->nullable()->after('DamageType');
            $table->string('WarehouseLocation', 100)->nullable()->after('InspectionNotes');
            $table->text('Remarks')->nullable()->after('WarehouseLocation');
            $table->unsignedBigInteger('ResolvedBy')->nullable()->after('Remarks');
            $table->date('ResolvedDate')->nullable()->after('ResolvedBy');

            $table->foreign('PurchaseOrderID')->references('PurchaseOrderID')->on('PurchaseOrder')->nullOnDelete();
            $table->foreign('ResolvedBy')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('DamagedProduct', function (Blueprint $table) {
            $table->dropForeign(['PurchaseOrderID']);
            $table->dropForeign(['ResolvedBy']);
            $table->dropColumn([
                'Status',
                'PurchaseOrderID',
                'DamageType',
                'InspectionNotes',
                'WarehouseLocation',
                'Remarks',
                'ResolvedBy',
                'ResolvedDate',
            ]);
        });
    }
};
