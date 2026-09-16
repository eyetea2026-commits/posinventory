<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\StockReceivingBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Covers the new Create -> Draft -> Print -> Pending -> Add to Inventory ->
// Completed workflow end to end, without disturbing the existing Approve/
// Submit/Cancel path or the legacy manual Stock Receiving flow (both keep
// their own full coverage in PurchaseOrderModuleTest.php).
class PurchaseOrderReceivingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Supplier $supplier;
    private Product $productA;
    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);

        $this->supplier = Supplier::create([
            'SupplierName' => 'Acme Supplies', 'ContactNumber' => '0000', 'Email' => 'acme@example.com', 'Address' => 'N/A',
        ]);
        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);

        $this->productA = Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01', 'SKU' => 'SKU-001',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $this->productA->ProductID, 'Quantity' => 5, 'ReorderThreshold' => 10, 'Status' => 'Low Stock']);

        $this->productB = Product::create([
            'ProductName' => 'NVR 8CH', 'Model' => 'NVR-08', 'SKU' => 'SKU-002',
            'Price' => 2000, 'CostPrice' => 1200, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $this->productB->ProductID, 'Quantity' => 2, 'ReorderThreshold' => 10, 'Status' => 'Low Stock']);
    }

    private function makeDraftOrder(): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-000001',
            'PurchaseDate' => now()->format('Y-m-d'),
            'Status' => PurchaseOrder::STATUS_DRAFT,
            'SupplierID' => $this->supplier->SupplierID,
            'CreatedBy' => $this->admin->id,
        ]);

        PurchaseOrderItem::create([
            'PurchaseOrderID' => $po->PurchaseOrderID, 'ProductID' => $this->productA->ProductID,
            'Quantity' => 50, 'CostPriceAtOrder' => 600,
        ]);
        PurchaseOrderItem::create([
            'PurchaseOrderID' => $po->PurchaseOrderID, 'ProductID' => $this->productB->ProductID,
            'Quantity' => 20, 'CostPriceAtOrder' => 1200,
        ]);

        return $po;
    }

    public function test_printing_a_draft_po_moves_it_to_pending_and_creates_a_batch(): void
    {
        $po = $this->makeDraftOrder();

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $response->assertOk();

        $po->refresh();
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $po->Status);

        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->first();
        $this->assertNotNull($batch);
        $this->assertSame(StockReceivingBatch::STATUS_PENDING, $batch->Status);
    }

    public function test_pending_po_no_longer_appears_in_the_active_purchase_order_list(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.index'));

        $response->assertOk();
        $response->assertDontSee($po->PONumber);
    }

    public function test_printed_po_appears_in_stock_receiving_pending_tab(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));

        $response = $this->actingAs($this->admin)->get(route('admin.stock-receivings.index'));

        $response->assertOk();
        $response->assertSee($po->PONumber);
        $response->assertSee('Pending / Expected Delivery');
    }

    public function test_printing_twice_does_not_create_a_second_batch(): void
    {
        $po = $this->makeDraftOrder();

        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));

        $this->assertSame(1, StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->count());
    }

    public function test_add_to_inventory_with_partial_quantity_only_adds_what_was_actually_received(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();

        $itemA = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productA->ProductID)->first();
        $itemB = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productB->ProductID)->first();

        $response = $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'items' => [
                ['purchase_order_item_id' => $itemA->PurchaseOrderItemID, 'quantity_received' => 48, 'receipt_number' => 'RCT-A1'],
                ['purchase_order_item_id' => $itemB->PurchaseOrderItemID, 'quantity_received' => 20, 'receipt_number' => 'RCT-B1'],
            ],
        ]);

        $response->assertRedirect(route('admin.stock-receivings.index'));

        // Ordered Quantity (the reference) is untouched; Inventory only
        // gained the actually-received amount, not the ordered amount.
        $itemA->refresh();
        $this->assertSame(50, $itemA->Quantity);
        $this->assertSame(48, $itemA->ReceivedQuantity);
        $this->assertSame('RCT-A1', $itemA->ReceiptNumber);
        $this->assertSame(5 + 48, Inventory::where('ProductID', $this->productA->ProductID)->value('Quantity'));

        $itemB->refresh();
        $this->assertSame(20, $itemB->Quantity);
        $this->assertSame(20, $itemB->ReceivedQuantity);
        $this->assertSame(2 + 20, Inventory::where('ProductID', $this->productB->ProductID)->value('Quantity'));

        $batch->refresh();
        $this->assertSame(StockReceivingBatch::STATUS_COMPLETED, $batch->Status);
        $this->assertNotNull($batch->CompletedAt);
        $this->assertSame($this->admin->id, $batch->ReceivedBy);

        // Product A came up short (48 of 50) so the PO is only Partially
        // Received, even though Product B arrived in full.
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $po->fresh()->Status);
    }

    public function test_add_to_inventory_in_full_marks_the_purchase_order_fully_received(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();

        $itemA = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productA->ProductID)->first();
        $itemB = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productB->ProductID)->first();

        $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'items' => [
                ['purchase_order_item_id' => $itemA->PurchaseOrderItemID, 'quantity_received' => 50, 'receipt_number' => 'RCT-A1'],
                ['purchase_order_item_id' => $itemB->PurchaseOrderItemID, 'quantity_received' => 20, 'receipt_number' => 'RCT-B1'],
            ],
        ]);

        $this->assertSame(PurchaseOrder::STATUS_FULLY_RECEIVED, $po->fresh()->Status);
    }

    public function test_completed_batch_moves_from_pending_to_completed_tab(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();

        $itemA = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productA->ProductID)->first();
        $itemB = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productB->ProductID)->first();

        $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'items' => [
                ['purchase_order_item_id' => $itemA->PurchaseOrderItemID, 'quantity_received' => 50],
                ['purchase_order_item_id' => $itemB->PurchaseOrderItemID, 'quantity_received' => 20],
            ],
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.stock-receivings.index'));

        $response->assertOk();
        // Present once (Completed tab) rather than twice (it must have
        // disappeared from the Pending tab's table).
        $response->assertSeeInOrder([$po->PONumber]);
        $this->assertSame(1, substr_count($response->getContent(), $po->PONumber));
    }

    public function test_add_to_inventory_rejects_a_quantity_above_what_was_ordered_and_changes_nothing(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();

        $itemA = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productA->ProductID)->first();
        $itemB = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productB->ProductID)->first();

        $inventoryABefore = Inventory::where('ProductID', $this->productA->ProductID)->value('Quantity');
        $inventoryBBefore = Inventory::where('ProductID', $this->productB->ProductID)->value('Quantity');

        $response = $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'items' => [
                // Over the ordered 50 -- must reject the whole batch, not
                // just this line, leaving Product B's otherwise-valid line
                // unapplied too.
                ['purchase_order_item_id' => $itemA->PurchaseOrderItemID, 'quantity_received' => 999],
                ['purchase_order_item_id' => $itemB->PurchaseOrderItemID, 'quantity_received' => 20],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $batch->refresh();
        $this->assertSame(StockReceivingBatch::STATUS_PENDING, $batch->Status);
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $po->fresh()->Status);
        $this->assertSame(0, $itemA->fresh()->ReceivedQuantity);
        $this->assertSame(0, $itemB->fresh()->ReceivedQuantity);
        $this->assertSame($inventoryABefore, Inventory::where('ProductID', $this->productA->ProductID)->value('Quantity'));
        $this->assertSame($inventoryBBefore, Inventory::where('ProductID', $this->productB->ProductID)->value('Quantity'));
    }

    public function test_completing_a_batch_twice_is_rejected_the_second_time(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();

        $itemA = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productA->ProductID)->first();
        $itemB = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productB->ProductID)->first();
        $payload = [
            'items' => [
                ['purchase_order_item_id' => $itemA->PurchaseOrderItemID, 'quantity_received' => 50],
                ['purchase_order_item_id' => $itemB->PurchaseOrderItemID, 'quantity_received' => 20],
            ],
        ];

        $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), $payload);
        $inventoryAAfterFirst = Inventory::where('ProductID', $this->productA->ProductID)->value('Quantity');

        $second = $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), $payload);

        $second->assertSessionHas('error');
        $this->assertSame($inventoryAAfterFirst, Inventory::where('ProductID', $this->productA->ProductID)->value('Quantity'));
    }

    public function test_view_details_shows_ordered_quantity_as_read_only_reference(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();

        $response = $this->actingAs($this->admin)->getJson(route('admin.stock-receivings.batches.show', $batch));

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('Purchase Order Quantity', $html);
        $this->assertStringContainsString('Quantity Received', $html);
        $this->assertStringContainsString('Receipt Number', $html);
        $this->assertStringContainsString($this->productA->ProductName, $html);
    }
}
