<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesItem;
use App\Models\SalesReturn;
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
        return SalesReturn::create(array_merge([
            'SalesTransactionID' => $this->transaction->SalesTransactionID,
            'ProductID' => $this->product->ProductID,
            'Quantity' => 1,
            'Reason' => 'Factory Defect',
            'ReturnType' => 'refund',
            'ReturnDate' => now()->format('Y-m-d'),
            'Status' => 'pending',
        ], $overrides));
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

    public function test_request_outside_return_window_is_flagged_ineligible_but_still_approvable(): void
    {
        $return = $this->makeReturn(['ReturnDate' => now()->addDays(SalesReturn::RETURN_WINDOW_DAYS + 5)->format('Y-m-d')]);

        $this->assertFalse($return->is_within_return_window);

        $response = $this->actingAs($this->admin)->getJson(route('admin.sales-returns.show', $return));
        $response->assertJson(['return' => ['EligibleForReturn' => false]]);

        // Eligibility is advisory only — admin can still approve an out-of-window request.
        $this->actingAs($this->admin)->post(route('admin.sales-returns.approve', $return));
        $this->assertSame('approved', $return->fresh()->Status);
    }
}
