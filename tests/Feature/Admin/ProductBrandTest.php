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

    public function test_create_page_offers_a_brand_select_with_every_brand(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.products.create'));

        $response->assertOk();
        $response->assertSee('name="BrandID"', false);
        $response->assertSee('Hikvision');
    }

    public function test_storing_a_product_with_a_brand_persists_it(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'ProductName' => 'Branded Camera', 'Model' => 'BC-001', 'Barcode' => 'BARCODE-BC-001',
            'CostPrice' => 500, 'CategoryID' => $this->category->CategoryID, 'BrandID' => $this->brand->BrandID,
        ]);

        $response->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('Product', ['ProductName' => 'Branded Camera', 'BrandID' => $this->brand->BrandID]);
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

    public function test_edit_page_preselects_the_products_current_brand(): void
    {
        $product = Product::create([
            'ProductName' => 'Existing Camera', 'Model' => 'EC-001', 'Barcode' => 'BARCODE-EC-001',
            'CostPrice' => 500, 'Price' => 700, 'CategoryID' => $this->category->CategoryID, 'BrandID' => $this->brand->BrandID,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.products.edit', $product));

        $response->assertOk();
        $response->assertSee("value=\"{$this->brand->BrandID}\" selected", false);
    }

    public function test_updating_a_products_brand_persists(): void
    {
        $product = Product::create([
            'ProductName' => 'Rebrand Me', 'Model' => 'RM-001', 'Barcode' => 'BARCODE-RM-001',
            'CostPrice' => 500, 'Price' => 700, 'CategoryID' => $this->category->CategoryID,
        ]);
        $newBrand = Brand::create(['BrandName' => 'Dahua']);

        $response = $this->actingAs($this->admin)->put(route('admin.products.update', $product), [
            'ProductName' => 'Rebrand Me', 'Model' => 'RM-001', 'Barcode' => 'BARCODE-RM-001',
            'CostPrice' => 500, 'Price' => 700, 'CategoryID' => $this->category->CategoryID, 'BrandID' => $newBrand->BrandID,
        ]);

        $response->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('Product', ['ProductID' => $product->ProductID, 'BrandID' => $newBrand->BrandID]);
    }
}
