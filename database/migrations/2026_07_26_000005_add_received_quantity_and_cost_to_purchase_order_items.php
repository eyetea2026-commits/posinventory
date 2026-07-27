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
            // Deliberately distinct from the old, removed "UnitPrice" column
            // (see 2026_07_11_000005_drop_unit_price_from_purchase_order_items.php):
            // that one was dropped because nothing ever populated it. This one
            // is populated automatically at PO-creation time and is a
            // historical snapshot the Supplier profile's spend analytics rely
            // on — summing today's live Product cost against historically
            // received quantities would misstate past spend.
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
