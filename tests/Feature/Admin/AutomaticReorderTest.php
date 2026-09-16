<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomaticReorderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $product;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);

        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $this->product = Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01', 'SKU' => 'SKU-001',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 5, 'ReorderThreshold' => 20, 'Status' => 'Low Stock']);

        $this->supplier = Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactPerson' => 'Jane Roe', 'ContactNumber' => '0000', 'Email' => 'acme@example.com', 'Address' => 'N/A']);
    }

    public function test_create_reorder_page_renders_with_suggested_quantity_when_no_supplier_known(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.create-from-reorder', $this->product));

        $response->assertOk();
        $response->assertSee('DVR Camera');
        // (20 - 5) + 20 = 35
        $response->assertSee('35');
        $response->assertSee('No supplier is assigned to this product');
    }

    public function test_create_reorder_page_preselects_the_sole_known_supplier_read_only(): void
    {
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID, 'CostPrice' => 550]);

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.create-from-reorder', $this->product));

        $response->assertOk();
        $response->assertDontSee('No supplier is assigned');
        $response->assertDontSee('Multiple suppliers are on record');
        $response->assertSee('Acme Supplies');
        $response->assertSee('Jane Roe');
        // Resolved supplier is read-only — no supplier <select> should render.
        $response->assertDontSee('id="SupplierID"', false);
    }

    public function test_create_reorder_page_has_no_helper_text_or_auto_filled_labels(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.create-from-reorder', $this->product));

        $response->assertOk();
        $response->assertDontSee('Product and supplier information are auto-filled');
        $response->assertDontSee('(Auto-Filled)');
    }

    public function test_create_reorder_ajax_request_returns_json_fields_for_the_modal(): void
    {
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID, 'CostPrice' => 550]);

        $response = $this->actingAs($this->admin)->getJson(route('admin.purchase-orders.create-from-reorder', $this->product));

        $response->assertOk();
        $response->assertJsonStructure(['html', 'productName']);
        $this->assertSame('DVR Camera', $response->json('productName'));
        $this->assertStringContainsString('Acme Supplies', $response->json('html'));
        $this->assertStringNotContainsString('(Auto-Filled)', $response->json('html'));
    }

    public function test_inventory_index_offers_reorder_modal_trigger_for_low_stock_product(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));

        $response->assertOk();
        $response->assertSee('openReorderModal(' . $this->product->ProductID . ')', false);
        $response->assertSee('id="reorderPurchaseOrderModal"', false);
        // The page must no longer navigate away to a separate PO page.
        $response->assertDontSee('href="' . route('admin.purchase-orders.create-from-reorder', $this->product) . '"', false);
    }

    public function test_create_reorder_page_flags_ambiguity_with_multiple_unpreferred_suppliers(): void
    {
        $supplier2 = Supplier::create(['SupplierName' => 'Other Supplier', 'ContactNumber' => '111', 'Email' => 'x@example.com', 'Address' => 'N/A']);
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID, 'CostPrice' => 550]);
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $supplier2->SupplierID, 'CostPrice' => 500]);

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.create-from-reorder', $this->product));

        $response->assertOk();
        $response->assertSee('Multiple suppliers are on record for this product');
        $response->assertSee('Acme Supplies');
        $response->assertSee('Other Supplier');
    }

    public function test_reordering_a_fully_stocked_product_is_not_offered_in_inventory_ui(): void
    {
        Inventory::where('ProductID', $this->product->ProductID)->update(['Quantity' => 100, 'ReorderThreshold' => 20]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));

        $response->assertOk();
        // The "Create Purchase Order" modal shell is always present in the
        // page (so it can react to products becoming low-stock via the
        // live poll without a reload) — what must be absent is a trigger
        // wired to THIS specific, fully-stocked product.
        $response->assertDontSee('openReorderModal(' . $this->product->ProductID . ')', false);
    }

    public function test_store_from_reorder_creates_po_using_resolved_supplier_and_cost(): void
    {
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID, 'CostPrice' => 550, 'IsPreferred' => 1]);

        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.store-from-reorder', $this->product), [
            'SupplierID' => $this->supplier->SupplierID,
            'OrderQuantity' => 35,
            'CostPrice' => 550,
            'Remarks' => 'Restocking low inventory',
        ]);

        $response->assertRedirect(route('admin.purchase-orders.index'));

        // Every new Purchase Order starts as a Draft, including this
        // Inventory-reorder-triggered one — it used to jump straight to
        // Pending, skipping the review step Draft exists for.
        $this->assertDatabaseHas('PurchaseOrder', [
            'SupplierID' => $this->supplier->SupplierID,
            'Status' => PurchaseOrder::STATUS_DRAFT,
            'Notes' => 'Restocking low inventory',
        ]);
        $this->assertDatabaseHas('PurchaseOrderItem', [
            'ProductID' => $this->product->ProductID, 'Quantity' => 35, 'CostPriceAtOrder' => 550,
        ]);
    }

    public function test_store_from_reorder_rejects_zero_or_empty_order_quantity(): void
    {
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID, 'CostPrice' => 550, 'IsPreferred' => 1]);

        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.store-from-reorder', $this->product), [
            'SupplierID' => $this->supplier->SupplierID,
            'OrderQuantity' => 0,
        ]);

        $response->assertSessionHasErrors('OrderQuantity');
        $this->assertDatabaseMissing('PurchaseOrderItem', ['ProductID' => $this->product->ProductID]);

        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.store-from-reorder', $this->product), [
            'SupplierID' => $this->supplier->SupplierID,
        ]);

        $response->assertSessionHasErrors('OrderQuantity');
    }

    public function test_store_from_reorder_with_no_known_supplier_requires_and_assigns_one(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.store-from-reorder', $this->product), [
            'OrderQuantity' => 35,
        ]);

        $response->assertSessionHasErrors('SupplierID');

        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.store-from-reorder', $this->product), [
            'SupplierID' => $this->supplier->SupplierID,
            'OrderQuantity' => 35,
            'CostPrice' => 600,
        ]);

        $response->assertRedirect(route('admin.purchase-orders.index'));

        // Picking a supplier for a product with none on record assigns it.
        $this->assertDatabaseHas('ProductSupplier', [
            'ProductID' => $this->product->ProductID,
            'SupplierID' => $this->supplier->SupplierID,
        ]);
        $this->assertDatabaseHas('PurchaseOrderItem', [
            'ProductID' => $this->product->ProductID, 'Quantity' => 35, 'CostPriceAtOrder' => 600,
        ]);
    }

    public function test_store_from_reorder_rejects_a_supplier_not_known_to_an_ambiguous_product(): void
    {
        $supplier2 = Supplier::create(['SupplierName' => 'Other Supplier', 'ContactNumber' => '111', 'Email' => 'x@example.com', 'Address' => 'N/A']);
        $unrelatedSupplier = Supplier::create(['SupplierName' => 'Unrelated', 'ContactNumber' => '222', 'Email' => 'y@example.com', 'Address' => 'N/A']);
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID, 'CostPrice' => 550]);
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $supplier2->SupplierID, 'CostPrice' => 500]);

        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.store-from-reorder', $this->product), [
            'SupplierID' => $unrelatedSupplier->SupplierID,
            'OrderQuantity' => 35,
        ]);

        $response->assertSessionHasErrors('SupplierID');
    }

    // --- Automatic Draft PO on Low Stock (no manual click needed) ---------

    public function test_visiting_inventory_auto_drafts_exactly_one_po_for_a_low_stock_product(): void
    {
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID, 'CostPrice' => 550]);

        $this->assertDatabaseCount('PurchaseOrder', 0);

        $this->actingAs($this->admin)->get(route('admin.inventory.index'))->assertOk();

        $this->assertDatabaseCount('PurchaseOrder', 1);
        $this->assertDatabaseHas('PurchaseOrder', [
            'SupplierID' => $this->supplier->SupplierID,
            'Status' => PurchaseOrder::STATUS_DRAFT,
        ]);
        // (20 - 5) + 20 = 35, same formula the manual reorder form suggests.
        $this->assertDatabaseHas('PurchaseOrderItem', [
            'ProductID' => $this->product->ProductID, 'Quantity' => 35, 'CostPriceAtOrder' => 550,
        ]);
        $this->assertTrue(Inventory::where('ProductID', $this->product->ProductID)->first()->AutoReorderTriggered);
    }

    public function test_repeated_inventory_checks_never_create_a_duplicate_draft_po(): void
    {
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID, 'CostPrice' => 550]);

        // Simulates refresh, reopening Inventory, and logout/login: the
        // stock stays at the same Low Stock level across repeated checks.
        $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $this->actingAs($this->admin)->get(route('admin.inventory.index'));

        $this->assertDatabaseCount('PurchaseOrder', 1);
    }

    public function test_auto_drafted_po_appears_in_the_existing_po_module_and_is_never_auto_approved(): void
    {
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID, 'CostPrice' => 550]);

        $this->actingAs($this->admin)->get(route('admin.inventory.index'));

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.index'));
        $response->assertOk();
        $response->assertSee('DVR Camera');

        // Admin leaves it untouched — further checks don't touch its status.
        $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $this->actingAs($this->admin)->get(route('admin.inventory.index'));

        $this->assertDatabaseHas('PurchaseOrder', ['Status' => PurchaseOrder::STATUS_DRAFT]);
    }

    public function test_restocking_resets_the_cycle_so_a_later_drop_drafts_exactly_one_new_po(): void
    {
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID, 'CostPrice' => 550]);

        $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $this->assertDatabaseCount('PurchaseOrder', 1);
        $this->assertTrue(Inventory::where('ProductID', $this->product->ProductID)->first()->AutoReorderTriggered);

        // Restocked above the threshold — the cycle resets.
        Inventory::where('ProductID', $this->product->ProductID)->update(['Quantity' => 100]);
        $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $this->assertFalse(Inventory::where('ProductID', $this->product->ProductID)->first()->AutoReorderTriggered);
        $this->assertDatabaseCount('PurchaseOrder', 1);

        // Drops low again — exactly one new draft PO, not zero, not two.
        Inventory::where('ProductID', $this->product->ProductID)->update(['Quantity' => 5]);
        $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $this->assertDatabaseCount('PurchaseOrder', 2);
    }

    public function test_an_existing_manual_draft_po_is_recognized_and_no_automatic_duplicate_is_created(): void
    {
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID, 'CostPrice' => 550]);

        // Admin already manually created a PO for this product before the
        // automatic check ever ran.
        $manualPo = PurchaseOrder::create([
            'PONumber' => 'PO-2026-000001',
            'PurchaseDate' => now()->toDateString(),
            'Status' => PurchaseOrder::STATUS_PENDING,
            'SupplierID' => $this->supplier->SupplierID,
            'CreatedBy' => $this->admin->id,
        ]);
        \App\Models\PurchaseOrderItem::create([
            'PurchaseOrderID' => $manualPo->PurchaseOrderID, 'ProductID' => $this->product->ProductID, 'Quantity' => 35, 'CostPriceAtOrder' => 550,
        ]);

        $this->actingAs($this->admin)->get(route('admin.inventory.index'));

        $this->assertDatabaseCount('PurchaseOrder', 1);
        $this->assertTrue(Inventory::where('ProductID', $this->product->ProductID)->first()->AutoReorderTriggered);
    }

    public function test_no_resolvable_supplier_does_not_auto_draft_and_keeps_retrying(): void
    {
        // The default setUp product has no known supplier at all.
        $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $this->assertDatabaseCount('PurchaseOrder', 0);
        $this->assertFalse(Inventory::where('ProductID', $this->product->ProductID)->first()->AutoReorderTriggered);

        // Once a supplier is assigned, the very next check succeeds.
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID, 'CostPrice' => 550]);
        $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $this->assertDatabaseCount('PurchaseOrder', 1);
    }

    public function test_in_stock_products_never_get_an_automatic_draft_po(): void
    {
        Inventory::where('ProductID', $this->product->ProductID)->update(['Quantity' => 100]);
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID, 'CostPrice' => 550]);

        $this->actingAs($this->admin)->get(route('admin.inventory.index'));

        $this->assertDatabaseCount('PurchaseOrder', 0);
    }
}
