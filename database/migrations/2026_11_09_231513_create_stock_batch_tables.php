<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIFO with batch tracking and batch-level costing (panel suggestion).
 *
 * - StockBatch: every lot of stock that came in (a delivery line, an older
 *   Stock Receiving entry, a supplier replacement, the stock already on
 *   hand), with its own unit cost, selling price and what is left of it.
 * - StockBatchAllocation: which batches each sale / stock removal took
 *   from, oldest batch first, at that batch's cost.
 * - SalesItem.CostAmount: the line's cost of goods sold (FIFO).
 *
 * The stock on hand is split over the batches the FIFO way: what is left
 * is the most recent deliveries (older ones were sold first). Stock older
 * than every recorded delivery becomes one "Opening Stock" batch at the
 * product's Cost Price. Sales made before this get the product's current
 * Cost Price as their cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('StockBatch', function (Blueprint $table) {
            $table->increments('StockBatchID');
            // BATCH-000001, filled in from the id right after insert.
            $table->string('BatchNumber', 20)->nullable()->unique();
            $table->unsignedInteger('ProductID');
            $table->string('Source', 40);
            $table->unsignedInteger('SupplierID')->nullable();
            $table->unsignedInteger('PurchaseOrderID')->nullable();
            $table->unsignedInteger('StockReceivingDeliveryItemID')->nullable();
            $table->unsignedInteger('ReceivingID')->nullable();
            $table->unsignedInteger('DamageID')->nullable();
            $table->string('ReceiptNumber', 50)->nullable();
            $table->dateTime('ReceivedAt');
            // In the product's base unit (pieces, meters).
            $table->decimal('QuantityReceived', 12, 2);
            $table->decimal('QuantityRemaining', 12, 2);
            // Per base unit.
            $table->decimal('UnitCost', 10, 2)->default(0);
            $table->decimal('SellingPrice', 10, 2)->nullable();
            $table->timestamps();

            $table->foreign('ProductID')->references('ProductID')->on('Product')->onDelete('cascade');
            $table->index(['ProductID', 'ReceivedAt']);
        });

        Schema::create('StockBatchAllocation', function (Blueprint $table) {
            $table->increments('StockBatchAllocationID');
            $table->unsignedInteger('StockBatchID');
            $table->unsignedInteger('ProductID');
            $table->string('Reason', 40);
            $table->unsignedInteger('SalesItemID')->nullable()->index();
            $table->unsignedInteger('StockAdjustmentID')->nullable()->index();
            $table->decimal('Quantity', 12, 2);
            // Put back on the batch by a refund that restocked it.
            $table->decimal('ReturnedQuantity', 12, 2)->default(0);
            $table->decimal('UnitCost', 10, 2)->default(0);
            $table->timestamps();

            $table->foreign('StockBatchID')->references('StockBatchID')->on('StockBatch')->onDelete('cascade');
        });

        Schema::table('SalesItem', function (Blueprint $table) {
            $table->decimal('CostAmount', 12, 2)->nullable()->after('DiscountAmount');
        });

        $this->backfillBatches();

        DB::table('SalesItem')
            ->whereNull('CostAmount')
            ->update(['CostAmount' => DB::raw('ROUND(BaseQuantity * COALESCE((SELECT CostPrice FROM Product WHERE Product.ProductID = SalesItem.ProductID), 0), 2)')]);
    }

    private function backfillBatches(): void
    {
        $products = DB::table('Product')
            ->leftJoin('Inventory', 'Inventory.ProductID', '=', 'Product.ProductID')
            ->select('Product.ProductID', 'Product.CostPrice', 'Product.Price', 'Inventory.Quantity')
            ->get();

        foreach ($products as $product) {
            $receipts = $this->receiptsOf($product);
            $left = max(0, round((float) $product->Quantity, 2));

            // Newest first: the stock still on hand is the latest deliveries.
            foreach (array_reverse(array_keys($receipts)) as $index) {
                $receipts[$index]['QuantityRemaining'] = min($receipts[$index]['QuantityReceived'], $left);
                $left = round($left - $receipts[$index]['QuantityRemaining'], 2);
            }

            if ($left > 0) {
                // Just before the first recorded delivery, so it sells first.
                array_unshift($receipts, [
                    'Source' => 'Opening Stock',
                    'ReceivedAt' => isset($receipts[0]) ? Carbon::parse($receipts[0]['ReceivedAt'])->subSecond()->toDateTimeString() : now()->toDateTimeString(),
                    'QuantityReceived' => $left,
                    'QuantityRemaining' => $left,
                    'UnitCost' => round((float) $product->CostPrice, 2),
                    'SellingPrice' => $product->Price,
                ]);
            }

            foreach ($receipts as $receipt) {
                $id = DB::table('StockBatch')->insertGetId($receipt + [
                    'ProductID' => $product->ProductID,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('StockBatch')->where('StockBatchID', $id)->update(['BatchNumber' => 'BATCH-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT)]);
            }
        }
    }

    /**
     * Every recorded delivery of the product, oldest first.
     *
     * @return list<array<string, mixed>>
     */
    private function receiptsOf(object $product): array
    {
        $deliveries = DB::table('StockReceivingDeliveryItem')
            ->join('StockReceivingDelivery', 'StockReceivingDelivery.StockReceivingDeliveryID', '=', 'StockReceivingDeliveryItem.StockReceivingDeliveryID')
            ->leftJoin('PurchaseOrder', 'PurchaseOrder.PurchaseOrderID', '=', 'StockReceivingDelivery.PurchaseOrderID')
            ->where('StockReceivingDeliveryItem.ProductID', $product->ProductID)
            ->where('StockReceivingDeliveryItem.BaseQuantity', '>', 0)
            ->get([
                'StockReceivingDeliveryItem.StockReceivingDeliveryItemID', 'StockReceivingDeliveryItem.BaseQuantity', 'StockReceivingDeliveryItem.UnitCost',
                'StockReceivingDeliveryItem.SellingPrice', 'StockReceivingDelivery.ReceivedAt', 'StockReceivingDelivery.ReceiptNumber',
                'StockReceivingDelivery.PurchaseOrderID', 'PurchaseOrder.SupplierID',
            ])
            ->map(fn ($row) => [
                'Source' => 'Purchase Order',
                'SupplierID' => $row->SupplierID,
                'PurchaseOrderID' => $row->PurchaseOrderID,
                'StockReceivingDeliveryItemID' => $row->StockReceivingDeliveryItemID,
                'ReceiptNumber' => $row->ReceiptNumber,
                'ReceivedAt' => $row->ReceivedAt ?: now()->toDateTimeString(),
                'QuantityReceived' => round((float) $row->BaseQuantity, 2),
                'UnitCost' => round((float) ($row->UnitCost ?? $product->CostPrice), 2),
                'SellingPrice' => $row->SellingPrice ?? $product->Price,
            ]);

        $older = DB::table('StockReceiving')
            ->leftJoin('PurchaseOrderItem', 'PurchaseOrderItem.PurchaseOrderItemID', '=', 'StockReceiving.PurchaseOrderItemID')
            ->where('StockReceiving.ProductID', $product->ProductID)
            ->where('StockReceiving.Quantity', '>', 0)
            ->get([
                'StockReceiving.ReceivingID', 'StockReceiving.Quantity', 'StockReceiving.DateReceived', 'StockReceiving.ReceiptNumber',
                'StockReceiving.SupplierID', 'StockReceiving.PurchaseOrderID', 'PurchaseOrderItem.CostPriceAtOrder', 'PurchaseOrderItem.QuantityPerUnit',
            ])
            ->map(function ($row) use ($product) {
                $perUnit = (float) ($row->QuantityPerUnit ?? 0) ?: 1;

                return [
                    'Source' => 'Stock Receiving',
                    'SupplierID' => $row->SupplierID,
                    'PurchaseOrderID' => $row->PurchaseOrderID,
                    'ReceivingID' => $row->ReceivingID,
                    'ReceiptNumber' => $row->ReceiptNumber,
                    'ReceivedAt' => $row->DateReceived.' 00:00:00',
                    'QuantityReceived' => round((float) $row->Quantity * $perUnit, 2),
                    'UnitCost' => round($row->CostPriceAtOrder !== null ? (float) $row->CostPriceAtOrder / $perUnit : (float) $product->CostPrice, 2),
                    'SellingPrice' => $product->Price,
                ];
            });

        return $deliveries->concat($older)->sortBy('ReceivedAt')->values()->all();
    }

    public function down(): void
    {
        Schema::table('SalesItem', function (Blueprint $table) {
            $table->dropColumn('CostAmount');
        });
        Schema::dropIfExists('StockBatchAllocation');
        Schema::dropIfExists('StockBatch');
    }
};
