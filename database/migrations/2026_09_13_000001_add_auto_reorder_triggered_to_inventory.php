<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('Inventory', function (Blueprint $table) {
            // Tracks whether the CURRENT low-stock cycle has already produced
            // an automatic Draft PO, so repeated inventory checks (page
            // refresh, live poll, re-login) don't create duplicates. Reset to
            // false once the product is restocked above its reorder
            // threshold, allowing exactly one new automatic PO per future
            // low-stock cycle.
            $table->boolean('AutoReorderTriggered')->default(false)->after('ReorderThreshold');

            // The highest PurchaseOrderID that existed the moment this
            // product was last restocked back above its reorder threshold
            // (0/null if never restocked while tracked). Used only to scope
            // the "does an open PO already cover this product" duplicate
            // check to the CURRENT low-stock cycle — without it, an old
            // unactioned draft from a cycle already closed out by a restock
            // would incorrectly block a brand new automatic draft for a
            // later, separate drop. An ID watermark (rather than a
            // timestamp) sidesteps same-second ordering ambiguity.
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
