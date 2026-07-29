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

    // The Add Damage modal submits via fetch() with X-Requested-With +
    // Accept: application/json, then follows the resulting redirect back to
    // index() and parses that final response's HTML for the "Auto-show
    // session messages" Swal marker (see admin/partials/ajax-modal-form).
    // followingRedirects() reproduces that exact round trip — this fails if
    // index()'s live-search JSON branch ever goes back to keying off those
    // same XHR headers instead of the explicit ?ajax=1 flag.
    public function test_store_via_the_modals_xhr_flow_still_returns_html_after_the_redirect(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->followingRedirects()
            ->post(route('admin.damages.store'), $this->baseDamagePayload());

        $response->assertOk();
        $response->assertSee('Auto-show session messages', false);
        $response->assertSee('Damaged product recorded successfully.');
        $this->assertDatabaseHas('DamagedProduct', ['Quantity' => 2, 'Status' => DamagedProduct::STATUS_PENDING]);
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

    public function test_manually_created_damage_is_tagged_with_manual_source(): void
    {
        $this->actingAs($this->admin)->post(route('admin.damages.store'), $this->baseDamagePayload());

        $this->assertDatabaseHas('DamagedProduct', ['SourceModule' => DamagedProduct::SOURCE_MANUAL]);
    }

    public function test_show_returns_full_details_json(): void
    {
        $damage = DamagedProduct::create(array_merge($this->baseDamagePayload(), ['SourceModule' => DamagedProduct::SOURCE_MANUAL]));

        $response = $this->actingAs($this->admin)->getJson(route('admin.damages.show', $damage));

        $response->assertOk();
        $response->assertJsonPath('damage.DamageID', $damage->DamageID);
        $response->assertJsonPath('product.ProductName', $this->product->ProductName);
        $response->assertJsonPath('supplier.SupplierName', $this->supplier->SupplierName);
    }

    public function test_print_report_renders_for_a_damage_record(): void
    {
        $damage = DamagedProduct::create(array_merge($this->baseDamagePayload(), ['SourceModule' => DamagedProduct::SOURCE_MANUAL]));

        $response = $this->actingAs($this->admin)->get(route('admin.damages.print', $damage));

        $response->assertOk();
        $response->assertSee('Damage Report #' . $damage->DamageID);
        $response->assertSee($this->product->ProductName);
    }

    public function test_store_accepts_an_optional_photo_upload(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->image('damage.jpg');

        $response = $this->actingAs($this->admin)->post(route('admin.damages.store'), array_merge($this->baseDamagePayload(), [
            'Image' => $file,
        ]));

        $response->assertRedirect(route('admin.damages.index'));
        $damage = DamagedProduct::first();
        $this->assertNotNull($damage->ImagePath);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($damage->ImagePath);
    }

    public function test_index_no_longer_shows_removed_filters_and_export_buttons(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.damages.index'));

        $response->assertOk();
        $response->assertDontSee('name="date_from"', false);
        $response->assertDontSee('name="date_to"', false);
        $response->assertDontSee('All Statuses');
        $response->assertDontSee('Export as CSV');
        $response->assertDontSee('>CSV<', false);
        $response->assertDontSee('>PDF<', false);
        $response->assertDontSee('>Excel<', false);
        $response->assertDontSee('window.print()', false);
        // Add Damage Record stays — explicitly kept per the approved requirements.
        $response->assertSee('Record Damage');
        // Supplier filter stays — only the date/status filters were removed.
        $response->assertSee('All Suppliers');
    }

    public function test_index_search_button_is_gone_and_search_input_is_present(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.damages.index'));

        $response->assertOk();
        $response->assertSee('id="damageSearchInput"', false);
        $response->assertDontSee('fa-solid fa-search"></i></button>', false);
    }

    public function test_export_route_no_longer_exists(): void
    {
        $this->actingAs($this->admin)->get('/admin/damages/export')->assertNotFound();
    }

    // Regression guard: fetch() follows a POST's redirect while preserving
    // custom headers, so the Add/Edit Damage modal's XHR-flagged submit to
    // store()/update()/mark-supplier-return/etc. lands back on this same
    // index route carrying the exact same X-Requested-With/Accept headers
    // a deliberate live-search request would send. If index() keyed off
    // those headers alone, that redirect-follow would get JSON instead of
    // the full HTML page the modal's shared submit helper expects to parse
    // for its success/error message — silently breaking every Add/Edit
    // Damage save. Only the explicit ?ajax=1 the live-search JS sends may
    // trigger the JSON branch.
    public function test_xhr_headers_alone_without_ajax_flag_still_return_the_full_page(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('admin.damages.index'));

        $response->assertOk();
        $response->assertSee('Record Damage');
        $response->assertDontSee('"rows":', false);
    }

    public function test_live_search_ajax_returns_filtered_rows_as_json(): void
    {
        DamagedProduct::create($this->baseDamagePayload());

        $otherProduct = Product::create([
            'ProductName' => 'Speed Dome PTZ', 'Model' => 'SPD-01', 'SKU' => 'SKU-002',
            'Price' => 2000, 'CostPrice' => 1200, 'CategoryID' => $this->product->CategoryID,
        ]);
        Inventory::create(['ProductID' => $otherProduct->ProductID, 'Quantity' => 5, 'Status' => 'Available']);
        DamagedProduct::create(array_merge($this->baseDamagePayload(), ['ProductID' => $otherProduct->ProductID]));

        $response = $this->actingAs($this->admin)->getJson(route('admin.damages.index', ['search' => 'Speed Dome', 'ajax' => 1]));

        $response->assertOk();
        $response->assertJsonStructure(['rows', 'pagination']);
        $this->assertStringContainsString('Speed Dome PTZ', $response->json('rows'));
        $this->assertStringNotContainsString('DVR Camera', $response->json('rows'));
    }

    public function test_show_includes_formatted_damage_number_and_supplier_return_status(): void
    {
        $damage = DamagedProduct::create(array_merge($this->baseDamagePayload(), [
            'Status' => DamagedProduct::STATUS_FOR_SUPPLIER_RETURN,
        ]));

        $response = $this->actingAs($this->admin)->getJson(route('admin.damages.show', $damage));

        $response->assertOk();
        $response->assertJsonPath('damage.DamageNumber', 'DMG-' . str_pad((string) $damage->DamageID, 6, '0', STR_PAD_LEFT));
        $response->assertJsonPath('supplierReturnStatus', 'Pending Supplier Return');
    }

    public function test_supplier_return_status_is_not_applicable_for_a_record_that_never_entered_that_path(): void
    {
        // No SupplierID, and a status that never went through the supplier
        // return workflow — this is the case that must NOT be gated on
        // SupplierID presence (a return-originated record can legitimately
        // reach "replacement_received" without ever having its own
        // SupplierID set).
        $damage = DamagedProduct::create(array_merge($this->baseDamagePayload(), [
            'Status' => DamagedProduct::STATUS_PENDING,
        ]));

        $response = $this->actingAs($this->admin)->getJson(route('admin.damages.show', $damage));

        $response->assertOk();
        $response->assertJsonPath('supplierReturnStatus', 'Not Applicable');
    }

    public function test_show_resolves_requested_by_for_a_manually_created_damage_record(): void
    {
        $this->actingAs($this->admin)->post(route('admin.damages.store'), $this->baseDamagePayload());
        $damage = DamagedProduct::first();

        $response = $this->actingAs($this->admin)->getJson(route('admin.damages.show', $damage));

        $response->assertOk();
        $response->assertJsonPath('requestedBy.Name', $this->admin->full_name);
    }
}
