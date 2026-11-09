<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each delivery line becomes a stock batch (panel suggestion):
 * - DamagedQuantity: arrived damaged (in the order line's unit). Counted as
 *   delivered on the order, never added to stock; it goes to the Damage
 *   module for return to the supplier.
 * - UnitCost / SellingPrice: per base unit, as they were when it arrived,
 *   so each batch's cost, selling price and margin can be followed.
 * Lines received before this get the order line's cost and the product's
 * current selling price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('StockReceivingDeliveryItem', function (Blueprint $table) {
            $table->integer('DamagedQuantity')->default(0)->after('Quantity');
            $table->decimal('UnitCost', 10, 2)->nullable()->after('BaseQuantity');
            $table->decimal('SellingPrice', 10, 2)->nullable()->after('UnitCost');
        });

        $lines = DB::table('StockReceivingDeliveryItem')
            ->join('PurchaseOrderItem', 'PurchaseOrderItem.PurchaseOrderItemID', '=', 'StockReceivingDeliveryItem.PurchaseOrderItemID')
            ->join('Product', 'Product.ProductID', '=', 'StockReceivingDeliveryItem.ProductID')
            ->select('StockReceivingDeliveryItem.StockReceivingDeliveryItemID', 'StockReceivingDeliveryItem.Quantity', 'StockReceivingDeliveryItem.BaseQuantity', 'PurchaseOrderItem.CostPriceAtOrder', 'PurchaseOrderItem.QuantityPerUnit', 'Product.Price')
            ->get();

        foreach ($lines as $line) {
            $perUnit = (float) $line->Quantity > 0 && (float) $line->BaseQuantity > 0
                ? (float) $line->BaseQuantity / (float) $line->Quantity
                : ((float) $line->QuantityPerUnit ?: 1);

            DB::table('StockReceivingDeliveryItem')
                ->where('StockReceivingDeliveryItemID', $line->StockReceivingDeliveryItemID)
                ->update([
                    'UnitCost' => $line->CostPriceAtOrder !== null ? round((float) $line->CostPriceAtOrder / $perUnit, 2) : null,
                    'SellingPrice' => $line->Price,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('StockReceivingDeliveryItem', function (Blueprint $table) {
            $table->dropColumn(['DamagedQuantity', 'UnitCost', 'SellingPrice']);
        });
    }
};
