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

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);

        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $product = Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01', 'SKU' => 'SKU-001',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => 20, 'ReorderThreshold' => 5, 'Status' => 'Available']);
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
}
