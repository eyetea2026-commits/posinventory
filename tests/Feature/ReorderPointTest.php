<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\StockReceivingBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ReorderPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The Re-Order Point is forecast from the product's own sales and its
// supplier's delivery time, never below 10:
//   Re-Order Point = (Average Daily Sales x Average Lead Time) + Safety Stock
//   Safety Stock   = (Max Daily Sales x Max Lead Time) - (Average Daily Sales x Average Lead Time)
class ReorderPointTest extends TestCase
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
        $this->camera = Product::create(['ProductName' => 'Dome Camera', 'Model' => 'DC-1', 'Price' => 100, 'CostPrice' => 60, 'CategoryID' => $category->CategoryID, 'Barcode' => 'CCTVEX-000001']);
        Inventory::create(['ProductID' => $this->camera->ProductID, 'Quantity' => 200, 'ReorderThreshold' => 10, 'Status' => 'Available']);
    }

    private function sell(int $quantity, int $daysAgo = 0): void
    {
        $this->travelTo(now()->subDays($daysAgo));
        $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $this->camera->ProductID, 'qty' => $quantity]],
            'payment_method' => 'cash', 'payment_amount' => 100 * $quantity,
        ])->assertOk();
        $this->travelBack();
    }

    /**
     * Ordered $daysToDeliver days ago, delivered today.
     */
    private function receiveOrderPlaced(int $daysToDeliver): void
    {
        $order = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-'.str_pad((string) (PurchaseOrder::count() + 1), 6, '0', STR_PAD_LEFT),
            'PurchaseDate' => now()->subDays($daysToDeliver)->toDateString(),
            'Status' => PurchaseOrder::STATUS_DRAFT, 'SupplierID' => $this->supplier->SupplierID, 'CreatedBy' => $this->admin->id,
        ]);
        $line = PurchaseOrderItem::create(['PurchaseOrderID' => $order->PurchaseOrderID, 'ProductID' => $this->camera->ProductID, 'Quantity' => 10, 'CostPriceAtOrder' => 60]);

        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $order));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $order->PurchaseOrderID)->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'receipt_number' => 'DR-'.$order->PurchaseOrderID,
            'items' => [['purchase_order_item_id' => $line->PurchaseOrderItemID, 'quantity_received' => 10]],
        ])->assertSessionMissing('error');
    }

    private function storedReorderPoint(): int
    {
        return (int) Inventory::where('ProductID', $this->camera->ProductID)->value('ReorderThreshold');
    }

    public function test_a_product_with_no_sales_has_the_minimum_reorder_point(): void
    {
        $result = ReorderPoint::forProduct($this->camera->ProductID);

        $this->assertSame(0.0, $result['average_daily_sales']);
        $this->assertTrue($result['lead_time_is_default']);
        $this->assertSame(ReorderPoint::DEFAULT_LEAD_TIME_DAYS, $result['max_lead_time']);
        $this->assertSame(0, $result['computed']);
        $this->assertSame(10, $result['reorder_point']);
    }

    public function test_the_reorder_point_follows_the_sales_and_the_lead_time(): void
    {
        // Lead time: 5 days and 3 days (average 4, max 5).
        $this->receiveOrderPlaced(5);
        $this->receiveOrderPlaced(3);

        // 30 sold in the last 30 days (the best day: 12); older sales don't count.
        $this->sell(20, 31);
        $this->sell(12, 10);
        $this->sell(6, 5);
        $this->sell(8);
        $this->sell(4);

        $result = ReorderPoint::forProduct($this->camera->ProductID);
        $this->assertSame(30.0, $result['units_sold']);
        $this->assertSame(1.0, $result['average_daily_sales']);
        $this->assertSame(12.0, $result['max_daily_sales']);
        $this->assertSame(4.0, $result['average_lead_time']);
        $this->assertSame(5, $result['max_lead_time']);
        $this->assertSame(2, $result['lead_time_orders']);
        // (1 x 4) + ((12 x 5) - (1 x 4)) = 4 + 56 = 60
        $this->assertSame(4.0, $result['demand_during_lead_time']);
        $this->assertSame(56.0, $result['safety_stock']);
        $this->assertSame(60, $result['reorder_point']);

        // Saved by the sale itself, and the stock status follows it.
        $this->assertSame(60, $this->storedReorderPoint());
        $this->assertSame(170, (int) Inventory::where('ProductID', $this->camera->ProductID)->value('Quantity'));
        $this->assertSame('Available', Inventory::where('ProductID', $this->camera->ProductID)->value('Status'));

        // The Inventory details show how it was worked out.
        $html = $this->actingAs($this->admin)->getJson(route('admin.inventory.show', $this->camera))->assertOk()->json('html');
        $this->assertStringContainsString('Re-Order Point (Forecast)', $html);
        $this->assertStringContainsString('(12.00 × 5) − (1.00 × 4.0) = 56.00', $html);
        $this->assertStringContainsString('(1.00 × 4.0) + 56.00 = 60', $html);
    }

    public function test_selling_more_raises_it_to_low_stock(): void
    {
        Inventory::where('ProductID', $this->camera->ProductID)->update(['Quantity' => 50]);

        // 12 sold today, no deliveries yet (7 days assumed): 12 x 7 = 84.
        $this->sell(12);

        $this->assertSame(84, $this->storedReorderPoint());
        $this->assertSame('Low Stock', Inventory::where('ProductID', $this->camera->ProductID)->value('Status'));
    }

    public function test_the_first_visit_of_a_day_brings_it_back_down_once_sales_stop(): void
    {
        $this->sell(12);
        $this->assertSame(84, $this->storedReorderPoint());

        // A month later nothing sold in the last 30 days: back to the minimum.
        $this->travel(31)->days();
        $this->actingAs($this->admin)->get(route('admin.inventory.index'))->assertOk();
        $this->assertSame(10, $this->storedReorderPoint());
    }

    public function test_the_refresh_command_recomputes_every_product(): void
    {
        Inventory::where('ProductID', $this->camera->ProductID)->update(['ReorderThreshold' => 50]);

        $this->artisan('reorder-points:refresh')->assertSuccessful();

        $this->assertSame(10, $this->storedReorderPoint());
    }
}
