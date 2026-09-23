<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $tv;

    private Category $dvr;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->tv = Category::create(['CategoryName' => 'TV']);
        $this->dvr = Category::create(['CategoryName' => 'DVR']);
    }

    // The Edit Category modal's "Brands in this Category" panel posts here.
    public function test_admin_can_add_a_brand_to_a_category_from_the_edit_category_form(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('admin.categories.brands.store', $this->tv), ['BrandName' => 'Samsung']);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('brand.BrandName', 'Samsung');
        $this->assertDatabaseHas('Brand', ['BrandName' => 'Samsung', 'CategoryID' => $this->tv->CategoryID]);
    }

    public function test_store_rejects_an_exact_duplicate_brand_under_the_same_category(): void
    {
        Brand::create(['BrandName' => 'Samsung', 'CategoryID' => $this->tv->CategoryID]);

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('admin.categories.brands.store', $this->tv), ['BrandName' => 'Samsung']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('BrandName');
        $this->assertSame(1, Brand::where('BrandName', 'Samsung')->where('CategoryID', $this->tv->CategoryID)->count());
    }

    // The exact scenario from the spec: Samsung / SAMSUNG / samsung / SamSung
    // under TV must all collide with the existing "Samsung" row.
    public function test_store_rejects_case_insensitive_variations_of_an_existing_brand(): void
    {
        Brand::create(['BrandName' => 'Samsung', 'CategoryID' => $this->tv->CategoryID]);

        foreach (['SAMSUNG', 'samsung', 'SamSung', '  Samsung  '] as $variant) {
            $response = $this->actingAs($this->admin)
                ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
                ->post(route('admin.categories.brands.store', $this->tv), ['BrandName' => $variant]);

            $response->assertStatus(422);
            $response->assertJsonFragment(['BrandName' => ['This brand already exists under the selected category.']]);
        }

        $this->assertSame(1, Brand::where('CategoryID', $this->tv->CategoryID)->count());
    }

    public function test_the_same_brand_name_is_allowed_under_a_different_category(): void
    {
        Brand::create(['BrandName' => 'Samsung', 'CategoryID' => $this->tv->CategoryID]);

        // Samsung under DVR is a different Brand+Category combination.
        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('admin.categories.brands.store', $this->dvr), ['BrandName' => 'Samsung']);

        $response->assertOk();
        $this->assertSame(1, Brand::where('CategoryID', $this->tv->CategoryID)->count());
        $this->assertSame(1, Brand::where('CategoryID', $this->dvr->CategoryID)->count());
    }

    public function test_destroy_is_blocked_while_the_brand_still_has_products(): void
    {
        $brand = Brand::create(['BrandName' => 'Hikvision', 'CategoryID' => $this->dvr->CategoryID]);
        Product::create([
            'ProductName' => 'DVR Unit', 'Model' => 'DV-01', 'Barcode' => 'BAR-DV-01',
            'CostPrice' => 100, 'Price' => 150, 'CategoryID' => $this->dvr->CategoryID, 'BrandID' => $brand->BrandID,
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->delete(route('admin.brands.destroy', $brand));

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertDatabaseHas('Brand', ['BrandID' => $brand->BrandID]);
    }

    public function test_destroy_succeeds_once_no_products_reference_the_brand(): void
    {
        $brand = Brand::create(['BrandName' => 'Omni', 'CategoryID' => $this->dvr->CategoryID]);

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->delete(route('admin.brands.destroy', $brand));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseMissing('Brand', ['BrandID' => $brand->BrandID]);
    }

    // Category deletion must also account for brands, not just products.
    public function test_category_destroy_is_blocked_while_it_still_has_brands(): void
    {
        Brand::create(['BrandName' => 'Samsung', 'CategoryID' => $this->tv->CategoryID]);

        $response = $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $this->tv));

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('Category', ['CategoryID' => $this->tv->CategoryID]);
    }

    public function test_edit_category_form_lists_its_existing_brands(): void
    {
        Brand::create(['BrandName' => 'Samsung', 'CategoryID' => $this->tv->CategoryID]);
        Brand::create(['BrandName' => 'LG', 'CategoryID' => $this->tv->CategoryID]);

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get(route('admin.categories.edit', $this->tv));

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('Samsung', $html);
        $this->assertStringContainsString('LG', $html);
        $this->assertStringContainsString('data-category-id="'.$this->tv->CategoryID.'"', $html);
    }

    public function test_add_category_form_shows_the_brands_panel(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.categories.create'));

        $response->assertOk();
        $response->assertSee('Brands in this Category');
        // No CategoryID exists yet -- the panel starts empty, driven purely
        // client-side until the whole Create Category form is submitted.
        $response->assertSee('data-category-id=""', false);
    }

    // The Add Category form's "Brands in this Category" panel collects
    // plain names client-side (see category-brands-behavior.blade.php) and
    // submits them as Brands[] alongside CategoryName/Description.
    public function test_creating_a_category_also_creates_the_brands_submitted_with_it(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.categories.store'), [
            'CategoryName' => 'AVR',
            'Brands' => ['Panther', 'Omni'],
        ]);

        $response->assertRedirect(route('admin.categories.index'));
        $category = Category::where('CategoryName', 'AVR')->firstOrFail();
        $this->assertDatabaseHas('Brand', ['BrandName' => 'Panther', 'CategoryID' => $category->CategoryID]);
        $this->assertDatabaseHas('Brand', ['BrandName' => 'Omni', 'CategoryID' => $category->CategoryID]);
    }

    public function test_creating_a_category_with_no_brands_still_works(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.categories.store'), [
            'CategoryName' => 'Empty Category',
        ]);

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('Category', ['CategoryName' => 'Empty Category']);
    }

    // The client already blocks adding the same name twice, but the server
    // must not trust that alone.
    public function test_creating_a_category_de_duplicates_case_insensitive_brand_names_server_side(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.categories.store'), [
            'CategoryName' => 'AVR',
            'Brands' => ['Panther', 'PANTHER', 'panther'],
        ]);

        $response->assertRedirect(route('admin.categories.index'));
        $category = Category::where('CategoryName', 'AVR')->firstOrFail();
        $this->assertSame(1, Brand::where('CategoryID', $category->CategoryID)->count());
    }

    // Database-level protection: the unique index on (BrandNameNormalized,
    // CategoryID) must reject a case-insensitive duplicate even when
    // created directly through the model -- i.e. it holds regardless of
    // whether the app-level check in BrandController ever runs at all,
    // which is exactly what a raw script, a future API endpoint, or a
    // genuinely concurrent request would bypass.
    public function test_database_constraint_rejects_a_case_insensitive_duplicate_even_bypassing_the_controller(): void
    {
        Brand::create(['BrandName' => 'Samsung', 'CategoryID' => $this->tv->CategoryID]);

        $this->expectException(QueryException::class);

        Brand::create(['BrandName' => 'SAMSUNG', 'CategoryID' => $this->tv->CategoryID]);
    }
}
