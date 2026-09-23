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

    public function test_admin_can_create_a_brand_assigned_to_a_category(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.brands.store'), [
            'BrandName' => 'Samsung', 'CategoryID' => $this->tv->CategoryID,
        ]);

        $response->assertRedirect(route('admin.brands.index'));
        $this->assertDatabaseHas('Brand', ['BrandName' => 'Samsung', 'CategoryID' => $this->tv->CategoryID]);
    }

    public function test_store_rejects_an_exact_duplicate_brand_under_the_same_category(): void
    {
        Brand::create(['BrandName' => 'Samsung', 'CategoryID' => $this->tv->CategoryID]);

        $response = $this->actingAs($this->admin)->post(route('admin.brands.store'), [
            'BrandName' => 'Samsung', 'CategoryID' => $this->tv->CategoryID,
        ]);

        $response->assertSessionHasErrors('BrandName');
        $this->assertSame(1, Brand::where('BrandName', 'Samsung')->where('CategoryID', $this->tv->CategoryID)->count());
    }

    // The exact scenario from the spec: Samsung / SAMSUNG / samsung / SamSung
    // under TV must all collide with the existing "Samsung" row.
    public function test_store_rejects_case_insensitive_variations_of_an_existing_brand(): void
    {
        Brand::create(['BrandName' => 'Samsung', 'CategoryID' => $this->tv->CategoryID]);

        foreach (['SAMSUNG', 'samsung', 'SamSung', '  Samsung  '] as $variant) {
            $response = $this->actingAs($this->admin)->post(route('admin.brands.store'), [
                'BrandName' => $variant, 'CategoryID' => $this->tv->CategoryID,
            ]);

            $response->assertSessionHasErrors('BrandName');
            $response->assertSessionHasErrors([
                'BrandName' => 'This brand already exists under the selected category.',
            ]);
        }

        $this->assertSame(1, Brand::where('CategoryID', $this->tv->CategoryID)->count());
    }

    public function test_the_same_brand_name_is_allowed_under_a_different_category(): void
    {
        Brand::create(['BrandName' => 'Samsung', 'CategoryID' => $this->tv->CategoryID]);

        // Samsung under DVR is a different Brand+Category combination.
        $response = $this->actingAs($this->admin)->post(route('admin.brands.store'), [
            'BrandName' => 'Samsung', 'CategoryID' => $this->dvr->CategoryID,
        ]);

        $response->assertRedirect(route('admin.brands.index'));
        $this->assertSame(1, Brand::where('CategoryID', $this->tv->CategoryID)->count());
        $this->assertSame(1, Brand::where('CategoryID', $this->dvr->CategoryID)->count());
    }

    public function test_update_rejects_renaming_into_an_existing_duplicate_combination(): void
    {
        Brand::create(['BrandName' => 'LG', 'CategoryID' => $this->tv->CategoryID]);
        $sony = Brand::create(['BrandName' => 'Sony', 'CategoryID' => $this->tv->CategoryID]);

        $response = $this->actingAs($this->admin)->put(route('admin.brands.update', $sony), [
            'BrandName' => 'lg', 'CategoryID' => $this->tv->CategoryID,
        ]);

        $response->assertSessionHasErrors('BrandName');
        $sony->refresh();
        $this->assertSame('Sony', $sony->BrandName);
    }

    public function test_update_rejects_moving_into_an_existing_duplicate_under_a_different_category(): void
    {
        Brand::create(['BrandName' => 'Dahua', 'CategoryID' => $this->dvr->CategoryID]);
        $movable = Brand::create(['BrandName' => 'Dahua', 'CategoryID' => $this->tv->CategoryID]);

        $response = $this->actingAs($this->admin)->put(route('admin.brands.update', $movable), [
            'BrandName' => 'Dahua', 'CategoryID' => $this->dvr->CategoryID,
        ]);

        $response->assertSessionHasErrors('BrandName');
        $movable->refresh();
        $this->assertSame($this->tv->CategoryID, $movable->CategoryID);
    }

    public function test_update_allows_keeping_a_brands_own_current_name_and_category(): void
    {
        $brand = Brand::create(['BrandName' => 'Sony', 'CategoryID' => $this->tv->CategoryID]);

        $response = $this->actingAs($this->admin)->put(route('admin.brands.update', $brand), [
            'BrandName' => 'Sony', 'CategoryID' => $this->tv->CategoryID,
        ]);

        $response->assertRedirect(route('admin.brands.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_update_allows_moving_a_brand_to_a_different_category_when_not_a_duplicate_there(): void
    {
        $brand = Brand::create(['BrandName' => 'Panther', 'CategoryID' => $this->tv->CategoryID]);

        $response = $this->actingAs($this->admin)->put(route('admin.brands.update', $brand), [
            'BrandName' => 'Panther', 'CategoryID' => $this->dvr->CategoryID,
        ]);

        $response->assertRedirect(route('admin.brands.index'));
        $brand->refresh();
        $this->assertSame($this->dvr->CategoryID, $brand->CategoryID);
    }

    public function test_destroy_is_blocked_while_the_brand_still_has_products(): void
    {
        $brand = Brand::create(['BrandName' => 'Hikvision', 'CategoryID' => $this->dvr->CategoryID]);
        Product::create([
            'ProductName' => 'DVR Unit', 'Model' => 'DV-01', 'Barcode' => 'BAR-DV-01',
            'CostPrice' => 100, 'Price' => 150, 'CategoryID' => $this->dvr->CategoryID, 'BrandID' => $brand->BrandID,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.brands.destroy', $brand));

        $response->assertRedirect(route('admin.brands.index'));
        $this->assertDatabaseHas('Brand', ['BrandID' => $brand->BrandID]);
    }

    public function test_destroy_succeeds_once_no_products_reference_the_brand(): void
    {
        $brand = Brand::create(['BrandName' => 'Omni', 'CategoryID' => $this->dvr->CategoryID]);

        $response = $this->actingAs($this->admin)->delete(route('admin.brands.destroy', $brand));

        $response->assertRedirect(route('admin.brands.index'));
        $this->assertDatabaseMissing('Brand', ['BrandID' => $brand->BrandID]);
    }

    public function test_show_returns_brand_details_with_its_category_and_products(): void
    {
        $brand = Brand::create(['BrandName' => 'LG', 'CategoryID' => $this->tv->CategoryID]);
        Product::create([
            'ProductName' => 'LG Smart TV', 'Model' => 'LG-01', 'Barcode' => 'BAR-LG-01',
            'CostPrice' => 100, 'Price' => 150, 'CategoryID' => $this->tv->CategoryID, 'BrandID' => $brand->BrandID,
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get(route('admin.brands.show', $brand));

        $response->assertOk();
        $response->assertJsonPath('brand.BrandName', 'LG');
        $response->assertJsonPath('brand.CategoryName', 'TV');
        $response->assertJsonPath('brand.ProductCount', 1);
        $response->assertJsonFragment(['Products' => ['LG Smart TV']]);
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
