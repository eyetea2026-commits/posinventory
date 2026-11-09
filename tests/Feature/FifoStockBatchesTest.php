<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DamagedProduct;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\SalesItem;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockBatchAllocation;
use App\Models\StockReceivingBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockBatches;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// FIFO with batch tracking and batch-level costing: every delivery is its
// own batch at its own cost; sales and removals take from the oldest batch
// first, so each sale line's cost (and profit) follows the batches it came
// out of, even when the same product was bought at different prices.
class FifoStockBatchesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private Supplier $supplier;

    private Product $camera;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);
        $this->supplier = Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0912-345-6789', 'Address' => 'N/A']);

        $category = Category::create(['CategoryName' => 'CCTV']);
        $this->camera = Product::create(['ProductName' => 'Dome Camera', 'Model' => 'DC-1', 'Price' => 100, 'CostPrice' => 50, 'CategoryID' => $category->CategoryID, 'Barcode' => 'CCTVEX-000001']);
        Inventory::create(['ProductID' => $this->camera->ProductID, 'Quantity' => 0, 'ReorderThreshold' => 10, 'Status' => 'Out of Stock']);
    }

    private function receive(int $quantity, float $cost): void
    {
        $order = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-'.str_pad((string) (PurchaseOrder::count() + 1), 6, '0', STR_PAD_LEFT), 'PurchaseDate' => now()->toDateString(),
            'Status' => PurchaseOrder::STATUS_DRAFT, 'SupplierID' => $this->supplier->SupplierID, 'CreatedBy' => $this->admin->id,
        ]);
        $line = PurchaseOrderItem::create(['PurchaseOrderID' => $order->PurchaseOrderID, 'ProductID' => $this->camera->ProductID, 'Quantity' => $quantity, 'CostPriceAtOrder' => $cost]);

        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $order));
        $receivingBatch = StockReceivingBatch::where('PurchaseOrderID', $order->PurchaseOrderID)->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $receivingBatch), [
            'receipt_number' => 'DR-'.$order->PurchaseOrderID,
            'items' => [['purchase_order_item_id' => $line->PurchaseOrderItemID, 'quantity_received' => $quantity]],
        ])->assertSessionMissing('error');
    }

    private function sell(int $quantity): SalesItem
    {
        $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $this->camera->ProductID, 'qty' => $quantity]],
            'payment_method' => 'cash', 'payment_amount' => 100 * $quantity,
        ])->assertOk();

        return SalesItem::latest('SalesItemID')->firstOrFail();
    }

    /**
     * @return list<int|float>
     */
    private function remainingPerBatch(): array
    {
        return StockBatch::where('ProductID', $this->camera->ProductID)->fifo()->get()->map(fn ($batch) => $batch->QuantityRemaining)->all();
    }

    public function test_each_delivery_is_its_own_batch_at_its_own_cost(): void
    {
        $this->receive(10, 60);
        $this->travel(1)->days();
        $this->receive(5, 70);

        $batches = StockBatch::where('ProductID', $this->camera->ProductID)->fifo()->get();
        $this->assertCount(2, $batches);
        $this->assertSame(StockBatch::SOURCE_PURCHASE_ORDER, $batches[0]->Source);
        $this->assertSame(10, $batches[0]->QuantityReceived);
        $this->assertSame(60.0, (float) $batches[0]->UnitCost);
        $this->assertSame(70.0, (float) $batches[1]->UnitCost);
        $this->assertSame($this->supplier->SupplierID, $batches[1]->SupplierID);
        $this->assertMatchesRegularExpression('/^BATCH-\d{6}$/', $batches[0]->BatchNumber);
    }

    public function test_a_sale_takes_from_the_oldest_batch_first_at_each_batchs_cost(): void
    {
        $this->receive(10, 60);
        $this->travel(1)->days();
        $this->receive(5, 70);

        // 12 = all 10 of the ₱60 batch + 2 of the ₱70 batch.
        $line = $this->sell(12);

        $this->assertSame([0, 3], $this->remainingPerBatch());
        $this->assertSame(740.0, (float) $line->fresh()->CostAmount);
        $this->assertSame(1200.0 - 740.0, $line->fresh()->grossProfit());

        $allocations = StockBatchAllocation::where('SalesItemID', $line->SalesItemID)->orderBy('StockBatchAllocationID')->get();
        $this->assertSame([10, 2], $allocations->pluck('Quantity')->all());
        $this->assertSame([60.0, 70.0], $allocations->map(fn ($allocation) => (float) $allocation->UnitCost)->all());
        $this->assertSame(StockBatchAllocation::REASON_SALE, $allocations[0]->Reason);
    }

    public function test_stock_already_on_hand_becomes_an_opening_stock_batch_at_the_cost_price(): void
    {
        Inventory::where('ProductID', $this->camera->ProductID)->first()->update(['Quantity' => 4]);

        $batch = StockBatch::where('ProductID', $this->camera->ProductID)->sole();
        $this->assertSame(StockBatch::SOURCE_STOCK_ADDED, $batch->Source);
        $this->assertSame(4, $batch->QuantityRemaining);
        $this->assertSame(50.0, (float) $batch->UnitCost);
    }

    public function test_a_stock_adjustment_removes_from_the_oldest_batch(): void
    {
        $this->receive(3, 60);
        $this->travel(1)->days();
        $this->receive(5, 70);

        $this->actingAs($this->admin)->post(route('admin.stock-adjustments.store'), [
            'ProductID' => $this->camera->ProductID, 'QuantityAdjust' => 4, 'Reason' => StockAdjustment::REASON_LOST, 'Date' => now()->format('Y-m-d'),
        ])->assertSessionHasNoErrors();

        $this->assertSame([0, 4], $this->remainingPerBatch());
        $adjustmentId = StockAdjustment::firstOrFail()->AdjustmentID;
        $this->assertSame(2, StockBatchAllocation::where('StockAdjustmentID', $adjustmentId)->where('Reason', StockBatchAllocation::REASON_STOCK_ADJUSTMENT)->count());
    }

    public function test_a_restocked_refund_goes_back_onto_the_batch_it_was_sold_from(): void
    {
        $this->receive(2, 60);
        $this->travel(1)->days();
        $this->receive(5, 70);
        $line = $this->sell(3);
        $this->assertSame([0, 4], $this->remainingPerBatch());

        // One piece refunded back to the shelf: back onto the ₱70 batch
        // (the last one this sale took from), not a new batch.
        StockBatches::returningFrom($line->SalesItemID, function () {
            $inventory = Inventory::where('ProductID', $this->camera->ProductID)->first();
            $inventory->update(['Quantity' => $inventory->Quantity + 1]);
        });

        $this->assertSame([0, 5], $this->remainingPerBatch());
        $this->assertSame(1, StockBatchAllocation::where('SalesItemID', $line->SalesItemID)->orderByDesc('StockBatchAllocationID')->first()->ReturnedQuantity);
    }

    public function test_a_supplier_replacement_comes_in_at_the_damaged_pieces_cost(): void
    {
        $this->receive(2, 60);
        $order = PurchaseOrder::firstOrFail();
        $damage = DamagedProduct::create([
            'ProductID' => $this->camera->ProductID, 'PurchaseOrderID' => $order->PurchaseOrderID, 'SupplierID' => $this->supplier->SupplierID,
            'SourceModule' => DamagedProduct::SOURCE_STOCK_RECEIVING, 'Quantity' => 1, 'Description' => 'Damaged on arrival',
            'DateRecorded' => now(), 'DamageType' => 'damaged_product', 'Status' => DamagedProduct::STATUS_RETURNED_TO_SUPPLIER,
        ]);

        $this->actingAs($this->admin)->post(route('admin.damages.receive-replacement', $damage))->assertSessionHas('success');

        $replacement = StockBatch::where('Source', StockBatch::SOURCE_SUPPLIER_REPLACEMENT)->sole();
        $this->assertSame(1, $replacement->QuantityRemaining);
        $this->assertSame(60.0, (float) $replacement->UnitCost);
        $this->assertSame($damage->DamageID, $replacement->DamageID);
    }

    public function test_the_batches_always_add_up_to_the_stock_on_hand(): void
    {
        $this->receive(10, 60);
        $this->sell(4);
        $this->travel(1)->days();
        $this->receive(6, 65);
        $this->sell(7);

        $this->assertSame(5, Inventory::where('ProductID', $this->camera->ProductID)->value('Quantity') + 0);
        $this->assertSame(5.0, (float) StockBatch::where('ProductID', $this->camera->ProductID)->sum('QuantityRemaining'));
    }

    public function test_inventory_details_list_the_batches_in_fifo_order(): void
    {
        $this->receive(10, 60);
        $this->travel(1)->days();
        $this->receive(5, 70);
        $this->sell(10);

        $html = $this->actingAs($this->admin)->get(route('admin.inventory.show', $this->camera))->assertOk()->getContent();

        $this->assertStringContainsString('Stock Batches (FIFO)', $html);
        [$first, $second] = StockBatch::fifo()->pluck('BatchNumber')->all();
        $this->assertLessThan(strpos($html, $second), strpos($html, $first));
        // The ₱60 batch is used up; the ₱70 one sells next.
        $this->assertMatchesRegularExpression('/'.$second.'<\/code>\s*<br><small class="batch-next">Sells next<\/small>/', $html);
        // Profit: 10 sold at ₱100 from the ₱60 batch.
        $this->assertStringContainsString('₱400.00', $html);
        // Stock value: 5 left at ₱70.
        $this->assertStringContainsString('₱350.00', $html);
    }

    public function test_the_sales_report_summary_has_the_fifo_cost_and_gross_profit(): void
    {
        $this->receive(10, 60);
        $this->travel(1)->days();
        $this->receive(5, 70);
        $this->sell(12);

        $this->actingAs($this->admin)->get(route('admin.reports.print', ['type' => 'sales']))->assertOk()
            ->assertSeeInOrder(['Cost of Goods Sold (FIFO)', '₱740.00', 'Gross Profit', '₱460.00']);
    }

    public function test_the_migration_splits_the_stock_on_hand_over_the_latest_deliveries(): void
    {
        $this->receive(10, 60);
        $this->travel(1)->days();
        $this->receive(5, 70);
        $this->sell(3);

        // As if the stock had been kept without batches: 18 on hand, but
        // only 15 ever delivered, so 3 predate every recorded delivery.
        Inventory::where('ProductID', $this->camera->ProductID)->update(['Quantity' => 18]);
        $migration = require database_path('migrations/2026_11_09_231513_create_stock_batch_tables.php');
        Schema::table('SalesItem', fn ($table) => $table->dropColumn('CostAmount'));
        Schema::drop('StockBatchAllocation');
        Schema::drop('StockBatch');
        $migration->up();
        $this->assertSame(['Opening Stock', 'Purchase Order'], DB::table('StockBatch')->where('QuantityRemaining', '>', 0)->orderBy('ReceivedAt')->limit(2)->pluck('Source')->all());

        $batches = DB::table('StockBatch')->orderBy('ReceivedAt')->orderBy('StockBatchID')->get();
        $this->assertSame(['Opening Stock', 'Purchase Order', 'Purchase Order'], $batches->pluck('Source')->all());
        // What is left is the latest deliveries; the oldest stock sells first.
        $this->assertSame([3.0, 10.0, 5.0], $batches->map(fn ($batch) => (float) $batch->QuantityRemaining)->all());
        $this->assertSame([50.0, 60.0, 70.0], $batches->map(fn ($batch) => (float) $batch->UnitCost)->all());
        // Earlier sales get the product's Cost Price as their cost.
        $this->assertSame(150.0, (float) DB::table('SalesItem')->value('CostAmount'));
    }
}
