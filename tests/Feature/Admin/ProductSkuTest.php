<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSkuTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
    }

    public function test_storing_a_product_auto_generates_a_sku_from_its_product_id(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01', 'Barcode' => 'BARCODE-CAM-01',
            'CostPrice' => 500, 'CategoryID' => $this->category->CategoryID,
        ]);

        $response->assertRedirect(route('admin.products.index'));
        $product = Product::where('ProductName', 'DVR Camera')->firstOrFail();

        $this->assertSame('SKU-'.str_pad((string) $product->ProductID, 6, '0', STR_PAD_LEFT), $product->SKU);
    }

    public function test_two_products_created_in_sequence_get_distinct_skus(): void
    {
        $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'ProductName' => 'First Camera', 'Model' => 'CAM-01', 'Barcode' => 'BARCODE-CAM-01',
            'CostPrice' => 500, 'CategoryID' => $this->category->CategoryID,
        ]);
        $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'ProductName' => 'Second Camera', 'Model' => 'CAM-02', 'Barcode' => 'BARCODE-CAM-02',
            'CostPrice' => 500, 'CategoryID' => $this->category->CategoryID,
        ]);

        $first = Product::where('ProductName', 'First Camera')->firstOrFail();
        $second = Product::where('ProductName', 'Second Camera')->firstOrFail();

        $this->assertNotSame($first->SKU, $second->SKU);
    }

    public function test_product_index_search_finds_a_product_by_its_sku(): void
    {
        $product = Product::create([
            'ProductName' => 'Findable Camera', 'Model' => 'FC-01', 'Barcode' => 'BARCODE-FC-01',
            'CostPrice' => 500, 'Price' => 700, 'CategoryID' => $this->category->CategoryID,
        ]);
        $product->update(['SKU' => Product::generateSku($product->ProductID)]);

        $response = $this->actingAs($this->admin)->get(route('admin.products.index', ['search' => $product->SKU]));

        $response->assertOk();
        $response->assertSee('Findable Camera');
    }

    public function test_edit_product_modals_ajax_response_shows_the_sku_read_only(): void
    {
        $product = Product::create([
            'ProductName' => 'Editable Camera', 'Model' => 'EC-01', 'Barcode' => 'BARCODE-EC-01',
            'CostPrice' => 500, 'Price' => 700, 'CategoryID' => $this->category->CategoryID,
        ]);
        $product->update(['SKU' => Product::generateSku($product->ProductID)]);

        $response = $this->actingAs($this->admin)->get(
            route('admin.products.edit', $product),
            ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']
        );

        $response->assertOk();
        $html = $response->json('html');

        $this->assertStringContainsString($product->SKU, $html);
        $this->assertStringContainsString('readonly', $html);
    }

    public function test_view_details_shows_the_sku(): void
    {
        $product = Product::create([
            'ProductName' => 'Viewable Camera', 'Model' => 'VC-01', 'Barcode' => 'BARCODE-VC-01',
            'CostPrice' => 500, 'Price' => 700, 'CategoryID' => $this->category->CategoryID,
        ]);
        $product->update(['SKU' => Product::generateSku($product->ProductID)]);

        $response = $this->actingAs($this->admin)->get(route('admin.products.show', $product));

        $response->assertOk();
        $response->assertSee($product->SKU);
    }

    public function test_add_product_form_never_shows_a_sku_field(): void
    {
        // SKU can't exist until the product's ProductID is assigned at
        // creation, so the Add form (unlike Edit) never renders it.
        $response = $this->actingAs($this->admin)->get(route('admin.products.create'));

        $response->assertOk();
        $response->assertDontSee('name="SKU"', false);
    }
}
