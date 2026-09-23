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

    private Category $otherCategory;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $this->otherCategory = Category::create(['CategoryName' => 'DVR', 'Description' => 'Recorders']);
        $this->brand = Brand::create(['BrandName' => 'Hikvision', 'CategoryID' => $this->category->CategoryID]);
    }

    public function test_create_page_offers_a_brand_select_scoped_to_every_category(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.products.create'));

        $response->assertOk();
        $response->assertSee('name="BrandID"', false);
        $response->assertSee('Hikvision');
        // The Brand <option> carries its own Category so client-side JS can
        // filter it — this is the one thing the create page must render
        // correctly for that filtering to be possible at all.
        $response->assertSee('data-category="'.$this->category->CategoryID.'"', false);
    }

    public function test_storing_a_product_with_a_brand_belonging_to_the_selected_category_persists_it(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'ProductName' => 'Branded Camera', 'Model' => 'BC-001', 'Barcode' => 'BARCODE-BC-001',
            'CostPrice' => 500, 'CategoryID' => $this->category->CategoryID, 'BrandID' => $this->brand->BrandID,
        ]);

        $response->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('Product', ['ProductName' => 'Branded Camera', 'BrandID' => $this->brand->BrandID]);
    }

    public function test_storing_a_product_rejects_a_brand_that_belongs_to_a_different_category(): void
    {
        // $this->brand belongs to $this->category, not $this->otherCategory.
        $response = $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'ProductName' => 'Mismatched Camera', 'Model' => 'MC-001', 'Barcode' => 'BARCODE-MC-001',
            'CostPrice' => 500, 'CategoryID' => $this->otherCategory->CategoryID, 'BrandID' => $this->brand->BrandID,
        ]);

        $response->assertSessionHasErrors('BrandID');
        $this->assertDatabaseMissing('Product', ['ProductName' => 'Mismatched Camera']);
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

    public function test_edit_page_prefills_the_products_current_brand(): void
    {
        $product = Product::create([
            'ProductName' => 'Existing Camera', 'Model' => 'EC-001', 'Barcode' => 'BARCODE-EC-001',
            'CostPrice' => 500, 'Price' => 700, 'CategoryID' => $this->category->CategoryID, 'BrandID' => $this->brand->BrandID,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.products.edit', $product));

        $response->assertOk();
        $response->assertSee('value="'.$this->brand->BrandID.'" data-category', false);
    }

    public function test_updating_a_products_brand_to_one_from_a_different_category_is_rejected(): void
    {
        $product = Product::create([
            'ProductName' => 'Rebrand Me', 'Model' => 'RM-001', 'Barcode' => 'BARCODE-RM-001',
            'CostPrice' => 500, 'Price' => 700, 'CategoryID' => $this->otherCategory->CategoryID,
        ]);

        // $this->brand belongs to $this->category, but the product stays in
        // $this->otherCategory -- the mismatch must be rejected.
        $response = $this->actingAs($this->admin)->put(route('admin.products.update', $product), [
            'ProductName' => 'Rebrand Me', 'Model' => 'RM-001', 'Barcode' => 'BARCODE-RM-001',
            'CostPrice' => 500, 'Price' => 700, 'CategoryID' => $this->otherCategory->CategoryID, 'BrandID' => $this->brand->BrandID,
        ]);

        $response->assertSessionHasErrors('BrandID');
        $this->assertDatabaseHas('Product', ['ProductID' => $product->ProductID, 'BrandID' => null]);
    }

    public function test_updating_a_products_brand_to_one_from_the_matching_category_persists(): void
    {
        $product = Product::create([
            'ProductName' => 'Rebrand Me Too', 'Model' => 'RM-002', 'Barcode' => 'BARCODE-RM-002',
            'CostPrice' => 500, 'Price' => 700, 'CategoryID' => $this->category->CategoryID,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.products.update', $product), [
            'ProductName' => 'Rebrand Me Too', 'Model' => 'RM-002', 'Barcode' => 'BARCODE-RM-002',
            'CostPrice' => 500, 'Price' => 700, 'CategoryID' => $this->category->CategoryID, 'BrandID' => $this->brand->BrandID,
        ]);

        $response->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('Product', ['ProductID' => $product->ProductID, 'BrandID' => $this->brand->BrandID]);
    }
}
