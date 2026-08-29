<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseOrderModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Supplier $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);

        $this->supplier = Supplier::create([
            'SupplierName' => 'Acme Supplies', 'ContactNumber' => '0000', 'Email' => 'acme@example.com', 'Address' => 'N/A',
        ]);
        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $this->product = Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01', 'SKU' => 'SKU-001',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 5, 'Status' => 'Available']);
    }

    // The old UnitPrice column stays gone — CostPriceAtOrder is a distinct,
    // populated field, not a resurrection of the dead one.
    public function test_purchase_order_item_has_no_unit_price_column_but_has_cost_price_at_order(): void
    {
        $this->assertFalse(Schema::hasColumn('PurchaseOrderItem', 'UnitPrice'));
        $this->assertTrue(Schema::hasColumn('PurchaseOrderItem', 'CostPriceAtOrder'));
        $this->assertTrue(Schema::hasColumn('PurchaseOrderItem', 'ReceivedQuantity'));
        $this->assertTrue(Schema::hasColumn('PurchaseOrder', 'ExpectedDeliveryDate'));
        $this->assertTrue(Schema::hasColumn('PurchaseOrder', 'PONumber'));
    }

    // Line Total is the financial value of what was ORDERED on this line,
    // not what has arrived so far — Received/Remaining already have their
    // own columns for tracking fulfillment. Using ReceivedQuantity here
    // made every line read ₱0.00 until receiving started.
    public function test_purchase_order_item_line_total_is_ordered_quantity_times_cost_not_received(): void
    {
        $po = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-LT', 'PurchaseDate' => now()->format('Y-m-d'),
            'Status' => PurchaseOrder::STATUS_PENDING, 'SupplierID' => $this->supplier->SupplierID,
        ]);
        $item = PurchaseOrderItem::create([
            'PurchaseOrderID' => $po->PurchaseOrderID, 'ProductID' => $this->product->ProductID,
            'Quantity' => 20, 'CostPriceAtOrder' => 815, 'ReceivedQuantity' => 0,
        ]);

        $this->assertEquals(16300.0, $item->line_total);

        // Receiving some of it doesn't change the line's own ordered value.
        $item->update(['ReceivedQuantity' => 5]);
        $this->assertEquals(16300.0, $item->fresh()->line_total);
    }

    // PurchaseDate is a plain date the admin can edit freely, and the table
    // never tracked creation timestamps before — two POs placed the same
    // day (or deliberately backdated) couldn't be told apart, so "newest
    // first" wasn't reliable. Sorted by the real creation timestamp now.
    public function test_index_sorts_by_actual_creation_time_not_just_purchase_date(): void
    {
        $older = $this->makeOrder(['PONumber' => 'PO-OLDER', 'PurchaseDate' => now()->format('Y-m-d')]);
        $older->forceFill(['created_at' => now()->subHours(3)])->save();

        $newer = $this->makeOrder(['PONumber' => 'PO-NEWER', 'PurchaseDate' => now()->format('Y-m-d')]);
        $newer->forceFill(['created_at' => now()->subHour()])->save();

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.index'));

        $response->assertOk();
        $body = $response->getContent();
        $this->assertLessThan(strpos($body, 'PO-OLDER'), strpos($body, 'PO-NEWER'));
    }

    public function test_index_has_no_filter_button_but_search_and_category_filter_still_work(): void
    {
        $otherCategory = Category::create(['CategoryName' => 'Networking', 'Description' => 'Switches']);
        $otherProduct = Product::create([
            'ProductName' => 'Network Switch', 'Model' => 'SW-01', 'SKU' => 'SKU-002',
            'Price' => 2000, 'CostPrice' => 1200, 'CategoryID' => $otherCategory->CategoryID,
        ]);
        $matchingPo = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-000001', 'PurchaseDate' => now()->format('Y-m-d'),
            'Status' => PurchaseOrder::STATUS_PENDING, 'SupplierID' => $this->supplier->SupplierID,
        ]);
        PurchaseOrderItem::create(['PurchaseOrderID' => $matchingPo->PurchaseOrderID, 'ProductID' => $this->product->ProductID, 'Quantity' => 5, 'CostPriceAtOrder' => 600]);
        $otherPo = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-000002', 'PurchaseDate' => now()->format('Y-m-d'),
            'Status' => PurchaseOrder::STATUS_PENDING, 'SupplierID' => $this->supplier->SupplierID,
        ]);
        PurchaseOrderItem::create(['PurchaseOrderID' => $otherPo->PurchaseOrderID, 'ProductID' => $otherProduct->ProductID, 'Quantity' => 3, 'CostPriceAtOrder' => 1200]);

        $indexResponse = $this->actingAs($this->admin)->get(route('admin.purchase-orders.index'));
        $indexResponse->assertOk();
        $indexResponse->assertDontSee('<i class="fas fa-filter"></i> Filter', false);

        $categoryFiltered = $this->actingAs($this->admin)->get(route('admin.purchase-orders.index', ['category_id' => $otherCategory->CategoryID]));
        $categoryFiltered->assertOk();
        $categoryFiltered->assertSee('PO-TEST-000002');
        $categoryFiltered->assertDontSee('PO-TEST-000001');

        $searchFiltered = $this->actingAs($this->admin)->get(route('admin.purchase-orders.index', ['search' => 'PO-TEST-000001']));
        $searchFiltered->assertOk();
        $searchFiltered->assertSee('PO-TEST-000001');
        $searchFiltered->assertDontSee('PO-TEST-000002');
    }

    public function test_store_creates_purchase_order_with_generated_po_number_and_cost_snapshot(): void
    {
        // Current Price (650) is deliberately different from the product's
        // own stored CostPrice (600, from setUp) — the saved
        // CostPriceAtOrder must exactly match what was entered here, never
        // silently fall back to the product's own cost.
        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.store'), [
            'SupplierID' => $this->supplier->SupplierID,
            'PurchaseDate' => now()->format('Y-m-d'),
            'ExpectedDeliveryDate' => now()->addDays(7)->format('Y-m-d'),
            'Status' => 'pending',
            'products' => [
                ['product_id' => $this->product->ProductID, 'quantity' => 5, 'cost_price' => 650],
            ],
        ]);

        $response->assertRedirect(route('admin.purchase-orders.index'));
        $po = PurchaseOrder::first();
        $this->assertNotNull($po->PONumber);
        $this->assertStringStartsWith('PO-' . now()->format('Y') . '-', $po->PONumber);
        $this->assertDatabaseHas('PurchaseOrderItem', [
            'ProductID' => $this->product->ProductID, 'Quantity' => 5, 'CostPriceAtOrder' => 650,
        ]);
        $this->assertTrue(ActivityLog::where('Action', 'purchase_order.created')->exists());
    }

    // Current Price: required, numeric, > ₱0.00 — covers every rejection
    // case called out explicitly (missing, zero, negative, non-numeric).
    public function test_store_rejects_missing_zero_negative_and_non_numeric_current_price(): void
    {
        $basePayload = [
            'SupplierID' => $this->supplier->SupplierID,
            'PurchaseDate' => now()->format('Y-m-d'),
            'Status' => 'pending',
        ];

        $missing = $this->actingAs($this->admin)->post(route('admin.purchase-orders.store'), $basePayload + [
            'products' => [['product_id' => $this->product->ProductID, 'quantity' => 5]],
        ]);
        $missing->assertSessionHasErrors('products.0.cost_price');

        $zero = $this->actingAs($this->admin)->post(route('admin.purchase-orders.store'), $basePayload + [
            'products' => [['product_id' => $this->product->ProductID, 'quantity' => 5, 'cost_price' => 0]],
        ]);
        $zero->assertSessionHasErrors('products.0.cost_price');

        $negative = $this->actingAs($this->admin)->post(route('admin.purchase-orders.store'), $basePayload + [
            'products' => [['product_id' => $this->product->ProductID, 'quantity' => 5, 'cost_price' => -100]],
        ]);
        $negative->assertSessionHasErrors('products.0.cost_price');

        $nonNumeric = $this->actingAs($this->admin)->post(route('admin.purchase-orders.store'), $basePayload + [
            'products' => [['product_id' => $this->product->ProductID, 'quantity' => 5, 'cost_price' => 'abc']],
        ]);
        $nonNumeric->assertSessionHasErrors('products.0.cost_price');

        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_store_accepts_a_valid_decimal_current_price(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.store'), [
            'SupplierID' => $this->supplier->SupplierID,
            'PurchaseDate' => now()->format('Y-m-d'),
            'Status' => 'pending',
            'products' => [
                ['product_id' => $this->product->ProductID, 'quantity' => 5, 'cost_price' => 815.75],
            ],
        ]);

        $response->assertRedirect(route('admin.purchase-orders.index'));
        $this->assertDatabaseHas('PurchaseOrderItem', [
            'ProductID' => $this->product->ProductID, 'CostPriceAtOrder' => 815.75,
        ]);
    }

    // The reorder quick-create flow: Previous Price is read-only display
    // only (never a submittable field, so there's nothing to "make
    // read-only" server-side — it simply isn't part of the request), and
    // Current Price is what actually gets saved as CostPriceAtOrder,
    // independent of the product's own stored CostPrice.
    public function test_store_from_reorder_saves_the_entered_current_price_not_the_products_stored_cost(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.purchase-orders.store-from-reorder', $this->product),
            ['OrderQuantity' => 20, 'CostPrice' => 850, 'SupplierID' => $this->supplier->SupplierID]
        );

        $response->assertRedirect(route('admin.purchase-orders.index'));
        $this->assertDatabaseHas('PurchaseOrderItem', [
            'ProductID' => $this->product->ProductID, 'Quantity' => 20, 'CostPriceAtOrder' => 850,
        ]);
        // The product's own stored cost (600, from setUp) is untouched.
        $this->assertEquals(600, $this->product->fresh()->CostPrice);
    }

    public function test_store_from_reorder_rejects_a_missing_or_zero_current_price(): void
    {
        $missing = $this->actingAs($this->admin)->post(
            route('admin.purchase-orders.store-from-reorder', $this->product),
            ['OrderQuantity' => 20, 'SupplierID' => $this->supplier->SupplierID]
        );
        $missing->assertSessionHasErrors('CostPrice');

        $zero = $this->actingAs($this->admin)->post(
            route('admin.purchase-orders.store-from-reorder', $this->product),
            ['OrderQuantity' => 20, 'CostPrice' => 0, 'SupplierID' => $this->supplier->SupplierID]
        );
        $zero->assertSessionHasErrors('CostPrice');

        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_approved_purchase_orders_cannot_be_created_directly(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.store'), [
            'SupplierID' => $this->supplier->SupplierID,
            'PurchaseDate' => now()->format('Y-m-d'),
            'Status' => 'approved',
            'products' => [
                ['product_id' => $this->product->ProductID, 'quantity' => 5],
            ],
        ]);

        $response->assertSessionHasErrors('Status');
    }

    private function makeOrder(array $overrides = []): PurchaseOrder
    {
        $po = PurchaseOrder::create(array_merge([
            'PONumber' => 'PO-TEST-000001',
            'PurchaseDate' => now()->format('Y-m-d'),
            'Status' => PurchaseOrder::STATUS_PENDING,
            'SupplierID' => $this->supplier->SupplierID,
        ], $overrides));

        PurchaseOrderItem::create([
            'PurchaseOrderID' => $po->PurchaseOrderID,
            'ProductID' => $this->product->ProductID,
            'Quantity' => 10,
            'CostPriceAtOrder' => 600,
        ]);

        return $po;
    }

    public function test_approve_transitions_pending_to_approved(): void
    {
        $po = $this->makeOrder();

        $this->actingAs($this->admin)->post(route('admin.purchase-orders.approve', $po));

        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $po->fresh()->Status);
    }

    public function test_approve_records_who_approved_it(): void
    {
        $po = $this->makeOrder();

        $this->actingAs($this->admin)->post(route('admin.purchase-orders.approve', $po));

        $this->assertSame($this->admin->id, $po->fresh()->ApprovedBy);
    }

    public function test_print_preview_renders_po_number_supplier_and_line_items(): void
    {
        $po = $this->makeOrder(['CreatedBy' => $this->admin->id]);
        $this->actingAs($this->admin)->post(route('admin.purchase-orders.approve', $po));

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));

        $response->assertOk();
        $response->assertSee($po->PONumber);
        $response->assertSee('Acme Supplies');
        $response->assertSee('DVR Camera');
        $response->assertSee('CCTV Express');
        $response->assertSee('Prepared By');
        $response->assertSee('Approved By');
        $response->assertSee($this->admin->full_name);
        // 10 ordered x 600 cost = 6,000.00 grand total — ordered-based,
        // same as the Line Total column everywhere else in the PO module.
        $response->assertSee('6,000.00');
        $response->assertSee('Purchase Order Information');
        $response->assertSee('Supplier Information');
        $response->assertSee('Order Summary');
        $response->assertSee('Remarks');
        // "Checked By" was a signature line nothing in this system ever
        // fills in (no "checked by" concept exists anywhere else in the PO
        // lifecycle) — removed from the print layout.
        $response->assertDontSee('Checked By');
    }

    public function test_print_preview_shows_pending_approval_when_not_yet_approved(): void
    {
        $po = $this->makeOrder();

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));

        $response->assertOk();
        $response->assertSee('Pending Approval');
    }

    public function test_show_ajax_branch_returns_read_only_details_without_status_or_action_buttons(): void
    {
        $po = $this->makeOrder(['CreatedBy' => $this->admin->id]);

        $response = $this->actingAs($this->admin)->getJson(route('admin.purchase-orders.show', $po));

        $response->assertOk();
        $response->assertJsonStructure(['html']);
        $html = $response->json('html');

        $this->assertStringContainsString('Order Information', $html);
        $this->assertStringContainsString('Order Items', $html);
        $this->assertStringContainsString('DVR Camera', $html);
        $this->assertStringNotContainsString('Approve', $html);
        $this->assertStringNotContainsString('Cancel', $html);
        $this->assertStringNotContainsString('Export PDF', $html);
    }

    public function test_show_full_page_still_renders_status_and_action_buttons(): void
    {
        $po = $this->makeOrder(['CreatedBy' => $this->admin->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.show', $po));

        $response->assertOk();
        $response->assertSee('Status');
        $response->assertSee('Approve');
        $response->assertSee('Export PDF');
    }

    public function test_index_actions_column_links_to_print_not_export(): void
    {
        $po = $this->makeOrder();

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.index'));

        $response->assertOk();
        $response->assertSee(route('admin.purchase-orders.print', $po), false);
        $response->assertDontSee(route('admin.purchase-orders.export', $po), false);
        $response->assertSee("openViewPurchaseOrderModal(event, {$po->PurchaseOrderID})", false);
    }

    public function test_index_shows_an_edit_action_for_editable_statuses_only(): void
    {
        $editablePo = $this->makeOrder(['PONumber' => 'PO-TEST-EDITABLE', 'Status' => PurchaseOrder::STATUS_PENDING]);
        $lockedPo = $this->makeOrder(['PONumber' => 'PO-TEST-LOCKED', 'Status' => PurchaseOrder::STATUS_APPROVED]);

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.index'));

        $response->assertOk();
        $response->assertSee("openEditPurchaseOrderModal(event, {$editablePo->PurchaseOrderID})", false);
        $response->assertDontSee("openEditPurchaseOrderModal(event, {$lockedPo->PurchaseOrderID})", false);
    }

    public function test_edit_returns_the_form_html_for_an_editable_order(): void
    {
        $po = $this->makeOrder();

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.edit', $po));

        $response->assertOk();
        $response->assertJsonStructure(['html']);
        $this->assertStringContainsString('name="SupplierID"', $response->json('html'));
        $this->assertStringContainsString('value="PUT"', $response->json('html'));
    }

    public function test_edit_rejects_an_order_that_is_no_longer_editable(): void
    {
        $po = $this->makeOrder(['Status' => PurchaseOrder::STATUS_FULLY_RECEIVED]);

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.edit', $po));

        $response->assertSessionHas('error');
    }

    public function test_update_persists_changes_to_an_editable_order(): void
    {
        $po = $this->makeOrder();
        $newSupplier = Supplier::create(['SupplierName' => 'New Supplier', 'ContactNumber' => '1111', 'Email' => 'new@example.com', 'Address' => 'N/A']);

        $response = $this->actingAs($this->admin)->put(route('admin.purchase-orders.update', $po), [
            'SupplierID' => $newSupplier->SupplierID,
            'PurchaseDate' => now()->format('Y-m-d'),
            'Notes' => 'Updated notes',
        ]);

        $response->assertRedirect(route('admin.purchase-orders.index'));
        $this->assertSame($newSupplier->SupplierID, $po->fresh()->SupplierID);
        $this->assertSame('Updated notes', $po->fresh()->Notes);
        $this->assertTrue(ActivityLog::where('Action', 'purchase_order.updated')->exists());
    }

    public function test_update_rejects_an_order_that_is_no_longer_editable(): void
    {
        $po = $this->makeOrder(['Status' => PurchaseOrder::STATUS_FULLY_RECEIVED, 'SupplierID' => $this->supplier->SupplierID]);

        $response = $this->actingAs($this->admin)->put(route('admin.purchase-orders.update', $po), [
            'SupplierID' => $this->supplier->SupplierID,
            'PurchaseDate' => now()->format('Y-m-d'),
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(PurchaseOrder::STATUS_FULLY_RECEIVED, $po->fresh()->Status);
    }

    public function test_cancel_is_blocked_once_any_quantity_received(): void
    {
        $po = $this->makeOrder(['Status' => PurchaseOrder::STATUS_APPROVED]);
        $po->items()->first()->update(['ReceivedQuantity' => 2]);

        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.cancel', $po));

        $response->assertSessionHas('error');
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $po->fresh()->Status);
    }

    public function test_receiving_against_po_line_increments_inventory_and_received_quantity(): void
    {
        $po = $this->makeOrder(['Status' => PurchaseOrder::STATUS_APPROVED]);
        $item = $po->items()->first();

        $response = $this->actingAs($this->admin)->post(route('admin.stock-receivings.store'), [
            'purchase_order_id' => $po->PurchaseOrderID,
            'purchase_order_item_id' => $item->PurchaseOrderItemID,
            'Quantity' => 4,
            'ReceiptNumber' => 'RCV-0001',
            'DateReceived' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('admin.stock-receivings.index'));
        $this->assertSame(9, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
        $this->assertSame(4, $item->fresh()->ReceivedQuantity);
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $po->fresh()->Status);
    }

    public function test_receiving_full_quantity_marks_po_fully_received(): void
    {
        $po = $this->makeOrder(['Status' => PurchaseOrder::STATUS_APPROVED]);
        $item = $po->items()->first();

        $this->actingAs($this->admin)->post(route('admin.stock-receivings.store'), [
            'purchase_order_id' => $po->PurchaseOrderID,
            'purchase_order_item_id' => $item->PurchaseOrderItemID,
            'Quantity' => 10,
            'ReceiptNumber' => 'RCV-0002',
            'DateReceived' => now()->format('Y-m-d'),
        ]);

        $this->assertSame(PurchaseOrder::STATUS_FULLY_RECEIVED, $po->fresh()->Status);
    }

    public function test_receiving_more_than_remaining_quantity_is_rejected(): void
    {
        $po = $this->makeOrder(['Status' => PurchaseOrder::STATUS_APPROVED]);
        $item = $po->items()->first();

        $response = $this->actingAs($this->admin)->post(route('admin.stock-receivings.store'), [
            'purchase_order_id' => $po->PurchaseOrderID,
            'purchase_order_item_id' => $item->PurchaseOrderItemID,
            'Quantity' => 11,
            'ReceiptNumber' => 'RCV-0003',
            'DateReceived' => now()->format('Y-m-d'),
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, $item->fresh()->ReceivedQuantity);
        $this->assertSame(5, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
    }

    public function test_ad_hoc_receiving_without_a_po_still_works(): void
    {
        $supplier2 = Supplier::create(['SupplierName' => 'Other Supplier', 'ContactNumber' => '111', 'Email' => 'x@example.com', 'Address' => 'N/A']);

        $response = $this->actingAs($this->admin)->post(route('admin.stock-receivings.store'), [
            'ProductID' => $this->product->ProductID,
            'SupplierID' => $supplier2->SupplierID,
            'Quantity' => 3,
            'ReceiptNumber' => 'RCV-ADHOC-0001',
            'DateReceived' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('admin.stock-receivings.index'));
        $this->assertSame(8, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
    }
}
