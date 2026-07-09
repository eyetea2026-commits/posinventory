<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleDisplayAndFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create([
            'name' => 'admin',
            'email' => 'admin@example.com',
            'role_id' => $role->id,
        ]);
    }

    public function test_category_index_displays_description_below_category_name(): void
    {
        Category::create([
            'CategoryName' => 'CCTV',
            'Description' => 'Security cameras and surveillance equipment.',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.categories.index'));

        $response->assertStatus(200);
        $response->assertSee('CCTV');
        $response->assertSee('Security cameras and surveillance equipment.');
    }

    public function test_product_index_can_filter_products_by_category(): void
    {
        $cameraCategory = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $accessoryCategory = Category::create(['CategoryName' => 'Accessories', 'Description' => 'Accessories']);

        $cameraProduct = Product::create([
            'ProductName' => 'DVR Camera',
            'Model' => 'CAM-01',
            'SKU' => 'SKU-001',
            'Price' => 1500,
            'CategoryID' => $cameraCategory->CategoryID,
        ]);

        Product::create([
            'ProductName' => 'Cable',
            'Model' => 'CAB-01',
            'SKU' => 'SKU-002',
            'Price' => 200,
            'CategoryID' => $accessoryCategory->CategoryID,
        ]);

        Inventory::create([
            'ProductID' => $cameraProduct->ProductID,
            'Quantity' => 12,
            'Status' => 'Available',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.products.index', ['category_id' => $cameraCategory->CategoryID]));

        $response->assertStatus(200);
        $response->assertSee('DVR Camera');
        $response->assertDontSee('Cable');
        $response->assertSee('CCTV');
    }
}
