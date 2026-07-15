<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\DamagedProduct;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DamageModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $product;
    private Supplier $supplier;

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
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 10, 'Status' => 'Available']);

        $this->supplier = Supplier::create([
            'SupplierName' => 'Acme Supplies', 'ContactNumber' => '0000', 'Email' => 'acme@example.com', 'Address' => 'N/A',
        ]);
    }

    private function baseDamagePayload(array $overrides = []): array
    {
        return array_merge([
            'ProductID' => $this->product->ProductID,
            'SupplierID' => $this->supplier->SupplierID,
            'Quantity' => 2,
            'Description' => 'Cracked casing on arrival',
            'DateRecorded' => now()->format('Y-m-d'),
            'DamageType' => 'broken',
        ], $overrides);
    }

    public function test_store_deducts_inventory_and_logs_activity(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.damages.store'), $this->baseDamagePayload());

        $response->assertRedirect(route('admin.damages.index'));
        $this->assertSame(8, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
        $this->assertDatabaseHas('DamagedProduct', ['Quantity' => 2, 'Status' => DamagedProduct::STATUS_PENDING]);
        $this->assertTrue(ActivityLog::where('Action', 'damage.created')->exists());
    }

    public function test_edit_and_delete_are_blocked_once_not_pending(): void
    {
        $damage = DamagedProduct::create(array_merge($this->baseDamagePayload(), ['Status' => DamagedProduct::STATUS_FOR_SUPPLIER_RETURN]));

        $editResponse = $this->actingAs($this->admin)->put(route('admin.damages.update', $damage), $this->baseDamagePayload(['Quantity' => 5]));
        $editResponse->assertSessionHas('error');
        $this->assertSame(2, $damage->fresh()->Quantity);

        $deleteResponse = $this->actingAs($this->admin)->delete(route('admin.damages.destroy', $damage));
        $deleteResponse->assertSessionHas('error');
        $this->assertDatabaseHas('DamagedProduct', ['DamageID' => $damage->DamageID]);
    }

    public function test_delete_allowed_while_pending_and_restores_inventory(): void
    {
        $damage = DamagedProduct::create(array_merge($this->baseDamagePayload(), ['Status' => DamagedProduct::STATUS_PENDING]));
        Inventory::where('ProductID', $this->product->ProductID)->update(['Quantity' => 8]);

        $this->actingAs($this->admin)->delete(route('admin.damages.destroy', $damage));

        $this->assertSame(10, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
        // Soft delete: the row stays in the table (recoverable) but drops out of default queries.
        $this->assertDatabaseHas('DamagedProduct', ['DamageID' => $damage->DamageID]);
        $this->assertNotNull($damage->fresh()->deleted_at);
        $this->assertNull(DamagedProduct::find($damage->DamageID));
        $this->assertTrue(ActivityLog::where('Action', 'damage.deleted')->exists());
    }

    public function test_status_transitions_do_not_further_touch_inventory(): void
    {
        $this->actingAs($this->admin)->post(route('admin.damages.store'), $this->baseDamagePayload());
        $damage = DamagedProduct::first();
        $quantityAfterCreate = Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity;

        $this->actingAs($this->admin)->post(route('admin.damages.mark-supplier-return', $damage));
        $this->assertSame($quantityAfterCreate, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
        $this->assertSame(DamagedProduct::STATUS_FOR_SUPPLIER_RETURN, $damage->fresh()->Status);

        $this->actingAs($this->admin)->post(route('admin.damages.confirm-supplier-return', $damage));
        $this->assertSame($quantityAfterCreate, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
        $this->assertSame(DamagedProduct::STATUS_RETURNED_TO_SUPPLIER, $damage->fresh()->Status);
        $this->assertTrue(ActivityLog::where('Action', 'damage.returned_to_supplier')->exists());
    }

    public function test_dispose_transition_works_from_pending(): void
    {
        $damage = DamagedProduct::create(array_merge($this->baseDamagePayload(), ['Status' => DamagedProduct::STATUS_PENDING]));

        $this->actingAs($this->admin)->post(route('admin.damages.dispose', $damage));

        $this->assertSame(DamagedProduct::STATUS_DISPOSED, $damage->fresh()->Status);
        $this->assertTrue(ActivityLog::where('Action', 'damage.disposed')->exists());
    }

    public function test_index_shows_kpis(): void
    {
        DamagedProduct::create(array_merge($this->baseDamagePayload(), ['Status' => DamagedProduct::STATUS_DISPOSED]));

        $response = $this->actingAs($this->admin)->get(route('admin.damages.index'));

        $response->assertStatus(200);
        $response->assertSee('Total Damage Records');
        $response->assertSee('Total Damage Cost');
    }
}
