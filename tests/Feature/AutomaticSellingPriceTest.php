<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\SalesItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\StockBatch;
use App\Models\StockReceivingBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Automatic selling price with batch-level costing: every purchase batch
// has its own cost, and is priced from it at the product's Profit Margin:
//   Selling Price = Batch Unit Cost / (1 - Profit Margin)
// The POS charges each piece at the price of the batch it comes from (FIFO).
class AutomaticSellingPriceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private Supplier $supplier;

    private Category $category;

    private Product $camera;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);
        $this->supplier = Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0912-345-6789', 'Address' => 'N/A']);
        $this->category = Category::create(['CategoryName' => 'CCTV']);

        // ₱50 cost, ₱100 price: a 50% Profit Margin.
        $this->camera = Product::create(['ProductName' => 'Dome Camera', 'Model' => 'DC-1', 'Price' => 100, 'CostPrice' => 50, 'CategoryID' => $this->category->CategoryID, 'Barcode' => 'CCTVEX-000001']);
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

    private function sell(int $quantity, float $paid)
    {
        return $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $this->camera->ProductID, 'qty' => $quantity]],
            'payment_method' => 'cash', 'payment_amount' => $paid,
        ]);
    }

    /**
     * ₱60 batch (sells at ₱120), then a ₱70 batch (sells at ₱140).
     */
    private function receiveTwoBatches(): void
    {
        $this->receive(10, 60);
        $this->travel(1)->days();
        $this->receive(5, 70);
    }

    public function test_each_batch_is_priced_from_its_own_cost_at_the_products_margin(): void
    {
        $this->receiveTwoBatches();

        $batches = StockBatch::fifo()->get();
        $this->assertSame([120.0, 140.0], $batches->map(fn ($batch) => (float) $batch->SellingPrice)->all());
        $this->assertSame([50.0, 50.0], $batches->map(fn ($batch) => $batch->marginPercent())->all());

        // The product's price is the batch that sells next...
        $this->assertSame(120.0, (float) $this->camera->fresh()->Price);
        $this->assertSame([['quantity' => 10, 'price' => 120.0], ['quantity' => 5, 'price' => 140.0]], $this->camera->fresh()->priceTiers());

        // ...and moves to the next batch once that one is sold out.
        $this->sell(10, 1200)->assertOk();
        $this->assertSame(140.0, (float) $this->camera->fresh()->Price);
    }

    public function test_the_pos_charges_each_piece_at_its_batchs_price(): void
    {
        $this->receiveTwoBatches();

        // 12 x ₱120 isn't enough: 10 are ₱120 and 2 are ₱140.
        $this->sell(12, 1440)->assertStatus(400)->assertJsonPath('message', 'Payment amount must be greater than or equal to total.');

        $this->sell(12, 1480)->assertOk()->assertJsonPath('total', 1480);
        $lines = SalesItem::orderBy('SalesItemID')->get();
        $this->assertSame([[10, 120.0], [2, 140.0]], $lines->map(fn ($line) => [$line->Quantity, (float) $line->UnitPrice])->all());

        // Two lines on the receipt.
        $receipt = 'RCT-'.str_pad((string) $lines->first()->SalesTransactionID, 6, '0', STR_PAD_LEFT);
        $this->actingAs($this->cashier)->get(route('cashier.receipt', $receipt))->assertOk()
            ->assertSeeInOrder(['Dome Camera', '120.00', 'Dome Camera', '140.00']);
    }

    public function test_the_pos_page_lists_each_batchs_price(): void
    {
        $this->receiveTwoBatches();

        $this->actingAs($this->cashier)->get(route('cashier.pos'))->assertOk()
            ->assertSee('&quot;tiers&quot;:[{&quot;quantity&quot;:10,&quot;price&quot;:120', false)
            ->assertSee('{&quot;quantity&quot;:5,&quot;price&quot;:140', false)
            ->assertSee('function lineSlices(item)', false);
    }

    public function test_the_profit_margin_is_saved_and_the_selling_price_worked_out_from_it(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.products.store'), [
            'ProductName' => 'Bullet Camera', 'Model' => 'BC-1', 'Barcode' => 'CCTVEX-000002',
            'CostPrice' => 100, 'ProfitMargin' => 20, 'CategoryID' => $this->category->CategoryID,
        ])->assertCreated();

        $product = Product::where('Model', 'BC-1')->firstOrFail();
        $this->assertSame(20.0, (float) $product->ProfitMargin);
        $this->assertSame(125.0, (float) $product->Price);

        $this->actingAs($this->admin)->postJson(route('admin.products.store'), [
            'ProductName' => 'Box Camera', 'Model' => 'BX-1', 'Barcode' => 'CCTVEX-000003',
            'CostPrice' => 100, 'ProfitMargin' => 96, 'CategoryID' => $this->category->CategoryID,
        ])->assertStatus(422)->assertJsonValidationErrors(['ProfitMargin' => 'The Profit Margin can be at most 95%.']);
    }

    public function test_changing_the_margin_prices_the_stock_on_hand_again(): void
    {
        $this->receiveTwoBatches();

        $this->actingAs($this->admin)->putJson(route('admin.products.update', $this->camera), [
            'ProductName' => 'Dome Camera', 'Model' => 'DC-1', 'Barcode' => 'CCTVEX-000001',
            'CostPrice' => 50, 'ProfitMargin' => 60, 'CategoryID' => $this->category->CategoryID,
        ])->assertRedirect();

        // ₱60 / (1 - 60%) = ₱150; ₱70 / (1 - 60%) = ₱175.
        $this->assertSame([150.0, 175.0], StockBatch::fifo()->get()->map(fn ($batch) => (float) $batch->SellingPrice)->all());
        $this->assertSame(150.0, (float) $this->camera->fresh()->Price);
    }

    public function test_the_product_form_shows_the_selling_price_as_automatic(): void
    {
        $html = $this->actingAs($this->admin)->getJson(route('admin.products.edit', $this->camera))->assertOk()->json('html');

        $this->assertMatchesRegularExpression('/<input type="text" id="SellingPrice" class="form-input" value="100\.00" readonly tabindex="-1">/', $html);
        $this->assertStringContainsString('Automatic: Cost Price ÷ (1 − Profit Margin). Each stock batch is priced from its own purchase cost.', $html);
        $this->assertStringContainsString('name="ProfitMargin" id="ProfitMargin" class="value-input " value="50"', $html);
    }

    public function test_a_refund_pays_back_each_piece_at_the_price_it_sold_for(): void
    {
        $this->receiveTwoBatches();
        $this->sell(12, 1480)->assertOk();
        $saleId = SalesItem::value('SalesTransactionID');

        // The last pieces sold come back first: 2 at ₱140, then 1 at ₱120.
        $first = $this->returnRequest($saleId, 3);
        $this->assertSame(400.0, $first->refundableTotal());

        // A later request starts after those: 2 more at ₱120.
        $second = $this->returnRequest($saleId, 2);
        $this->assertSame(240.0, $second->refundableTotal());
    }

    private function returnRequest(int $saleId, int $quantity): SalesReturnItem
    {
        $salesReturn = SalesReturn::create([
            'SalesTransactionID' => $saleId, 'ReturnType' => SalesReturn::TYPE_REFUND, 'ReturnDate' => now()->toDateString(), 'Status' => SalesReturn::STATUS_PENDING,
        ]);

        return SalesReturnItem::create([
            'SalesReturnID' => $salesReturn->SalesReturnID, 'ProductID' => $this->camera->ProductID, 'Quantity' => $quantity,
            'UnitPrice' => 120, 'Reason' => 'Factory Defect',
        ]);
    }

    public function test_the_migration_keeps_each_products_margin_and_prices_its_stock(): void
    {
        $this->receiveTwoBatches();
        // As before: one typed price (₱100 at ₱50 cost), batches at it.
        StockBatch::query()->update(['SellingPrice' => 100]);
        Product::whereKey($this->camera->ProductID)->update(['Price' => 100]);

        Schema::table('Product', fn ($table) => $table->dropColumn('ProfitMargin'));
        (require database_path('migrations/2026_11_09_233507_add_profit_margin_to_product_table.php'))->up();

        $this->assertSame(50.0, (float) DB::table('Product')->value('ProfitMargin'));
        $this->assertSame([120.0, 140.0], StockBatch::fifo()->get()->map(fn ($batch) => (float) $batch->SellingPrice)->all());
        $this->assertSame(120.0, (float) DB::table('Product')->value('Price'));
    }

    public function test_the_migration_never_sets_a_negative_margin_and_can_run_again(): void
    {
        // A mistyped ₱423,423 receipt for a ₱280 adapter, no Cost Price.
        $this->receive(5, 423423);
        Product::whereKey($this->camera->ProductID)->update(['Price' => 280, 'CostPrice' => null]);

        // An earlier run stopped part-way: the column is already there.
        (require database_path('migrations/2026_11_09_233507_add_profit_margin_to_product_table.php'))->up();

        $this->assertSame(0.0, (float) DB::table('Product')->value('ProfitMargin'));
        $this->assertSame(423423.0, (float) StockBatch::value('SellingPrice'));
        $this->assertSame(423423.0, (float) DB::table('Product')->value('Price'));
    }

    public function test_a_new_product_costing_more_than_its_price_gets_a_zero_margin(): void
    {
        $product = Product::create(['ProductName' => 'Battery', 'Model' => 'BT-1', 'Price' => 200, 'CostPrice' => 34234, 'CategoryID' => $this->category->CategoryID, 'Barcode' => 'CCTVEX-000004']);

        $this->assertSame(0.0, (float) $product->fresh()->ProfitMargin);
    }
}
