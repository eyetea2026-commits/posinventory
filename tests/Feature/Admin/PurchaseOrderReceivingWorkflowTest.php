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
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $this->productA->ProductID, 'Quantity' => 5, 'ReorderThreshold' => 10, 'Status' => 'Low Stock']);

        $this->productB = Product::create([
            'ProductName' => 'NVR 8CH', 'Model' => 'NVR-08',
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
        $firstBatchId = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->value('StockReceivingBatchID');

        // Print two more times for good measure -- covers both "already
        // Pending" (this PO's actual state right now) and repeated clicks
        // in general.
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));

        $this->assertSame(1, StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->count());
        $this->assertSame($firstBatchId, StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->value('StockReceivingBatchID'));
        $this->assertSame(1, PurchaseOrder::where('PurchaseOrderID', $po->PurchaseOrderID)->count());
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $po->fresh()->Status);
    }

    // Explicitly covers the "already Completed" case: printing a PO whose
    // batch has already been completed (Add to Inventory already ran) must
    // not create a second batch, must not touch Inventory again, and must
    // not change the PO's own (now Partially/Fully Received) status.
    public function test_printing_a_po_whose_batch_is_already_completed_changes_nothing(): void
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

        $statusAfterCompletion = $po->fresh()->Status;
        $inventoryAAfterCompletion = Inventory::where('ProductID', $this->productA->ProductID)->value('Quantity');
        $inventoryBAfterCompletion = Inventory::where('ProductID', $this->productB->ProductID)->value('Quantity');

        // Printing again now that the batch is Completed and the PO has
        // moved on to Fully Received — must be a total no-op.
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));

        $this->assertSame(1, StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->count());
        $this->assertSame(StockReceivingBatch::STATUS_COMPLETED, $batch->fresh()->Status);
        $this->assertSame($statusAfterCompletion, $po->fresh()->Status);
        $this->assertSame($inventoryAAfterCompletion, Inventory::where('ProductID', $this->productA->ProductID)->value('Quantity'));
        $this->assertSame($inventoryBAfterCompletion, Inventory::where('ProductID', $this->productB->ProductID)->value('Quantity'));
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

    public function test_add_to_inventory_rejects_a_receipt_number_duplicated_within_the_same_submission(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();

        $itemA = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productA->ProductID)->first();
        $itemB = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productB->ProductID)->first();

        $response = $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'items' => [
                ['purchase_order_item_id' => $itemA->PurchaseOrderItemID, 'quantity_received' => 50, 'receipt_number' => 'RCT-SAME'],
                ['purchase_order_item_id' => $itemB->PurchaseOrderItemID, 'quantity_received' => 20, 'receipt_number' => 'RCT-SAME'],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $batch->refresh();
        $this->assertSame(StockReceivingBatch::STATUS_PENDING, $batch->Status);
        $this->assertSame(0, $itemA->fresh()->ReceivedQuantity);
        $this->assertNull($itemA->fresh()->ReceiptNumber);
    }

    public function test_add_to_inventory_rejects_a_receipt_number_already_used_by_another_delivery(): void
    {
        $po1 = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po1));
        $batch1 = StockReceivingBatch::where('PurchaseOrderID', $po1->PurchaseOrderID)->firstOrFail();
        $item1A = PurchaseOrderItem::where('PurchaseOrderID', $po1->PurchaseOrderID)->where('ProductID', $this->productA->ProductID)->first();
        $item1B = PurchaseOrderItem::where('PurchaseOrderID', $po1->PurchaseOrderID)->where('ProductID', $this->productB->ProductID)->first();

        $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch1), [
            'items' => [
                ['purchase_order_item_id' => $item1A->PurchaseOrderItemID, 'quantity_received' => 50, 'receipt_number' => 'RCT-DUPE'],
                ['purchase_order_item_id' => $item1B->PurchaseOrderItemID, 'quantity_received' => 20],
            ],
        ]);

        // A second, separate PO/batch tries to reuse the same receipt number.
        $po2 = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-000002',
            'PurchaseDate' => now()->format('Y-m-d'),
            'Status' => PurchaseOrder::STATUS_DRAFT,
            'SupplierID' => $this->supplier->SupplierID,
            'CreatedBy' => $this->admin->id,
        ]);
        $item2A = PurchaseOrderItem::create([
            'PurchaseOrderID' => $po2->PurchaseOrderID, 'ProductID' => $this->productA->ProductID,
            'Quantity' => 10, 'CostPriceAtOrder' => 600,
        ]);
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po2));
        $batch2 = StockReceivingBatch::where('PurchaseOrderID', $po2->PurchaseOrderID)->firstOrFail();

        $response = $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch2), [
            'items' => [
                ['purchase_order_item_id' => $item2A->PurchaseOrderItemID, 'quantity_received' => 10, 'receipt_number' => 'RCT-DUPE'],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $batch2->refresh();
        $this->assertSame(StockReceivingBatch::STATUS_PENDING, $batch2->Status);
        $this->assertSame(0, $item2A->fresh()->ReceivedQuantity);
    }

    // ---- Live "already used" check (check-receipt-number) ----

    public function test_check_receipt_number_reports_unused_for_a_fresh_value(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('admin.stock-receivings.check-receipt-number'), [
            'receipt_number' => 'RCT-NEW',
        ]);

        $response->assertOk();
        $response->assertJson(['used' => false]);
    }

    public function test_check_receipt_number_detects_one_already_used_on_a_purchase_order_item(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();
        $itemA = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productA->ProductID)->first();
        $itemB = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productB->ProductID)->first();

        $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'items' => [
                ['purchase_order_item_id' => $itemA->PurchaseOrderItemID, 'quantity_received' => 50, 'receipt_number' => 'RCT-TAKEN'],
                ['purchase_order_item_id' => $itemB->PurchaseOrderItemID, 'quantity_received' => 20],
            ],
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('admin.stock-receivings.check-receipt-number'), [
            'receipt_number' => 'RCT-TAKEN',
        ]);

        $response->assertOk();
        $response->assertJson(['used' => true]);
    }

    public function test_check_receipt_number_excludes_the_items_own_current_value(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();
        $itemA = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productA->ProductID)->first();

        $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'items' => [
                ['purchase_order_item_id' => $itemA->PurchaseOrderItemID, 'quantity_received' => 30, 'receipt_number' => 'RCT-SELF'],
            ],
        ]);

        // Re-checking the value already saved on this exact line must not
        // flag it as a duplicate of itself.
        $response = $this->actingAs($this->admin)->postJson(route('admin.stock-receivings.check-receipt-number'), [
            'receipt_number' => 'RCT-SELF',
            'exclude_purchase_order_item_id' => $itemA->PurchaseOrderItemID,
        ]);

        $response->assertOk();
        $response->assertJson(['used' => false]);
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

    public function test_check_receipt_number_detects_one_already_used_by_the_legacy_manual_record_receipt_flow(): void
    {
        $this->actingAs($this->admin)->post(route('admin.stock-receivings.store'), [
            'ProductID' => $this->productA->ProductID,
            'SupplierID' => $this->supplier->SupplierID,
            'Quantity' => 5,
            'ReceiptNumber' => 'RCT-MANUAL',
            'DateReceived' => now()->format('Y-m-d'),
        ]);

        // The batch flow's own check must also catch a number already used
        // by the separate manual flow — the two uniqueness domains
        // (StockReceiving vs PurchaseOrderItem) aren't cross-validated on
        // submit, so this live check is what actually closes that gap.
        $response = $this->actingAs($this->admin)->postJson(route('admin.stock-receivings.check-receipt-number'), [
            'receipt_number' => 'RCT-MANUAL',
        ]);

        $response->assertOk();
        $response->assertJson(['used' => true]);
    }
}
