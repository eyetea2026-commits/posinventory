<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\Category;
use App\Models\DamagedProduct;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\SalesTransaction;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReturnApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $product;
    private SalesTransaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);

        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $this->product = Product::create([
            'ProductName' => 'DVR Camera',
            'Model' => 'CAM-01',
            'SKU' => 'SKU-001',
            'Price' => 1000,
            'CategoryID' => $category->CategoryID,
        ]);

        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 5, 'Status' => 'Available']);

        $cashierRole = Role::create(['role_name' => 'cashier']);
        $cashierUser = User::factory()->create(['role_id' => $cashierRole->id]);
        $staff = Staff::create([
            'FirstName' => 'Jane', 'MiddleName' => '-', 'LastName' => 'Doe',
            'ContactNumber' => '0000', 'Email' => 'jane@example.com', 'Age' => 30, 'Gender' => 'F',
            'UserID' => $cashierUser->id,
        ]);

        $this->transaction = SalesTransaction::create([
            'CustomerName' => 'Walk-in Customer',
            'SalesTransactionDate' => now(),
            'StaffID' => $staff->StaffID,
        ]);

        SalesItem::create([
            'Quantity' => 2,
            'UnitPrice' => 1000,
            'ProductID' => $this->product->ProductID,
            'SalesTransactionID' => $this->transaction->SalesTransactionID,
        ]);

        $discount = Discount::firstOrCreate(['DiscountRate' => 0]);
        $billing = Billing::create([
            'CustomerName' => 'Walk-in Customer',
            'VatApplied' => '12%',
            'BillingAmount' => 2000,
            'BillingDate' => now(),
            'DiscountID' => $discount->DiscountID,
            'SalesTransactionID' => $this->transaction->SalesTransactionID,
        ]);

        Payment::create([
            'PaymentAmount' => 2000,
            'PaymentMethod' => 'cash',
            'ReceiptNumber' => 'RCT-' . str_pad($this->transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT),
            'BillingID' => $billing->BillingID,
        ]);
    }

    private function makeReturn(array $overrides = []): SalesReturn
    {
        $fields = array_merge([
            'SalesTransactionID' => $this->transaction->SalesTransactionID,
            'ProductID' => $this->product->ProductID,
            'Quantity' => 1,
            'Reason' => 'Factory Defect',
            'ReturnType' => 'refund',
            'ReturnDate' => now()->format('Y-m-d'),
            'Status' => 'pending',
        ], $overrides);

        $return = SalesReturn::create($fields);

        // Mirrors the backfill migration: every SalesReturn row has a
        // matching SalesReturnItem line that approval now reads from
        // instead of the legacy header ProductID/Quantity/Reason.
        SalesReturnItem::create([
            'SalesReturnID' => $return->SalesReturnID,
            'ProductID' => $fields['ProductID'],
            'Quantity' => $fields['Quantity'],
            'UnitPrice' => 1000,
            'Reason' => $fields['Reason'],
        ]);

        return $return;
    }

    public function test_index_has_no_all_statuses_dropdown_or_filter_button_but_filters_still_work(): void
    {
        $pending = $this->makeReturn(['Status' => 'pending']);
        $declined = $this->makeReturn(['Status' => 'declined', 'DeclineReason' => 'Not eligible']);

        $indexResponse = $this->actingAs($this->admin)->get(route('admin.sales-returns.index'));
        $indexResponse->assertOk();
        $indexResponse->assertDontSee('All Statuses');
        $indexResponse->assertDontSee('<i class="fas fa-filter"></i> Filter', false);

        // Status still filters correctly via the tabs' query param, even
        // without the dropdown.
        $pendingOnly = $this->actingAs($this->admin)->get(route('admin.sales-returns.index', ['status' => 'pending']));
        $pendingOnly->assertOk();
        $pendingOnly->assertSee('#' . $pending->SalesReturnID . '</td>', false);
        $pendingOnly->assertDontSee('#' . $declined->SalesReturnID . '</td>', false);

        // Return-type filter still auto-applies without the Filter button.
        $replacementOnly = $this->actingAs($this->admin)->get(route('admin.sales-returns.index', ['return_type' => 'replacement']));
        $replacementOnly->assertOk();
        $replacementOnly->assertDontSee('#' . $pending->SalesReturnID . '</td>', false);
    }

    public function test_approve_only_works_on_pending_requests(): void
    {
        $return = $this->makeReturn(['Status' => 'approved']);

        $this->actingAs($this->admin)->post(route('admin.sales-returns.approve', $return));

        $this->assertSame('approved', $return->fresh()->Status);
    }

    public function test_approving_does_not_restore_inventory(): void
    {
        $return = $this->makeReturn();

        $this->actingAs($this->admin)->post(route('admin.sales-returns.approve', $return));

        $this->assertSame('approved', $return->fresh()->Status);
        $this->assertSame(5, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
        $this->assertTrue(ActivityLog::where('Action', 'return.approved')->exists());
    }

    public function test_approving_unsalable_reason_creates_damage_record(): void
    {
        foreach (['Factory Defect', 'Damaged Product'] as $reason) {
            $return = $this->makeReturn(['Reason' => $reason, 'Quantity' => 1]);

            $this->actingAs($this->admin)->post(route('admin.sales-returns.approve', $return));

            $damage = DamagedProduct::where('SalesReturnID', $return->SalesReturnID)->first();
            $this->assertNotNull($damage, "Expected a damage record for reason: {$reason}");
            $this->assertSame(DamagedProduct::STATUS_FOR_SUPPLIER_RETURN, $damage->Status);
            $this->assertSame($this->product->ProductID, $damage->ProductID);
            $this->assertSame(1, $damage->Quantity);
            $this->assertTrue(ActivityLog::where('Action', 'damage.created_from_return')->exists());
        }
    }

    public function test_approving_salable_reason_does_not_create_damage_record(): void
    {
        $return = $this->makeReturn(['Reason' => 'Other']);

        $this->actingAs($this->admin)->post(route('admin.sales-returns.approve', $return));

        $this->assertDatabaseMissing('DamagedProduct', ['SalesReturnID' => $return->SalesReturnID]);
    }

    public function test_decline_requires_a_reason_and_persists_it(): void
    {
        $return = $this->makeReturn();

        $response = $this->actingAs($this->admin)->post(route('admin.sales-returns.decline', $return), []);

        $response->assertSessionHasErrors('DeclineReason');
        $this->assertSame('pending', $return->fresh()->Status);

        $response = $this->actingAs($this->admin)->post(route('admin.sales-returns.decline', $return), [
            'DeclineReason' => 'Item shows signs of misuse.',
        ]);

        $return->refresh();
        $this->assertSame('declined', $return->Status);
        $this->assertSame('Item shows signs of misuse.', $return->DeclineReason);
        $this->assertTrue(ActivityLog::where('Action', 'return.declined')->exists());
    }

    public function test_decline_only_works_on_pending_requests(): void
    {
        $return = $this->makeReturn(['Status' => 'processed']);

        $this->actingAs($this->admin)->post(route('admin.sales-returns.decline', $return), [
            'DeclineReason' => 'too late',
        ]);

        $this->assertSame('processed', $return->fresh()->Status);
        $this->assertNull($return->fresh()->DeclineReason);
    }

    public function test_request_within_return_window_is_flagged_eligible(): void
    {
        $return = $this->makeReturn(['ReturnDate' => now()->format('Y-m-d')]);

        $this->assertSame(0, $return->days_since_purchase);
        $this->assertTrue($return->is_within_return_window);

        $response = $this->actingAs($this->admin)->getJson(route('admin.sales-returns.show', $return));
        $response->assertJson(['return' => ['EligibleForReturn' => true, 'ReturnWindowDays' => SalesReturn::RETURN_WINDOW_DAYS]]);
    }

    public function test_request_outside_return_window_is_flagged_ineligible_and_cannot_be_approved(): void
    {
        $return = $this->makeReturn(['ReturnDate' => now()->addDays(SalesReturn::RETURN_WINDOW_DAYS + 5)->format('Y-m-d')]);

        $this->assertFalse($return->is_within_return_window);

        $response = $this->actingAs($this->admin)->getJson(route('admin.sales-returns.show', $return));
        $response->assertJson(['return' => ['EligibleForReturn' => false]]);

        // Outside the window is a hard block now, not just advisory — approve must be rejected.
        $this->actingAs($this->admin)->post(route('admin.sales-returns.approve', $return));
        $this->assertSame('pending', $return->fresh()->Status);

        // Decline must still work for an out-of-window request.
        $this->actingAs($this->admin)->post(route('admin.sales-returns.decline', $return), [
            'DeclineReason' => 'Outside the return window.',
        ]);
        $this->assertSame('declined', $return->fresh()->Status);
    }
}
