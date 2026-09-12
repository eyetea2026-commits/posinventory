<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryModuleUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);

        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $this->product = Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01', 'SKU' => 'SKU-001',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 20, 'ReorderThreshold' => 5, 'Status' => 'Available']);
    }

    public function test_reset_button_is_removed_but_search_still_works(): void
    {
        $indexResponse = $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $indexResponse->assertOk();
        $indexResponse->assertDontSee('>Reset<', false);
        $indexResponse->assertDontSee('fa-undo', false);

        $searchResponse = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['search' => 'DVR Camera']));
        $searchResponse->assertOk();
        $searchResponse->assertSee('DVR Camera');

        $noMatchResponse = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['search' => 'Nonexistent Product XYZ']));
        $noMatchResponse->assertOk();
        $noMatchResponse->assertDontSee('DVR Camera');
    }

    // "View Details" now opens a modal instead of navigating — the list's
    // link calls window.openInventoryDetailsModal() via onclick, which
    // fetches this same route over AJAX.
    public function test_view_details_link_opens_a_modal_instead_of_navigating(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));

        $response->assertOk();
        $response->assertSee('openInventoryDetailsModal(' . $this->product->ProductID . ')', false);
    }

    public function test_show_ajax_request_returns_the_details_partial_as_json(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get(route('admin.inventory.show', $this->product));

        $response->assertOk();
        $response->assertJsonStructure(['html', 'productName']);
        $response->assertJsonPath('productName', 'DVR Camera');
        $this->assertStringContainsString('DVR Camera', $response->json('html'));
        $this->assertStringContainsString('SKU-001', $response->json('html'));
    }

    // The "Create Purchase Order" button used to show inside View Details
    // for a low-stock product — removed on request; the row-level reorder
    // trigger elsewhere on the Inventory list (AutomaticReorderTest) is a
    // separate, untouched entry point.
    public function test_view_details_no_longer_offers_create_purchase_order_even_when_low_stock(): void
    {
        Inventory::where('ProductID', $this->product->ProductID)->update(['Quantity' => 1, 'ReorderThreshold' => 5]);

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get(route('admin.inventory.show', $this->product));

        $response->assertOk();
        $this->assertStringNotContainsString('Create Purchase Order', $response->json('html'));
        $this->assertStringNotContainsString('openReorderModal', $response->json('html'));
    }

    public function test_show_direct_navigation_still_returns_the_full_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.show', $this->product));

        $response->assertOk();
        $response->assertSee('Inventory Details');
        $response->assertSee('DVR Camera');
        $response->assertSee('Back to Inventory');
    }
}
