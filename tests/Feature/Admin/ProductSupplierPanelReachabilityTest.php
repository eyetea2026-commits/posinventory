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
// product-form-fields.blade.php itself (gated to Edit mode), so both the
// modal's AJAX response and the standalone page render it from the same
// place.
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

    public function test_standalone_edit_page_shows_the_suppliers_panel_exactly_once(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.products.edit', $this->product));

        $response->assertOk();
        $response->assertSee('Suppliers for this Product');
        $this->assertSame(1, substr_count($response->getContent(), 'id="productSuppliersBody"'));
    }

    public function test_add_product_form_never_renders_the_suppliers_panel(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.products.create'));

        $response->assertOk();
        $response->assertDontSee('id="productSuppliersBody"', false);
        $response->assertDontSee('id="addProductSupplierBtn"', false);
    }
}
