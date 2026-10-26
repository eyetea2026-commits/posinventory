<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\StockReceivingBatch;
use App\Models\StockReceivingDelivery;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// A purchase order stays open when the supplier delivers fewer items than
// ordered: later deliveries add up (never past what's owed), until every
// line is in or the owner cancels what's left (the order is then Closed).
// Example: Camera 100/100, HDD 100/50, DVR 100/0 (supplier out of stock).
class PurchaseOrderPartialDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PurchaseOrder $po;

    private StockReceivingBatch $batch;

    /** @var array<string, PurchaseOrderItem> */
    private array $lines = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $supplier = Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0912-345-6789', 'Address' => 'N/A']);
        $category = Category::create(['CategoryName' => 'CCTV']);

        $this->po = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-000001', 'PurchaseDate' => now()->format('Y-m-d'),
            'Status' => PurchaseOrder::STATUS_DRAFT, 'SupplierID' => $supplier->SupplierID, 'CreatedBy' => $this->admin->id,
        ]);
        foreach (['Camera', 'HDD', 'DVR'] as $name) {
            $product = Product::create(['ProductName' => $name, 'Model' => $name.'-1', 'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID]);
            Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => 0, 'ReorderThreshold' => 10, 'Status' => 'Out of Stock']);
            $this->lines[$name] = PurchaseOrderItem::create(['PurchaseOrderID' => $this->po->PurchaseOrderID, 'ProductID' => $product->ProductID, 'Quantity' => 100, 'CostPriceAtOrder' => 600]);
        }

        // Printing sends the order to Stock Receiving.
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $this->po));
        $this->batch = StockReceivingBatch::where('PurchaseOrderID', $this->po->PurchaseOrderID)->firstOrFail();
    }

    /**
     * @param  array<string, int>  $quantities
     */
    private function receive(array $quantities, string $receipt)
    {
        return $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $this->batch), [
            'receipt_number' => $receipt,
            'items' => collect($this->lines)->map(fn ($line, $name) => ['purchase_order_item_id' => $line->PurchaseOrderItemID, 'quantity_received' => $quantities[$name] ?? 0])->values()->all(),
        ]);
    }

    private function stock(string $name): int
    {
        return (int) Inventory::where('ProductID', $this->lines[$name]->ProductID)->value('Quantity');
    }

    public function test_a_short_delivery_keeps_the_order_open_for_the_rest(): void
    {
        $this->receive(['Camera' => 100, 'HDD' => 50], 'DR-001')->assertSessionHas('success', 'Stock added to Inventory. The rest of this order is still expected, so it stays open in Pending / Expected Delivery.');

        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $this->po->fresh()->Status);
        $this->assertSame(StockReceivingBatch::STATUS_PENDING, $this->batch->fresh()->Status);
        $this->assertSame(50, $this->lines['HDD']->fresh()->remaining_quantity);

        // The second delivery adds to the first.
        $this->receive(['HDD' => 30], 'DR-002')->assertSessionMissing('error');
        $this->assertSame(80, $this->lines['HDD']->fresh()->ReceivedQuantity);
        $this->assertSame(80, $this->stock('HDD'));
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $this->po->fresh()->Status);

        // Both deliveries are kept, each with its own receipt number.
        $this->assertSame(['DR-001', 'DR-002'], StockReceivingDelivery::where('PurchaseOrderID', $this->po->PurchaseOrderID)->orderBy('StockReceivingDeliveryID')->pluck('ReceiptNumber')->all());
        $html = $this->actingAs($this->admin)->getJson(route('admin.stock-receivings.batches.show', $this->batch))->json('html');
        $this->assertStringContainsString('Delivery History', $html);
        $this->assertStringContainsString('DR-002', $html);
        $this->assertStringContainsString('Cancel Remaining Items', $html);
    }

    public function test_a_delivery_cannot_bring_more_than_is_still_owed(): void
    {
        $this->receive(['HDD' => 50], 'DR-001');

        $this->receive(['HDD' => 51], 'DR-002')->assertSessionHas('error', 'Only 50 more "HDD" can be received on this order (ordered 100, received 50 so far).');
        $this->assertSame(50, $this->stock('HDD'));

        // Nothing at all is not a delivery.
        $this->receive([], 'DR-003')->assertSessionHas('error', 'Enter how many of each product arrived in this delivery.');
        $this->assertSame(1, StockReceivingDelivery::count());
    }

    public function test_the_owner_cancels_what_is_left_and_the_order_closes(): void
    {
        $this->receive(['Camera' => 100, 'HDD' => 50], 'DR-001');

        // Cancel only the DVR (supplier out of stock): HDD is still owed, so the order stays open.
        $this->actingAs($this->admin)->post(route('admin.purchase-orders.cancel-remaining', $this->po), [
            'items' => [$this->lines['DVR']->PurchaseOrderItemID], 'reason' => 'Supplier out of stock',
        ])->assertSessionHas('success', 'Remaining items cancelled. The order stays open for the other products.');
        $this->assertSame(100, (int) $this->lines['DVR']->fresh()->CancelledQuantity);
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $this->po->fresh()->Status);

        // A DVR can no longer be received on this order.
        $this->receive(['DVR' => 1], 'DR-002')->assertSessionHas('error');

        // The rest of the HDD is cancelled too: nothing is owed, the order is Closed.
        $this->actingAs($this->admin)->post(route('admin.purchase-orders.cancel-remaining', $this->po), [
            'items' => [$this->lines['HDD']->PurchaseOrderItemID], 'reason' => 'Supplier discontinued it',
        ])->assertSessionHas('success', 'Remaining items cancelled. This purchase order is now Closed.');

        $po = $this->po->fresh();
        $this->assertSame(PurchaseOrder::STATUS_CLOSED, $po->Status);
        $this->assertSame('Supplier discontinued it', $po->RemainderCancelReason);
        $this->assertSame($this->admin->id, (int) $po->RemainderCancelledBy);
        $this->assertSame(StockReceivingBatch::STATUS_COMPLETED, $this->batch->fresh()->Status);
        $this->assertSame(['Camera' => 100, 'HDD' => 50, 'DVR' => 0], ['Camera' => $this->stock('Camera'), 'HDD' => $this->stock('HDD'), 'DVR' => $this->stock('DVR')]);

        $page = $this->actingAs($this->admin)->get(route('admin.purchase-orders.show', $this->po))->assertOk()->getContent();
        $this->assertStringContainsString('Closed', $page);
        $this->assertStringContainsString('Supplier discontinued it', $page);
        $this->assertStringContainsString('<th>Cancelled</th>', $page);
    }

    public function test_cancelling_the_remainder_needs_a_partly_received_order_a_product_and_a_reason(): void
    {
        // Nothing received yet: the whole order can be cancelled instead.
        $this->actingAs($this->admin)->post(route('admin.purchase-orders.cancel-remaining', $this->po), [
            'items' => [$this->lines['DVR']->PurchaseOrderItemID], 'reason' => 'x',
        ])->assertSessionHas('error', 'Only a partially received purchase order can have its remaining items cancelled.');

        $this->receive(['Camera' => 100], 'DR-001');
        $this->actingAs($this->admin)->post(route('admin.purchase-orders.cancel-remaining', $this->po), [])
            ->assertSessionHasErrors(['items', 'reason']);
        $this->assertSame(0, (int) $this->lines['DVR']->fresh()->CancelledQuantity);
    }
}
