<?php

use App\Models\PurchaseOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('PurchaseOrder', function (Blueprint $table) {
            $table->string('PONumber', 20)->nullable()->after('PurchaseOrderID');
            $table->text('Notes')->nullable()->after('ExpectedDeliveryDate');
            $table->string('Status', 30)->default('pending')->change();
        });

        PurchaseOrder::whereNull('PONumber')->get()->each(function (PurchaseOrder $po) {
            $year = Carbon::parse($po->PurchaseDate)->format('Y');
            $po->update(['PONumber' => "PO-{$year}-".str_pad((string) $po->PurchaseOrderID, 6, '0', STR_PAD_LEFT)]);
        });

        Schema::table('PurchaseOrder', function (Blueprint $table) {
            $table->unique('PONumber');
        });
    }

    public function down()
    {
        Schema::table('PurchaseOrder', function (Blueprint $table) {
            $table->dropUnique(['PONumber']);
            $table->dropColumn(['PONumber', 'Notes']);
        });
    }
};
