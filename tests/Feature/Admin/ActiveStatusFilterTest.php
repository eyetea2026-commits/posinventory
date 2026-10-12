<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Products and Categories: All / Active / Deactivated filter, deactivated
// records listed last, and categories deactivated instead of deleted.
class ActiveStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
    }

    private function makeProduct(string $name, bool $active = true): Product
    {
        $product = Product::create([
            'ProductName' => $name, 'Model' => $name.' M', 'Barcode' => 'BC-'.$name,
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $this->category->CategoryID, 'IsActive' => $active,
        ]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => 5, 'Status' => 'Available']);

        return $product;
    }

    // ---- Products ----

    public function test_products_list_has_the_status_filter(): void
    {
        $this->actingAs($this->admin)->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('id="statusFilter"', false)
            ->assertSeeInOrder(['All Statuses', 'Active', 'Deactivated']);
    }

    public function test_products_filter_shows_only_active_or_only_deactivated(): void
    {
        $this->makeProduct('Active Camera');
        $this->makeProduct('Retired Camera', false);

        $this->actingAs($this->admin)->get(route('admin.products.index', ['status' => 'active']))
            ->assertSee('Active Camera')->assertDontSee('Retired Camera');
        $this->actingAs($this->admin)->get(route('admin.products.index', ['status' => 'inactive']))
            ->assertSee('Retired Camera')->assertDontSee('Active Camera');
        $this->actingAs($this->admin)->get(route('admin.products.index'))
            ->assertSee('Active Camera')->assertSee('Retired Camera');
    }

    public function test_a_deactivated_product_moves_to_the_end_of_the_list(): void
    {
        $older = $this->makeProduct('Alpha Camera');
        $this->makeProduct('Zulu Camera');

        // Newest first while both are active...
        $this->actingAs($this->admin)->get(route('admin.products.index'))->assertSeeInOrder(['Zulu Camera', 'Alpha Camera']);

        // ...but once deactivated, the newest drops below the active one.
        $zulu = Product::where('ProductName', 'Zulu Camera')->first();
        $this->actingAs($this->admin)->from(route('admin.products.index', ['status' => 'active']))
            ->patch(route('admin.products.toggle-status', $zulu))
            ->assertRedirect(route('admin.products.index', ['status' => 'active']));

        $this->actingAs($this->admin)->get(route('admin.products.index'))->assertSeeInOrder(['Alpha Camera', 'Zulu Camera']);
        $this->assertTrue($older->fresh()->IsActive);
    }

    // ---- Categories ----

    public function test_category_rows_offer_deactivate_instead_of_delete(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.categories.index'))->assertOk()->getContent();

        $this->assertStringContainsString(route('admin.categories.toggle-status', $this->category), $html);
        $this->assertStringContainsString('confirmToggleCategoryStatus(', $html);
        $this->assertStringNotContainsString('title="Delete"', $html);
        $this->assertStringContainsString('id="statusFilter"', $html);
    }

    public function test_category_can_be_deactivated_and_reactivated_even_with_products(): void
    {
        $this->makeProduct('Dome Camera');

        $this->actingAs($this->admin)->patch(route('admin.categories.toggle-status', $this->category));
        $this->assertFalse($this->category->fresh()->IsActive);
        $this->assertDatabaseHas('Product', ['ProductName' => 'Dome Camera', 'CategoryID' => $this->category->CategoryID]);

        $this->actingAs($this->admin)->patch(route('admin.categories.toggle-status', $this->category));
        $this->assertTrue($this->category->fresh()->IsActive);
    }

    public function test_categories_filter_and_deactivated_ones_go_last(): void
    {
        $older = Category::create(['CategoryName' => 'Alpha Cables', 'Description' => 'x']);
        $newer = Category::create(['CategoryName' => 'Zulu Storage', 'Description' => 'x']);
        $newer->update(['IsActive' => false]);

        $this->actingAs($this->admin)->get(route('admin.categories.index'))
            ->assertSeeInOrder(['Alpha Cables', 'Zulu Storage']);
        $this->actingAs($this->admin)->get(route('admin.categories.index', ['status' => 'active']))
            ->assertSee('Alpha Cables')->assertDontSee('Zulu Storage');
        $this->actingAs($this->admin)->get(route('admin.categories.index', ['status' => 'inactive']))
            ->assertSee('Zulu Storage')->assertDontSee('Alpha Cables');
    }

    public function test_deactivated_category_is_not_offered_for_new_products(): void
    {
        Category::create(['CategoryName' => 'Retired Category', 'Description' => 'x', 'IsActive' => false]);

        $this->actingAs($this->admin)->get(route('admin.products.create'))
            ->assertOk()->assertSee('CCTV')->assertDontSee('Retired Category');

        $retired = Category::where('CategoryName', 'Retired Category')->first();
        $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'ProductName' => 'New Item', 'Model' => 'NI-1', 'Barcode' => 'BC-NEW', 'CostPrice' => 100, 'CategoryID' => $retired->CategoryID,
        ])->assertSessionHasErrors('CategoryID');
        $this->assertDatabaseMissing('Product', ['ProductName' => 'New Item']);
    }

    public function test_a_product_already_in_a_deactivated_category_keeps_it_when_edited(): void
    {
        $product = $this->makeProduct('Dome Camera');
        $this->category->update(['IsActive' => false]);

        $this->actingAs($this->admin)->put(route('admin.products.update', $product), [
            'ProductName' => 'Dome Camera Renamed', 'Model' => $product->Model, 'Barcode' => $product->Barcode,
            'CostPrice' => 600, 'Price' => 1000, 'CategoryID' => $this->category->CategoryID,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('Product', ['ProductID' => $product->ProductID, 'ProductName' => 'Dome Camera Renamed']);
    }
}
