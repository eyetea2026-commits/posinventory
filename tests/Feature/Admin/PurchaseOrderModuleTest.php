<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseOrderModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_order_item_has_no_unit_price_column(): void
    {
        $this->assertFalse(Schema::hasColumn('PurchaseOrderItem', 'UnitPrice'));
        $this->assertTrue(Schema::hasColumn('PurchaseOrder', 'ExpectedDeliveryDate'));
    }

    public function test_store_creates_purchase_order_without_unit_price(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $supplier = Supplier::create([
            'SupplierName' => 'Acme Supplies', 'ContactNumber' => '0000', 'Email' => 'acme@example.com', 'Address' => 'N/A',
        ]);
        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $product = Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01', 'SKU' => 'SKU-001',
            'Price' => 1000, 'CategoryID' => $category->CategoryID,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.purchase-orders.store'), [
            'SupplierID' => $supplier->SupplierID,
            'PurchaseDate' => now()->format('Y-m-d'),
            'ExpectedDeliveryDate' => now()->addDays(7)->format('Y-m-d'),
            'Status' => 'pending',
            'products' => [
                ['product_id' => $product->ProductID, 'quantity' => 5],
            ],
        ]);

        $response->assertRedirect(route('admin.purchase-orders.index'));
        $this->assertDatabaseHas('PurchaseOrderItem', ['ProductID' => $product->ProductID, 'Quantity' => 5]);
        $this->assertDatabaseHas('PurchaseOrder', ['SupplierID' => $supplier->SupplierID, 'Status' => 'pending']);
    }
}
