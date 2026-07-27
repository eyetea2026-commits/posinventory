<?php

use App\Models\PurchaseOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        // Backfill a real PO number for every pre-existing row from its
        // already-unique PK — no separate counter/sequence needed.
        PurchaseOrder::whereNull('PONumber')->get()->each(function (PurchaseOrder $po) {
            $year = \Illuminate\Support\Carbon::parse($po->PurchaseDate)->format('Y');
            $po->update(['PONumber' => "PO-{$year}-" . str_pad((string) $po->PurchaseOrderID, 6, '0', STR_PAD_LEFT)]);
        });

        // Stays nullable at the DB level (application code always sets it
        // immediately after insert, in the same request/transaction) — a
        // NOT NULL constraint here would reject the initial insert itself,
        // since PONumber is derived from the row's own auto-increment PK
        // and can't be known until after that insert happens.
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
