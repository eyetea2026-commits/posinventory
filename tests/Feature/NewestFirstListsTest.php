<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Every list shows the most recently added record on top. Each test adds an
// "Alpha" record first and a "Zulu" record second, so alphabetical order and
// newest-first order disagree: Zulu must come before Alpha.
class NewestFirstListsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);
        $this->category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
    }

    private function makeProduct(string $name): Product
    {
        $product = Product::create([
            'ProductName' => $name, 'Model' => $name.' Model', 'Barcode' => 'BC-'.$name,
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $this->category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => 100, 'ReorderThreshold' => 10, 'Status' => 'Available']);

        return $product;
    }

    public function test_products_list_shows_the_newest_product_first(): void
    {
        $this->makeProduct('Alpha Camera');
        $this->makeProduct('Zulu Camera');

        $this->actingAs($this->admin)->get(route('admin.products.index'))
            ->assertOk()->assertSeeInOrder(['Zulu Camera', 'Alpha Camera']);
    }

    public function test_inventory_list_shows_the_newest_product_first(): void
    {
        $this->makeProduct('Alpha Camera');
        $this->makeProduct('Zulu Camera');

        $this->actingAs($this->admin)->get(route('admin.inventory.index'))
            ->assertOk()->assertSeeInOrder(['Zulu Camera', 'Alpha Camera']);
    }

    public function test_pos_product_grid_shows_the_newest_product_first(): void
    {
        $this->makeProduct('Alpha Camera');
        $this->makeProduct('Zulu Camera');

        $this->actingAs($this->cashier)->get(route('cashier.pos'))
            ->assertOk()->assertSeeInOrder(['Zulu Camera', 'Alpha Camera']);
    }

    public function test_suppliers_list_shows_the_newest_supplier_first(): void
    {
        Supplier::create(['SupplierName' => 'Alpha Supplies', 'ContactNumber' => '0001', 'Email' => 'alpha@example.com', 'Address' => 'N/A']);
        Supplier::create(['SupplierName' => 'Zulu Supplies', 'ContactNumber' => '0002', 'Email' => 'zulu@example.com', 'Address' => 'N/A']);

        $this->actingAs($this->admin)->get(route('admin.suppliers.index'))
            ->assertOk()->assertSeeInOrder(['Zulu Supplies', 'Alpha Supplies']);
    }

    public function test_users_list_shows_the_newest_account_first(): void
    {
        $cashierRole = $this->cashier->role_id;
        User::factory()->create(['name' => 'alpha_user', 'role_id' => $cashierRole]);
        User::factory()->create(['name' => 'zulu_user', 'role_id' => $cashierRole]);

        $this->actingAs($this->admin)->get(route('admin.users.index'))
            ->assertOk()->assertSeeInOrder(['zulu_user', 'alpha_user']);
    }

    public function test_administrator_accounts_stay_on_top_of_the_users_list(): void
    {
        $this->admin->update(['name' => 'Administrator']);
        // Cashiers added after the admin would otherwise be listed above it.
        User::factory()->create(['name' => 'alpha_user', 'role_id' => $this->cashier->role_id]);
        User::factory()->create(['name' => 'zulu_user', 'role_id' => $this->cashier->role_id]);

        $this->actingAs($this->admin)->get(route('admin.users.index'))
            ->assertOk()->assertSeeInOrder(['Administrator', 'zulu_user', 'alpha_user']);
    }

    public function test_stock_adjustments_list_follows_when_recorded_not_the_adjustment_date(): void
    {
        $alpha = $this->makeProduct('Alpha Camera');
        $zulu = $this->makeProduct('Zulu Camera');
        // The newer record carries an older adjustment date; it still goes on top.
        StockAdjustment::create(['ProductID' => $alpha->ProductID, 'QuantityAdjust' => -1, 'Reason' => StockAdjustment::REASON_LOST, 'Date' => '2026-10-10']);
        StockAdjustment::create(['ProductID' => $zulu->ProductID, 'QuantityAdjust' => -1, 'Reason' => StockAdjustment::REASON_LOST, 'Date' => '2026-10-01']);

        $this->actingAs($this->admin)->get(route('admin.stock-adjustments.index'))
            ->assertOk()->assertSeeInOrder(['Zulu Camera', 'Alpha Camera']);
    }

    public function test_category_brand_chips_show_the_newest_brand_first(): void
    {
        Brand::create(['BrandName' => 'Alpha Brand', 'CategoryID' => $this->category->CategoryID]);
        Brand::create(['BrandName' => 'Zulu Brand', 'CategoryID' => $this->category->CategoryID]);

        $this->actingAs($this->admin)->get(route('admin.categories.edit', $this->category))
            ->assertOk()->assertSeeInOrder(['Zulu Brand', 'Alpha Brand']);
    }

    public function test_newly_added_brand_chips_are_placed_first(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.categories.edit', $this->category))->assertOk()->getContent();

        $this->assertStringContainsString('list.prepend(makePendingBrandChip(brandName));', $html);
        $this->assertStringContainsString('list.prepend(makeSavedBrandChip(data.brand));', $html);
    }
}
