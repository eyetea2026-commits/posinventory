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

    public function test_delivery_details_popup_is_wide(): void
    {
        $this->actingAs($this->admin)->get(route('admin.stock-receivings.index'))
            ->assertOk()
            ->assertSee('<div id="batchDetailsModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="batchDetailsModalTitle" aria-hidden="true">
        <div class="modal-content modal-content-wide" style="max-width: 900px;">', false);
    }

    public function test_stock_receiving_index_renders_search_and_supplier_filter(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));

        $response = $this->actingAs($this->admin)->get(route('admin.stock-receivings.index'));

        $response->assertOk();
        $response->assertSee('id="receivingSearchInput"', false);
        $response->assertSee('id="receivingSupplierFilter"', false);
        $response->assertSee($this->supplier->SupplierName);
        // Search and filter share the tabs' row, right after the Completed tab.
        $response->assertSeeInOrder(['class="tabs-header"', 'Completed (', 'class="toolbar receiving-filters"', 'id="receivingSearchInput"', 'id="tab-pending"'], false);
    }

    // ---- PO History tab: printed POs stay viewable and reprintable ----

    private function makeSecondDraftOrder(): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-000002', 'PurchaseDate' => now()->format('Y-m-d'),
            'Status' => PurchaseOrder::STATUS_DRAFT, 'SupplierID' => $this->supplier->SupplierID,
            'CreatedBy' => $this->admin->id,
        ]);
        PurchaseOrderItem::create([
            'PurchaseOrderID' => $po->PurchaseOrderID, 'ProductID' => $this->productA->ProductID,
            'Quantity' => 5, 'CostPriceAtOrder' => 600,
        ]);

        return $po;
    }

    public function test_history_tab_lists_printed_pos_with_a_reprint_button(): void
    {
        $printed = $this->makeDraftOrder();
        $draft = $this->makeSecondDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $printed));

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.index', ['view' => 'history']));

        $response->assertOk();
        $response->assertSee($printed->PONumber);
        $response->assertDontSee($draft->PONumber);
        $response->assertSee(route('admin.purchase-orders.print', $printed), false);
        $response->assertSee('title="Reprint"', false);
    }

    public function test_active_tab_has_no_reprint_button_and_shows_both_counts(): void
    {
        $printed = $this->makeDraftOrder();
        $this->makeSecondDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $printed));

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.index'));

        $response->assertOk();
        $response->assertDontSee('title="Reprint"', false);
        $response->assertSee('Draft Order <span style="opacity: 0.75;">(1)</span>', false);
        $response->assertDontSee('Active Orders');
        $response->assertSee('History <span style="opacity: 0.75;">(1)</span>', false);
    }

    public function test_reprinting_from_history_changes_nothing(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $statusAfterFirstPrint = $po->fresh()->Status;

        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po))->assertOk();

        $this->assertSame($statusAfterFirstPrint, $po->fresh()->Status);
        $this->assertSame(1, StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->count());
    }

    // Regression: the search's OR conditions used to be ungrouped, so a
    // supplier-name search could pull already-printed POs back into the
    // active list.
    public function test_searching_the_active_list_never_surfaces_a_printed_po(): void
    {
        $printed = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $printed));

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.index', ['search' => $this->supplier->SupplierName]));

        $response->assertOk();
        $response->assertDontSee($printed->PONumber);
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
            'receipt_number' => 'DR-PRINT-1',
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
            'receipt_number' => 'RCT-A1',
            'items' => [
                ['purchase_order_item_id' => $itemA->PurchaseOrderItemID, 'quantity_received' => 48],
                ['purchase_order_item_id' => $itemB->PurchaseOrderItemID, 'quantity_received' => 20],
            ],
        ]);

        $response->assertRedirect(route('admin.stock-receivings.index'));

        // Ordered Quantity (the reference) is untouched; Inventory only
        // gained the actually-received amount, not the ordered amount.
        $itemA->refresh();
        $this->assertSame(50, $itemA->Quantity);
        $this->assertSame(48, $itemA->ReceivedQuantity);
        $this->assertSame(5 + 48, Inventory::where('ProductID', $this->productA->ProductID)->value('Quantity'));

        $itemB->refresh();
        $this->assertSame(20, $itemB->Quantity);
        $this->assertSame(20, $itemB->ReceivedQuantity);
        $this->assertSame(2 + 20, Inventory::where('ProductID', $this->productB->ProductID)->value('Quantity'));

        // 2 of product A are still owed, so the order stays open (Partially
        // Received, still pending in Stock Receiving) for the next delivery.
        $batch->refresh();
        $this->assertSame(StockReceivingBatch::STATUS_PENDING, $batch->Status);
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $po->fresh()->Status);
        $this->assertNull($batch->CompletedAt);

        // The rest arrives: received adds up, and the order is complete.
        $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'receipt_number' => 'RCT-A2',
            'items' => [
                ['purchase_order_item_id' => $itemA->PurchaseOrderItemID, 'quantity_received' => 2],
                ['purchase_order_item_id' => $itemB->PurchaseOrderItemID, 'quantity_received' => 0],
            ],
        ])->assertSessionMissing('error');
        $this->assertSame(50, $itemA->fresh()->ReceivedQuantity);
        $this->assertSame(5 + 50, Inventory::where('ProductID', $this->productA->ProductID)->value('Quantity'));
        $this->assertSame(StockReceivingBatch::STATUS_COMPLETED, $batch->fresh()->Status);
        $this->assertSame(PurchaseOrder::STATUS_FULLY_RECEIVED, $po->fresh()->Status);
        $this->assertSame(2, StockReceivingDelivery::where('PurchaseOrderID', $po->PurchaseOrderID)->count());
        $batch->refresh();
        $this->assertSame('RCT-A2', $batch->ReceiptNumber);
        $this->assertNotNull($batch->CompletedAt);
        $this->assertSame($this->admin->id, $batch->ReceivedBy);
    }

    public function test_add_to_inventory_in_full_marks_the_purchase_order_fully_received(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();

        $itemA = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productA->ProductID)->first();
        $itemB = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productB->ProductID)->first();

        $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'receipt_number' => 'RCT-A1',
            'items' => [
                ['purchase_order_item_id' => $itemA->PurchaseOrderItemID, 'quantity_received' => 50],
                ['purchase_order_item_id' => $itemB->PurchaseOrderItemID, 'quantity_received' => 20],
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
        // Listed in exactly one table row (Completed tab) rather than two (it
        // must have disappeared from the Pending tab's table). Counted by
        // row cell, not the whole page: the admin bell's "Delivery Received"
        // notice legitimately mentions the PO number too.
        $response->assertSeeInOrder([$po->PONumber]);
        $this->assertSame(1, substr_count($response->getContent(), 'po-number-cell">'.$po->PONumber.'<'));
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
            'receipt_number' => 'DR-OVER-1',
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
            'receipt_number' => 'DR-TWICE-1',
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

    // Add to Inventory needs the delivery's Receipt Number; the button stays
    // disabled until one is typed.
    public function test_add_to_inventory_requires_a_receipt_number(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();
        $itemA = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productA->ProductID)->first();
        $inventoryBefore = Inventory::where('ProductID', $this->productA->ProductID)->value('Quantity');

        foreach (['', '   '] as $receiptNumber) {
            $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
                'receipt_number' => $receiptNumber,
                'items' => [['purchase_order_item_id' => $itemA->PurchaseOrderItemID, 'quantity_received' => 10]],
            ])->assertSessionHasErrors(['receipt_number' => 'Enter the Receipt Number of this delivery.']);
        }

        $this->assertSame($inventoryBefore, Inventory::where('ProductID', $this->productA->ProductID)->value('Quantity'));

        $html = $this->actingAs($this->admin)->getJson(route('admin.stock-receivings.batches.show', $batch))->json('html');
        $this->assertMatchesRegularExpression('/id="batchReceiptNumberInput"[^>]*required/', $html);
        $this->actingAs($this->admin)->get(route('admin.stock-receivings.index'))->assertOk()
            ->assertSee('id="addToInventoryBtn" style="display:none;" disabled>', false)
            ->assertSee('function syncAddToInventoryButton()', false);
    }

    public function test_one_receipt_number_covers_every_product_in_the_delivery(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();

        $itemA = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productA->ProductID)->first();
        $itemB = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productB->ProductID)->first();

        $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'receipt_number' => 'RCT-SAME',
            'items' => [
                ['purchase_order_item_id' => $itemA->PurchaseOrderItemID, 'quantity_received' => 50],
                ['purchase_order_item_id' => $itemB->PurchaseOrderItemID, 'quantity_received' => 20],
            ],
        ])->assertRedirect(route('admin.stock-receivings.index'))->assertSessionMissing('error');

        $batch->refresh();
        $this->assertSame(StockReceivingBatch::STATUS_COMPLETED, $batch->Status);
        $this->assertSame('RCT-SAME', $batch->ReceiptNumber);
        $this->assertSame(50, $itemA->fresh()->ReceivedQuantity);
        $this->assertSame(20, $itemB->fresh()->ReceivedQuantity);

        // The completed delivery shows that one Receipt Number (also in its
        // Delivery History row), no per-line column.
        $html = $this->actingAs($this->admin)->getJson(route('admin.stock-receivings.batches.show', $batch))->json('html');
        $this->assertStringContainsString('RCT-SAME', $html);
        $this->assertStringContainsString('Delivery History', $html);
        $this->assertStringNotContainsString('][receipt_number]', $html);
    }

    public function test_existing_deliveries_keep_their_receipt_number_after_the_upgrade(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();

        $migration = require database_path('migrations/2026_10_17_071332_add_receipt_number_to_stock_receiving_batch_table.php');
        $migration->down();
        // Before the upgrade, Receipt Numbers were saved per product line.
        PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productA->ProductID)->update(['ReceiptNumber' => 'OLD-RCT-1']);
        $migration->up();

        $this->assertSame('OLD-RCT-1', $batch->fresh()->ReceiptNumber);
    }

    public function test_add_to_inventory_rejects_a_receipt_number_already_used_by_another_delivery(): void
    {
        $po1 = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po1));
        $batch1 = StockReceivingBatch::where('PurchaseOrderID', $po1->PurchaseOrderID)->firstOrFail();
        $item1A = PurchaseOrderItem::where('PurchaseOrderID', $po1->PurchaseOrderID)->where('ProductID', $this->productA->ProductID)->first();
        $item1B = PurchaseOrderItem::where('PurchaseOrderID', $po1->PurchaseOrderID)->where('ProductID', $this->productB->ProductID)->first();

        $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch1), [
            'receipt_number' => 'RCT-DUPE',
            'items' => [
                ['purchase_order_item_id' => $item1A->PurchaseOrderItemID, 'quantity_received' => 50],
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
            'receipt_number' => 'RCT-DUPE',
            'items' => [
                ['purchase_order_item_id' => $item2A->PurchaseOrderItemID, 'quantity_received' => 10],
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

    public function test_check_receipt_number_detects_one_already_used_by_another_delivery(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();
        $itemA = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productA->ProductID)->first();
        $itemB = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productB->ProductID)->first();

        $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'receipt_number' => 'RCT-TAKEN',
            'items' => [
                ['purchase_order_item_id' => $itemA->PurchaseOrderItemID, 'quantity_received' => 50],
                ['purchase_order_item_id' => $itemB->PurchaseOrderItemID, 'quantity_received' => 20],
            ],
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('admin.stock-receivings.check-receipt-number'), [
            'receipt_number' => 'RCT-TAKEN',
        ]);

        $response->assertOk();
        $response->assertJson(['used' => true]);
    }

    public function test_a_later_delivery_cannot_reuse_an_earlier_deliverys_receipt_number(): void
    {
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();
        $itemA = PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $this->productA->ProductID)->first();

        $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'receipt_number' => 'RCT-SELF',
            'items' => [
                ['purchase_order_item_id' => $itemA->PurchaseOrderItemID, 'quantity_received' => 30],
            ],
        ]);

        // The order is still open (30 of 50 arrived); its next delivery is a
        // different delivery, so it can't reuse the first one's receipt number.
        $response = $this->actingAs($this->admin)->postJson(route('admin.stock-receivings.check-receipt-number'), [
            'receipt_number' => 'RCT-SELF',
            'exclude_batch_id' => $batch->StockReceivingBatchID,
        ]);

        $response->assertOk();
        $response->assertJson(['used' => true]);
        $this->actingAs($this->admin)->postJson(route('admin.stock-receivings.check-receipt-number'), [
            'receipt_number' => 'RCT-SELF-2',
            'exclude_batch_id' => $batch->StockReceivingBatchID,
        ])->assertJson(['used' => false]);
    }

    public function test_view_details_shows_ordered_quantity_as_read_only_reference(): void
    {
        // Add Product assigns every product a SKU (SKU-000001 style).
        $this->productA->update(['SKU' => Product::generateSku($this->productA->ProductID)]);
        $po = $this->makeDraftOrder();
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();

        $response = $this->actingAs($this->admin)->getJson(route('admin.stock-receivings.batches.show', $batch));

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('<th>Ordered</th>', $html);
        $this->assertStringContainsString('<th>Still Owed</th>', $html);
        $this->assertStringContainsString('<th>Receiving Now</th>', $html);
        $this->assertStringContainsString('Receipt Number', $html);
        // One Receipt Number field for the whole delivery, not one per line.
        $this->assertSame(1, substr_count($html, 'name="receipt_number"'));
        $this->assertStringNotContainsString('][receipt_number]', $html);
        $this->assertStringContainsString($this->productA->ProductName, $html);
        // Each item shows its Model and SKU next to the product name.
        $product = $this->productA->fresh();
        $this->assertMatchesRegularExpression('#<th>Product</th>\s*<th>Model</th>\s*<th>SKU</th>#', $html);
        $this->assertStringContainsString('<td>'.e($product->Model).'</td>', $html);
        $this->assertStringContainsString('>'.$product->SKU.'</td>', $html);
        $this->assertNotEmpty($product->SKU);
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
