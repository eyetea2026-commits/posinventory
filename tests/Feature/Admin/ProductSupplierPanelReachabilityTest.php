<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The Edit Product button opens an AJAX modal (see openEditProductModal() in
// admin/products/index.blade.php), which only ever fetches the
// product-form-fields partial — the "Suppliers for this Product" panel used
// to live only on the separate standalone edit page nothing links to
// anymore, making it permanently unreachable. The panel now lives inside
// product-form-fields.blade.php itself, rendering an AJAX (Edit mode) or
// pending/client-side-only (Add mode) variant depending on whether a
// product exists yet, so both the modal's AJAX response and the standalone
// page render it from the same place.
class ProductSupplierPanelReachabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $this->product = Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01', 'Barcode' => 'BARCODE-CAM-01',
            'CostPrice' => 500, 'Price' => 700, 'CategoryID' => $this->category->CategoryID,
        ]);

        Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0000', 'Email' => 'acme@example.com', 'Address' => 'N/A']);
    }

    public function test_edit_product_modals_ajax_response_includes_the_suppliers_panel(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.products.edit', $this->product),
            ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']
        );

        $response->assertOk();
        $html = $response->json('html');

        $this->assertStringContainsString('Suppliers for this Product', $html);
        $this->assertStringContainsString('id="productSuppliersBody"', $html);
        $this->assertStringContainsString('id="addProductSupplierBtn"', $html);
        $this->assertStringContainsString('Acme Supplies', $html);
    }

    // The Add Product popup's supplier box and button share their ids with
    // the Edit popup's, so the Edit panel must look inside its own form;
    // looking page-wide read the Add popup's empty box and said "Select a
    // supplier." even with one chosen.
    public function test_the_edit_suppliers_panel_reads_its_own_supplier_box(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.products.index'))->assertOk()->getContent();

        $this->assertStringContainsString('window.initProductSuppliersPanel(productId, form);', $html);
        $this->assertStringContainsString("var supplierSelect = root.querySelector('#newSupplierId');", $html);
        $this->assertStringNotContainsString("document.getElementById('newSupplierId')", $html);

        $this->actingAs($this->admin)->get(route('admin.products.edit', $this->product))->assertOk()
            ->assertSee("window.initProductSuppliersPanel({$this->product->ProductID}, document.getElementById('productForm'));", false);
    }

    public function test_standalone_edit_page_shows_the_suppliers_panel_exactly_once(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.products.edit', $this->product));

        $response->assertOk();
        $response->assertSee('Suppliers for this Product');
        $this->assertSame(1, substr_count($response->getContent(), 'id="productSuppliersBody"'));
    }

    // The Add Product form now renders its own pending (client-side-only)
    // variant of this panel, since a supplier can be chosen before the
    // product exists — see the "Suppliers[][SupplierID]" handling in
    // ProductController::store() and the matching JS in
    // product-form-behavior.blade.php. It must never render the AJAX
    // (Edit-mode) panel markup, since no ProductID exists yet to poll.
    public function test_add_product_form_renders_the_pending_suppliers_panel_not_the_ajax_one(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.products.create'));

        $response->assertOk();
        $response->assertSee('Suppliers for this Product');
        $response->assertSee('id="pendingProductSuppliersBody"', false);
        $response->assertSee('id="addProductSupplierBtn"', false);
        $response->assertSee('Acme Supplies');
        $response->assertDontSee('id="productSuppliersBody"', false);
    }
}
