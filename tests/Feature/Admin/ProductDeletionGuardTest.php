<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\ProductSupplier;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// ProductController::destroy() blocks deletion of a Product with dependent
// records, since every one of those tables has an ON DELETE CASCADE foreign
// key to Product -- skipping any of them here would let a "delete" silently
// wipe that history instead of blocking it with a friendly message (audit
// finding F14: the guard originally checked 6 relations but missed
// ProductSupplier, ProductCostHistory, and DiscountProduct).
class ProductDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
    }

    private function makeProduct(): Product
    {
        return Product::create([
            'ProductName' => 'Test Camera', 'Model' => 'CAM-X'.uniqid(),
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $this->category->CategoryID,
        ]);
    }

    public function test_deletion_is_blocked_when_a_product_supplier_link_exists(): void
    {
        $product = $this->makeProduct();
        $supplier = Supplier::create([
            'SupplierName' => 'Test Supplier', 'ContactNumber' => '0000',
            'Email' => 'supplier@example.com', 'Address' => 'Test Address',
        ]);
        ProductSupplier::create(['ProductID' => $product->ProductID, 'SupplierID' => $supplier->SupplierID, 'CostPrice' => 500]);

        $response = $this->actingAs($this->admin)->delete(route('admin.products.destroy', $product->ProductID));

        $response->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('Product', ['ProductID' => $product->ProductID]);
    }

    public function test_deletion_is_blocked_when_a_product_cost_history_record_exists(): void
    {
        $product = $this->makeProduct();
        ProductCostHistory::create([
            'ProductID' => $product->ProductID, 'OldCostPrice' => 500, 'NewCostPrice' => 600,
            'ChangedAt' => now(), 'Source' => ProductCostHistory::SOURCE_PRODUCT_UPDATE,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.products.destroy', $product->ProductID));

        $response->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('Product', ['ProductID' => $product->ProductID]);
    }

    public function test_deletion_is_blocked_when_a_discount_is_assigned_to_the_product(): void
    {
        $product = $this->makeProduct();
        $discount = Discount::create(['Name' => 'Test Promo', 'DiscountRate' => 10]);
        DB::table('discountproduct')->insert([
            'DiscountID' => $discount->DiscountID, 'ProductID' => $product->ProductID,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.products.destroy', $product->ProductID));

        $response->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('Product', ['ProductID' => $product->ProductID]);
    }

    public function test_a_product_with_no_dependent_records_can_still_be_deleted(): void
    {
        $product = $this->makeProduct();

        $response = $this->actingAs($this->admin)->delete(route('admin.products.destroy', $product->ProductID));

        $response->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseMissing('Product', ['ProductID' => $product->ProductID]);
    }
}
