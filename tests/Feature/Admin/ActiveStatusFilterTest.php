<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

// Products and Categories: no Active / Deactivated status anymore (stock
// decides whether the POS lists a product), newest first.
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

    public function test_products_list_has_no_status_filter(): void
    {
        $this->actingAs($this->admin)->get(route('admin.products.index'))
            ->assertOk()
            ->assertDontSee('id="statusFilter"', false)
            ->assertDontSee('All Statuses');
    }

    public function test_an_old_status_in_the_address_is_ignored(): void
    {
        $this->makeProduct('Active Camera');
        $this->makeProduct('Retired Camera', false);

        $this->actingAs($this->admin)->get(route('admin.products.index', ['status' => 'active']))
            ->assertSee('Active Camera')->assertSee('Retired Camera');
        $this->actingAs($this->admin)->get(route('admin.products.index', ['status' => 'inactive']))
            ->assertSee('Active Camera')->assertSee('Retired Camera');
    }

    public function test_products_are_listed_newest_first(): void
    {
        $this->makeProduct('Alpha Camera');
        $this->makeProduct('Zulu Camera', false);

        $this->actingAs($this->admin)->get(route('admin.products.index'))->assertSeeInOrder(['Zulu Camera', 'Alpha Camera']);
    }

    // ---- Categories ----

    // The Deactivate/Activate button was removed from categories entirely
    // (and there's still no Delete): the row only offers Edit.
    public function test_category_rows_have_no_deactivate_or_delete_button(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.categories.index'))->assertOk()->getContent();
        preg_match('#<tbody id="categoriesTbody"[^>]*>(.*?)</tbody>#s', $html, $rows);

        $this->assertStringContainsString('openEditCategoryModal(event, '.$this->category->CategoryID.')', $rows[1]);
        $this->assertStringNotContainsString('Deactivate', $rows[1]);
        $this->assertStringNotContainsString('Activate', $rows[1]);
        $this->assertStringNotContainsString('confirmToggleCategoryStatus', $html);
        $this->assertStringNotContainsString('title="Delete"', $html);
    }

    public function test_category_status_can_no_longer_be_toggled(): void
    {
        $this->makeProduct('Dome Camera');

        $this->assertFalse(Route::has('admin.categories.toggle-status'));
        $this->actingAs($this->admin)->patch('/admin/categories/'.$this->category->CategoryID.'/toggle-status');

        $this->assertTrue($this->category->fresh()->IsActive);
        $this->assertDatabaseHas('Product', ['ProductName' => 'Dome Camera', 'CategoryID' => $this->category->CategoryID]);
    }

    // The category status filter was removed: no dropdown, and a ?status=
    // in the address no longer hides anything.
    public function test_categories_have_no_status_filter_and_list_every_category(): void
    {
        $older = Category::create(['CategoryName' => 'Alpha Cables', 'Description' => 'x']);
        $newer = Category::create(['CategoryName' => 'Zulu Storage', 'Description' => 'x']);
        $newer->update(['IsActive' => false]);

        $html = $this->actingAs($this->admin)->get(route('admin.categories.index'))
            ->assertSeeInOrder(['Alpha Cables', 'Zulu Storage'])
            ->getContent();
        $this->assertStringNotContainsString('id="statusFilter"', $html);
        $this->assertStringNotContainsString('All Statuses', $html);
        $this->assertStringNotContainsString('name="status"', $html);

        foreach (['active', 'inactive'] as $status) {
            $this->actingAs($this->admin)->get(route('admin.categories.index', ['status' => $status]))
                ->assertSee('Alpha Cables')->assertSee('Zulu Storage');
        }
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
