<?php

namespace Tests\Feature\Cashier;

use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Cashier "Recent Transactions" must be a same-day, own-sales-only audit
// view -- see TASK: FIX TRANSACTION HISTORY ACCESS AND CASHIER DAILY AUDIT
// VIEW. Enforcement lives entirely in CashierAuthController::transactions()
// and ::viewReceipt(), not the frontend, so every check here goes straight
// at the route/response rather than trusting what the page would render.
class TransactionHistoryDailyScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier1;
    private User $cashier2;
    private Staff $staff1;
    private Staff $staff2;

    protected function setUp(): void
    {
        parent::setUp();

        $cashierRole = Role::create(['role_name' => 'cashier']);
        $this->cashier1 = User::factory()->create(['name' => 'cashier1', 'role_id' => $cashierRole->id]);
        $this->cashier2 = User::factory()->create(['name' => 'cashier2', 'role_id' => $cashierRole->id]);

        $this->staff1 = Staff::create([
            'FirstName' => 'Cash', 'MiddleName' => '-', 'LastName' => 'One',
            'ContactNumber' => '0000', 'Email' => 'c1@example.com', 'Age' => 25, 'Gender' => 'F',
            'UserID' => $this->cashier1->id,
        ]);
        $this->staff2 = Staff::create([
            'FirstName' => 'Cash', 'MiddleName' => '-', 'LastName' => 'Two',
            'ContactNumber' => '0000', 'Email' => 'c2@example.com', 'Age' => 26, 'Gender' => 'M',
            'UserID' => $this->cashier2->id,
        ]);
    }

    private function makeTransactionFor(Staff $staff, $when, string $customerName = 'Walk-in Customer'): SalesTransaction
    {
        return SalesTransaction::create([
            'CustomerName' => $customerName,
            'SalesTransactionDate' => $when,
            'StaffID' => $staff->StaffID,
        ]);
    }

    // --- Test 2 / Test 4: today only ------------------------------------

    public function test_cashier_sees_todays_transaction_but_not_yesterdays(): void
    {
        $today = $this->makeTransactionFor($this->staff1, now(), 'Today Customer');
        $this->makeTransactionFor($this->staff1, now()->subDay(), 'Yesterday Customer');

        $response = $this->actingAs($this->cashier1)->get(route('cashier.transactions'));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('transactions')->total());
        $response->assertSee('Today Customer');
        $response->assertDontSee('Yesterday Customer');
    }

    public function test_transaction_from_a_moment_before_midnight_does_not_leak_into_today(): void
    {
        // 11:59:59 PM yesterday must land in yesterday's bucket, not today's.
        $this->makeTransactionFor($this->staff1, now()->subDay()->endOfDay(), 'Late Night Customer');

        $response = $this->actingAs($this->cashier1)->get(route('cashier.transactions'));

        $this->assertSame(0, $response->viewData('transactions')->total());
    }

    public function test_transaction_from_a_moment_after_midnight_today_is_visible(): void
    {
        $this->makeTransactionFor($this->staff1, now()->startOfDay(), 'Midnight Customer');

        $response = $this->actingAs($this->cashier1)->get(route('cashier.transactions'));

        $this->assertSame(1, $response->viewData('transactions')->total());
    }

    // --- Test 3: cross-cashier isolation, combined with the date rule ---

    public function test_cashier_cannot_see_another_cashiers_transaction_even_from_today(): void
    {
        $this->makeTransactionFor($this->staff2, now(), 'Other Cashier Customer');

        $response = $this->actingAs($this->cashier1)->get(route('cashier.transactions'));

        $this->assertSame(0, $response->viewData('transactions')->total());
        $response->assertDontSee('Other Cashier Customer');
    }

    // --- Test 5 / §5-6: backend can't be bypassed via request params ----

    public function test_date_from_and_date_to_query_params_cannot_widen_the_visible_range(): void
    {
        $this->makeTransactionFor($this->staff1, now()->subDays(10), 'Old Customer');

        $response = $this->actingAs($this->cashier1)->get(route('cashier.transactions', [
            'date_from' => now()->subDays(30)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $this->assertSame(0, $response->viewData('transactions')->total());
        $response->assertDontSee('Old Customer');
    }

    public function test_direct_receipt_url_for_another_cashiers_transaction_is_denied(): void
    {
        $otherTransaction = $this->makeTransactionFor($this->staff2, now(), 'Other Cashier Customer');
        $receiptNumber = 'RCT-' . str_pad($otherTransaction->SalesTransactionID, 6, '0', STR_PAD_LEFT);

        $response = $this->actingAs($this->cashier1)->get(route('cashier.receipt', $receiptNumber));

        $response->assertNotFound();
    }

    public function test_direct_receipt_url_for_an_old_transaction_of_ones_own_still_works(): void
    {
        // The date restriction is a Transaction-History *listing* rule, not
        // a permanent lockout on a receipt the cashier legitimately owns --
        // e.g. reprinting a receipt for a customer a day or two later must
        // still work even though it no longer shows up in "Recent
        // Transactions". Only ownership is enforced on the receipt itself.
        $ownOldTransaction = $this->makeTransactionFor($this->staff1, now()->subDays(3), 'Loyal Customer');
        $receiptNumber = 'RCT-' . str_pad($ownOldTransaction->SalesTransactionID, 6, '0', STR_PAD_LEFT);

        $response = $this->actingAs($this->cashier1)->get(route('cashier.receipt', $receiptNumber));

        $response->assertOk();
    }

    // --- Test 6: search stays inside the today+ownership scope ----------

    public function test_search_does_not_surface_yesterdays_matching_transaction(): void
    {
        $this->makeTransactionFor($this->staff1, now()->subDay(), 'Juan Dela Cruz');

        $response = $this->actingAs($this->cashier1)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get(route('cashier.transactions', ['search' => 'Juan']));

        $response->assertOk();
        $this->assertStringNotContainsString('Juan Dela Cruz', $response->json('rows'));
    }

    // --- Test 10: empty state -------------------------------------------

    public function test_empty_state_says_today_not_a_generic_message(): void
    {
        $response = $this->actingAs($this->cashier1)->get(route('cashier.transactions'));

        $response->assertOk();
        $response->assertSee('No transactions found for today.');
    }
}
