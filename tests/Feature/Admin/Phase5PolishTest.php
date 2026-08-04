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
}
