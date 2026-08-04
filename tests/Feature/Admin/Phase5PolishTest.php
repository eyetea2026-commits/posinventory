<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Covers the "Validation & confirmation dialog polish" pass: SweetAlert
// coverage on previously-uncovered actions (logout, PO submit/approve),
// and the small server-side validation gaps found in the audit.
class Phase5PolishTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
    }

    public function test_supplier_store_rejects_a_too_short_contact_number(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.suppliers.store'), [
            'SupplierName' => 'Acme Supplies', 'ContactNumber' => '123',
            'Email' => 'acme@example.com', 'Address' => 'N/A',
        ]);

        $response->assertSessionHasErrors('ContactNumber');
        $this->assertDatabaseMissing('Supplier', ['SupplierName' => 'Acme Supplies']);
    }

    public function test_supplier_store_accepts_a_realistic_contact_number(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.suppliers.store'), [
            'SupplierName' => 'Acme Supplies', 'ContactNumber' => '+63 912 345 6789',
            'Email' => 'acme@example.com', 'Address' => 'N/A',
        ]);

        $response->assertSessionDoesntHaveErrors('ContactNumber');
        $this->assertDatabaseHas('Supplier', ['SupplierName' => 'Acme Supplies']);
    }

    public function test_admin_layout_logout_form_carries_a_confirm_dialog(): void
    {
        // Uses the Suppliers index rather than the dashboard — the
        // dashboard's chart data query relies on MySQL's YEARWEEK(), which
        // has no SQLite equivalent and isn't available under the test DB.
        $response = $this->actingAs($this->admin)->get(route('admin.suppliers.index'));

        $response->assertOk();
        $response->assertSee('js-confirm-submit', false);
        $response->assertSee(route('admin.logout'), false);
    }

    private function makeOrder(array $overrides = []): PurchaseOrder
    {
        $supplier = Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0000000', 'Email' => 'acme@example.com', 'Address' => 'N/A']);
        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $product = Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01', 'SKU' => 'SKU-001',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);

        $po = PurchaseOrder::create(array_merge([
            'PONumber' => 'PO-TEST-' . uniqid(),
            'SupplierID' => $supplier->SupplierID,
            'PurchaseDate' => now()->format('Y-m-d'),
            'Status' => PurchaseOrder::STATUS_PENDING,
            'CreatedBy' => $this->admin->id,
        ], $overrides));

        $po->items()->create(['ProductID' => $product->ProductID, 'Quantity' => 10, 'CostPriceAtOrder' => 600]);

        return $po;
    }

    public function test_purchase_order_show_page_wraps_submit_and_approve_in_a_confirm_dialog(): void
    {
        $po = $this->makeOrder(['Status' => PurchaseOrder::STATUS_DRAFT]);

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.show', $po));

        $response->assertOk();
        $response->assertSee('data-confirm-title="Submit Purchase Order"', false);
        $response->assertSee('data-confirm-title="Approve Purchase Order"', false);
    }

    // Regression test for a real bug found during the SweetAlert2 redesign:
    // admin/layout.blade.php carried its own older `.swal2-popup { width:
    // 28em !important; ... }` block, positioned AFTER the shared
    // partials/swal-helpers.blade.php include — with matching specificity
    // and !important, source order made it win every time, silently
    // no-op'ing the shared partial's sizing on every single admin page.
    public function test_admin_layout_does_not_carry_a_duplicate_swal2_popup_override(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.suppliers.index'));

        $response->assertOk();
        // The shared partial's compact rule should be present exactly once.
        $content = $response->getContent();
        $this->assertSame(1, substr_count($content, 'width: 380px !important'));
        // The old layout-local override (a competing "28em !important" width
        // declaration) must not have come back — checked as the exact
        // duplicate-rule signature, not a bare "28em" substring, since that
        // string alone also appears inside this file's own explanatory CSS
        // comment describing the bug that was fixed.
        $this->assertStringNotContainsString('width: 28em !important', $content);
    }

    // Regression test for the "receipt never appears" bug: when the
    // browser's popup blocker prevents window.open() from succeeding, the
    // sale still completed successfully server-side but the cashier saw
    // nothing — no receipt, no error. The POS page must always ship the
    // in-page fallback viewer so a receipt is guaranteed to display
    // regardless of whether popups are allowed.
    public function test_pos_page_ships_the_receipt_fallback_viewer_for_blocked_popups(): void
    {
        $cashierRole = Role::firstOrCreate(['role_name' => 'cashier']);
        $cashier = User::factory()->create(['role_id' => $cashierRole->id]);

        $response = $this->actingAs($cashier)->get(route('cashier.pos'));

        $response->assertOk();
        $response->assertSee('id="receiptFallbackOverlay"', false);
        $response->assertSee('showReceiptFallback', false);
        $response->assertSee('printReceiptFallback', false);
    }
}
