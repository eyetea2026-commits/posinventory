<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBrandTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Category $category;
    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $this->brand = Brand::create(['BrandName' => 'Hikvision']);
    }

    public function test_create_page_offers_a_brand_field_with_every_brand_suggested(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.products.create'));

        $response->assertOk();
        $response->assertSee('name="BrandName"', false);
        $response->assertSee('Hikvision');
    }

    public function test_storing_a_product_with_a_known_brand_name_persists_it(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'ProductName' => 'Branded Camera', 'Model' => 'BC-001', 'Barcode' => 'BARCODE-BC-001',
            'CostPrice' => 500, 'CategoryID' => $this->category->CategoryID, 'BrandName' => $this->brand->BrandName,
        ]);

        $response->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('Product', ['ProductName' => 'Branded Camera', 'BrandID' => $this->brand->BrandID]);
    }

    public function test_storing_a_product_with_a_manually_typed_brand_creates_it(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'ProductName' => 'New Brand Camera', 'Model' => 'NB-001', 'Barcode' => 'BARCODE-NB-001',
            'CostPrice' => 500, 'CategoryID' => $this->category->CategoryID, 'BrandName' => 'Dahua',
        ]);

        $response->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('Brand', ['BrandName' => 'Dahua']);
        $newBrand = Brand::where('BrandName', 'Dahua')->firstOrFail();
        $this->assertDatabaseHas('Product', ['ProductName' => 'New Brand Camera', 'BrandID' => $newBrand->BrandID]);
    }

    public function test_storing_a_product_without_a_brand_still_succeeds(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'ProductName' => 'Unbranded Camera', 'Model' => 'UC-001', 'Barcode' => 'BARCODE-UC-001',
            'CostPrice' => 500, 'CategoryID' => $this->category->CategoryID,
        ]);

        $response->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('Product', ['ProductName' => 'Unbranded Camera', 'BrandID' => null]);
    }

    public function test_edit_page_prefills_the_products_current_brand_name(): void
    {
        $product = Product::create([
            'ProductName' => 'Existing Camera', 'Model' => 'EC-001', 'Barcode' => 'BARCODE-EC-001',
            'CostPrice' => 500, 'Price' => 700, 'CategoryID' => $this->category->CategoryID, 'BrandID' => $this->brand->BrandID,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.products.edit', $product));

        $response->assertOk();
        $response->assertSee("value=\"{$this->brand->BrandName}\"", false);
    }

    public function test_updating_a_products_brand_to_a_manually_typed_name_persists(): void
    {
        $product = Product::create([
            'ProductName' => 'Rebrand Me', 'Model' => 'RM-001', 'Barcode' => 'BARCODE-RM-001',
            'CostPrice' => 500, 'Price' => 700, 'CategoryID' => $this->category->CategoryID,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.products.update', $product), [
            'ProductName' => 'Rebrand Me', 'Model' => 'RM-001', 'Barcode' => 'BARCODE-RM-001',
            'CostPrice' => 500, 'Price' => 700, 'CategoryID' => $this->category->CategoryID, 'BrandName' => 'Dahua',
        ]);

        $response->assertRedirect(route('admin.products.index'));
        $newBrand = Brand::where('BrandName', 'Dahua')->firstOrFail();
        $this->assertDatabaseHas('Product', ['ProductID' => $product->ProductID, 'BrandID' => $newBrand->BrandID]);
    }
}
