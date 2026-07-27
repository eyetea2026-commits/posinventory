<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\ProductSupplier;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierProductRelationshipTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $product;
    private Supplier $supplierA;
    private Supplier $supplierB;

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

        $this->supplierA = Supplier::create(['SupplierName' => 'Supplier A', 'ContactNumber' => '1', 'Email' => 'a@example.com', 'Address' => 'N/A']);
        $this->supplierB = Supplier::create(['SupplierName' => 'Supplier B', 'ContactNumber' => '2', 'Email' => 'b@example.com', 'Address' => 'N/A']);
    }

    public function test_a_product_with_no_suppliers_resolves_to_null(): void
    {
        $this->assertNull($this->product->resolveReorderSupplier());
    }

    public function test_a_product_with_exactly_one_supplier_resolves_to_it(): void
    {
        $ps = ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplierA->SupplierID, 'CostPrice' => 500]);

        $resolved = $this->product->resolveReorderSupplier();

        $this->assertSame($ps->ProductSupplierID, $resolved->ProductSupplierID);
    }

    public function test_a_product_with_multiple_suppliers_and_no_preferred_resolves_to_null(): void
    {
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplierA->SupplierID, 'CostPrice' => 500]);
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplierB->SupplierID, 'CostPrice' => 550]);

        $this->assertNull($this->product->resolveReorderSupplier());
    }

    public function test_the_preferred_supplier_is_resolved_even_with_multiple_suppliers(): void
    {
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplierA->SupplierID, 'CostPrice' => 500]);
        $preferred = ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplierB->SupplierID, 'CostPrice' => 550, 'IsPreferred' => 1]);

        $resolved = $this->product->resolveReorderSupplier();

        $this->assertSame($preferred->ProductSupplierID, $resolved->ProductSupplierID);
    }

    public function test_only_one_supplier_can_be_preferred_per_product(): void
    {
        $psA = ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplierA->SupplierID, 'CostPrice' => 500, 'IsPreferred' => 1]);

        $this->expectException(QueryException::class);
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplierB->SupplierID, 'CostPrice' => 550, 'IsPreferred' => 1]);
    }

    public function test_mark_preferred_moves_the_flag_instead_of_violating_the_unique_constraint(): void
    {
        $psA = ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplierA->SupplierID, 'CostPrice' => 500, 'IsPreferred' => 1]);
        $psB = ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplierB->SupplierID, 'CostPrice' => 550]);

        $psB->markPreferred();

        $this->assertNull($psA->fresh()->IsPreferred);
        $this->assertEquals(1, $psB->fresh()->IsPreferred);
    }

    public function test_product_supplier_store_endpoint_logs_cost_history_on_change(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.products.suppliers.store', $this->product), [
            'SupplierID' => $this->supplierA->SupplierID,
            'CostPrice' => 500,
        ]);

        $this->assertDatabaseHas('ProductCostHistory', [
            'ProductID' => $this->product->ProductID,
            'SupplierID' => $this->supplierA->SupplierID,
            'NewCostPrice' => 500,
            'Source' => ProductCostHistory::SOURCE_SUPPLIER_PIVOT_UPDATE,
        ]);

        // Updating the same pair again with a different cost should log the old→new change.
        $this->actingAs($this->admin)->postJson(route('admin.products.suppliers.store', $this->product), [
            'SupplierID' => $this->supplierA->SupplierID,
            'CostPrice' => 550,
        ]);

        $this->assertDatabaseHas('ProductCostHistory', [
            'ProductID' => $this->product->ProductID,
            'SupplierID' => $this->supplierA->SupplierID,
            'OldCostPrice' => 500,
            'NewCostPrice' => 550,
        ]);
        $this->assertDatabaseCount('ProductSupplier', 1);
    }

    public function test_supplier_history_page_renders_transaction_history_only(): void
    {
        ProductSupplier::create(['ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplierA->SupplierID, 'CostPrice' => 500]);

        $response = $this->actingAs($this->admin)->get(route('admin.suppliers.show', $this->supplierA));

        $response->assertOk();
        $response->assertSee($this->supplierA->SupplierName);
        $response->assertSee('Transaction History');
        // The old profile-style sections should no longer be shown.
        $response->assertDontSee('Supplier Information');
        $response->assertDontSee('Total Purchase Orders');
        $response->assertDontSee('Receiving History');
        $response->assertDontSee('Product History');
    }
}
