<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Models\Staff;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

// Products used to be deletable when they had no dependent records -- and
// every dependent table has an ON DELETE CASCADE foreign key to Product, so
// one missed relation in the guard meant silently wiping history (audit
// finding F14). Delete is gone entirely now: a product is deactivated
// instead, which keeps all of its history and hides it from the POS and
// from new Purchase Orders.
class ProductDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private Category $category;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $cashierRole = Role::create(['role_name' => 'cashier']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->cashier = User::factory()->create(['role_id' => $cashierRole->id]);
        $this->category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $this->supplier = Supplier::create([
            'SupplierName' => 'Test Supplier', 'ContactNumber' => '0000',
            'Email' => 'supplier@example.com', 'Address' => 'Test Address',
        ]);
    }

    private function makeProduct(string $name = 'Test Camera', bool $isActive = true, int $stock = 20): Product
    {
        $product = Product::create([
            'ProductName' => $name, 'Model' => 'CAM-X'.uniqid(),
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $this->category->CategoryID,
            'IsActive' => $isActive,
        ]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => $stock, 'ReorderThreshold' => 10, 'Status' => 'Available']);

        return $product;
    }

    public function test_new_products_are_active_by_default(): void
    {
        $product = Product::create([
            'ProductName' => 'Default Camera', 'Model' => 'CAM-DEF',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $this->category->CategoryID,
        ]);

        $this->assertTrue($product->fresh()->IsActive);
    }

    public function test_there_is_no_way_to_delete_a_product_anymore(): void
    {
        $product = $this->makeProduct();

        $this->assertFalse(Route::has('admin.products.destroy'));
        $this->actingAs($this->admin)->delete('/admin/products/'.$product->ProductID);

        $this->assertDatabaseHas('Product', ['ProductID' => $product->ProductID]);
    }

    public function test_deactivating_keeps_the_product_and_all_its_history(): void
    {
        $product = $this->makeProduct();
        ProductSupplier::create(['ProductID' => $product->ProductID, 'SupplierID' => $this->supplier->SupplierID, 'CostPrice' => 500]);
        $staff = Staff::create([
            'FirstName' => 'Jane', 'MiddleName' => '-', 'LastName' => 'Doe',
            'ContactNumber' => '0000', 'Email' => 'jane@example.com', 'Age' => 30, 'Gender' => 'F',
            'UserID' => $this->cashier->id,
        ]);
        $transaction = SalesTransaction::create(['CustomerName' => 'Walk-in Customer', 'SalesTransactionDate' => now(), 'StaffID' => $staff->StaffID]);
        SalesItem::create(['Quantity' => 1, 'UnitPrice' => 1000, 'ProductID' => $product->ProductID, 'SalesTransactionID' => $transaction->SalesTransactionID]);

        $response = $this->actingAs($this->admin)->patch(route('admin.products.toggle-status', $product));

        $response->assertRedirect(route('admin.products.index'));
        $this->assertFalse($product->fresh()->IsActive);
        $this->assertDatabaseHas('Product', ['ProductID' => $product->ProductID]);
        $this->assertDatabaseHas('ProductSupplier', ['ProductID' => $product->ProductID]);
        $this->assertDatabaseHas('SalesItem', ['ProductID' => $product->ProductID]);
        $this->assertDatabaseHas('Inventory', ['ProductID' => $product->ProductID]);
    }

    public function test_toggling_twice_reactivates_and_both_are_logged(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->admin)->patch(route('admin.products.toggle-status', $product));
        $this->actingAs($this->admin)->patch(route('admin.products.toggle-status', $product));

        $this->assertTrue($product->fresh()->IsActive);
        $this->assertTrue(ActivityLog::where('Action', 'product.deactivated')->exists());
        $this->assertTrue(ActivityLog::where('Action', 'product.activated')->exists());
    }

    public function test_cashier_cannot_toggle_a_products_status(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->cashier)->patch(route('admin.products.toggle-status', $product));

        $this->assertTrue($product->fresh()->IsActive);
    }

    public function test_product_list_shows_inactive_badge_and_toggle_instead_of_delete(): void
    {
        $this->makeProduct('Retired Camera', false);

        $this->actingAs($this->admin)->get(route('admin.products.index'))->assertOk();

        $rows = $this->actingAs($this->admin)->getJson(route('admin.products.index'))->json('rows');

        $this->assertStringContainsString('Retired Camera', $rows);
        $this->assertStringContainsString('product-status-badge', $rows);
        $this->assertStringContainsString('confirmToggleProductStatus', $rows);
        $this->assertStringNotContainsString('confirmDelete', $rows);
        $this->assertStringNotContainsString('value="DELETE"', $rows);
        $this->assertStringNotContainsString('fa-trash', $rows);
    }

    public function test_pos_screen_hides_inactive_products(): void
    {
        $this->makeProduct('Active Camera');
        $this->makeProduct('Retired Camera', false);

        $response = $this->actingAs($this->cashier)->get(route('cashier.pos'));

        $response->assertOk();
        $response->assertSee('Active Camera');
        $response->assertDontSee('Retired Camera');
    }

    public function test_checkout_rejects_an_inactive_product_from_a_stale_cart(): void
    {
        $product = $this->makeProduct('Retired Camera', false);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => 1000,
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $this->assertSame(20, Inventory::where('ProductID', $product->ProductID)->value('Quantity'));
        $this->assertSame(0, SalesItem::count());
    }

    public function test_purchase_order_picker_hides_inactive_products(): void
    {
        $this->makeProduct('Active Camera');
        $this->makeProduct('Retired Camera', false);

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.create'));

        $response->assertOk();
        $response->assertSee('Active Camera');
        $response->assertDontSee('Retired Camera');
    }

    public function test_a_new_purchase_order_cannot_include_an_inactive_product(): void
    {
        $product = $this->makeProduct('Retired Camera', false);

        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.store'), [
            'SupplierID' => $this->supplier->SupplierID,
            'PurchaseDate' => now()->toDateString(),
            'items' => [['product_id' => $product->ProductID, 'quantity' => 5, 'cost_price' => 600, 'supplier_id' => $this->supplier->SupplierID]],
        ]);

        $response->assertSessionHasErrors('items.0.product_id');
        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_reorder_form_refuses_an_inactive_product(): void
    {
        $product = $this->makeProduct('Retired Camera', false);

        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.store-from-reorder', $product), [
            'OrderQuantity' => 5, 'CostPrice' => 600, 'SupplierID' => $this->supplier->SupplierID,
        ]);

        $response->assertSessionHasErrors('OrderQuantity');
        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_auto_reorder_skips_inactive_products_even_when_out_of_stock(): void
    {
        $active = $this->makeProduct('Active Camera', true, 0);
        $retired = $this->makeProduct('Retired Camera', false, 0);
        ProductSupplier::create(['ProductID' => $active->ProductID, 'SupplierID' => $this->supplier->SupplierID, 'CostPrice' => 500]);
        ProductSupplier::create(['ProductID' => $retired->ProductID, 'SupplierID' => $this->supplier->SupplierID, 'CostPrice' => 500]);

        PurchaseOrderController::autoGenerateDraftPurchaseOrders();

        $this->assertDatabaseHas('PurchaseOrderItem', ['ProductID' => $active->ProductID]);
        $this->assertDatabaseMissing('PurchaseOrderItem', ['ProductID' => $retired->ProductID]);
    }
}
