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

    public function test_receive_replacement_increases_inventory_and_marks_status(): void
    {
        $damage = DamagedProduct::create(array_merge($this->baseDamagePayload(), [
            'Status' => DamagedProduct::STATUS_RETURNED_TO_SUPPLIER,
        ]));
        Inventory::where('ProductID', $this->product->ProductID)->update(['Quantity' => 8]);

        $response = $this->actingAs($this->admin)->post(route('admin.damages.receive-replacement', $damage));

        $response->assertSessionHas('success');
        $this->assertSame(10, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
        $this->assertSame(DamagedProduct::STATUS_REPLACEMENT_RECEIVED, $damage->fresh()->Status);
        $this->assertNotNull($damage->fresh()->ResolvedBy);
        $this->assertTrue(ActivityLog::where('Action', 'damage.replacement_received')->exists());
    }

    public function test_receive_replacement_honors_custom_quantity(): void
    {
        $damage = DamagedProduct::create(array_merge($this->baseDamagePayload(['Quantity' => 2]), [
            'Status' => DamagedProduct::STATUS_RETURNED_TO_SUPPLIER,
        ]));
        Inventory::where('ProductID', $this->product->ProductID)->update(['Quantity' => 8]);

        $this->actingAs($this->admin)->post(route('admin.damages.receive-replacement', $damage), [
            'replacement_quantity' => 5,
        ]);

        $this->assertSame(13, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
    }

    public function test_receive_replacement_rejected_unless_returned_to_supplier(): void
    {
        $damage = DamagedProduct::create(array_merge($this->baseDamagePayload(), [
            'Status' => DamagedProduct::STATUS_FOR_SUPPLIER_RETURN,
        ]));
        Inventory::where('ProductID', $this->product->ProductID)->update(['Quantity' => 8]);

        $response = $this->actingAs($this->admin)->post(route('admin.damages.receive-replacement', $damage));

        $response->assertSessionHas('error');
        $this->assertSame(8, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
        $this->assertSame(DamagedProduct::STATUS_FOR_SUPPLIER_RETURN, $damage->fresh()->Status);
    }

    public function test_cancel_restores_inventory_and_marks_cancelled(): void
    {
        $damage = DamagedProduct::create(array_merge($this->baseDamagePayload(['Quantity' => 3]), [
            'Status' => DamagedProduct::STATUS_FOR_SUPPLIER_RETURN,
        ]));
        Inventory::where('ProductID', $this->product->ProductID)->update(['Quantity' => 7]);

        $response = $this->actingAs($this->admin)->post(route('admin.damages.cancel', $damage));

        $response->assertSessionHas('success');
        $this->assertSame(10, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
        $this->assertSame(DamagedProduct::STATUS_CANCELLED, $damage->fresh()->Status);
        $this->assertTrue(ActivityLog::where('Action', 'damage.cancelled')->exists());
    }

    public function test_cancel_rejected_once_returned_to_supplier(): void
    {
        $damage = DamagedProduct::create(array_merge($this->baseDamagePayload(), [
            'Status' => DamagedProduct::STATUS_RETURNED_TO_SUPPLIER,
        ]));
        Inventory::where('ProductID', $this->product->ProductID)->update(['Quantity' => 8]);

        $response = $this->actingAs($this->admin)->post(route('admin.damages.cancel', $damage));

        $response->assertSessionHas('error');
        $this->assertSame(8, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
        $this->assertSame(DamagedProduct::STATUS_RETURNED_TO_SUPPLIER, $damage->fresh()->Status);
    }

    public function test_bulk_confirm_supplier_return_transitions_only_eligible_records(): void
    {
        $eligible1 = DamagedProduct::create(array_merge($this->baseDamagePayload(), ['Status' => DamagedProduct::STATUS_FOR_SUPPLIER_RETURN]));
        $eligible2 = DamagedProduct::create(array_merge($this->baseDamagePayload(), ['Status' => DamagedProduct::STATUS_FOR_SUPPLIER_RETURN]));
        $notEligible = DamagedProduct::create(array_merge($this->baseDamagePayload(), ['Status' => DamagedProduct::STATUS_PENDING]));

        $response = $this->actingAs($this->admin)->post(route('admin.damages.bulk-return-to-supplier'), [
            'damage_ids' => [$eligible1->DamageID, $eligible2->DamageID, $notEligible->DamageID],
        ]);

        $response->assertSessionHas('success');
        $this->assertSame(DamagedProduct::STATUS_RETURNED_TO_SUPPLIER, $eligible1->fresh()->Status);
        $this->assertSame(DamagedProduct::STATUS_RETURNED_TO_SUPPLIER, $eligible2->fresh()->Status);
        $this->assertSame(DamagedProduct::STATUS_PENDING, $notEligible->fresh()->Status);
        $this->assertSame(2, ActivityLog::where('Action', 'damage.returned_to_supplier')->count());
    }

    public function test_create_page_lists_only_return_originated_pending_supplier_return_records(): void
    {
        $cashierRole = Role::firstOrCreate(['role_name' => 'cashier']);
        $cashierUser = User::factory()->create(['role_id' => $cashierRole->id]);
        $staff = \App\Models\Staff::create([
            'FirstName' => 'Jane', 'MiddleName' => '-', 'LastName' => 'Doe',
            'ContactNumber' => '0000', 'Email' => 'jane.staff@example.com', 'Age' => 30, 'Gender' => 'F',
            'UserID' => $cashierUser->id,
        ]);
        $transaction = \App\Models\SalesTransaction::create([
            'CustomerName' => 'Jane Buyer',
            'SalesTransactionDate' => now(),
            'StaffID' => $staff->StaffID,
        ]);
        $salesReturn = \App\Models\SalesReturn::create([
            'SalesTransactionID' => $transaction->SalesTransactionID,
            'ProductID' => $this->product->ProductID,
            'Quantity' => 1,
            'Reason' => 'Factory Defect',
            'ReturnType' => 'refund',
            'ReturnDate' => now()->format('Y-m-d'),
            'Status' => 'approved',
            'CustomerName' => 'Jane Buyer',
        ]);
        $fromReturn = DamagedProduct::create(array_merge($this->baseDamagePayload(), [
            'Status' => DamagedProduct::STATUS_FOR_SUPPLIER_RETURN,
            'SalesReturnID' => $salesReturn->SalesReturnID,
        ]));
        // Manually-recorded damage marked for supplier return — must NOT appear in this list.
        DamagedProduct::create(array_merge($this->baseDamagePayload(), ['Status' => DamagedProduct::STATUS_FOR_SUPPLIER_RETURN]));

        $response = $this->actingAs($this->admin)->get(route('admin.damages.create'));

        $response->assertStatus(200);
        $response->assertViewHas('pendingReturnDamages', function ($records) use ($fromReturn) {
            return $records->count() === 1 && $records->first()->DamageID === $fromReturn->DamageID;
        });
    }
}
