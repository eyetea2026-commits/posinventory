<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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

    public function test_the_edit_suppliers_panel_reads_its_own_supplier_box(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.products.index'))->assertOk()->getContent();

        $this->assertStringContainsString('window.initProductSuppliersPanel(productId, form);', $html);
        $this->assertStringContainsString("var supplierSelect = root.querySelector('#newSupplierId');", $html);
        $this->assertStringNotContainsString("document.getElementById('newSupplierId')", $html);
        $this->assertStringNotContainsString('Add / Update Supplier', $html);

        $this->assertStringContainsString('window.hideLinkedSupplierOptions = function (select, supplierIds) {', $html);
        $this->assertStringContainsString('window.hideLinkedSupplierOptions(supplierSelect, suppliers.map(function (ps) { return ps.SupplierID; }));', $html);
        $this->assertStringContainsString('window.hideLinkedSupplierOptions(newSupplierIdSelect, pendingSuppliers.map(function (entry) { return entry.supplierId; }));', $html);

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
