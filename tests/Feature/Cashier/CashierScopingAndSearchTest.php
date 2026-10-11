<?php

namespace Tests\Feature\Cashier;

use App\Models\Category;
use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierScopingAndSearchTest extends TestCase
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

        Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
    }

    private function makeTransactionFor(Staff $staff, string $customerName = 'Walk-in Customer'): SalesTransaction
    {
        return SalesTransaction::create([
            'CustomerName' => $customerName,
            'SalesTransactionDate' => now(),
            'StaffID' => $staff->StaffID,
        ]);
    }

    // --- Ownership scoping (item 2/3/4) ---------------------------------

    // Every cashier sees all of today's sales, but can only reprint their own.
    public function test_cashier_sees_every_cashiers_transactions_from_today(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeTransactionFor($this->staff1);
        }
        for ($i = 0; $i < 8; $i++) {
            $this->makeTransactionFor($this->staff2);
        }

        $response = $this->actingAs($this->cashier1)->get(route('cashier.transactions'));
        $response->assertOk();
        $this->assertSame(13, $response->viewData('transactions')->total());

        $response2 = $this->actingAs($this->cashier2)->get(route('cashier.transactions'));
        $response2->assertOk();
        $this->assertSame(13, $response2->viewData('transactions')->total());
    }

    public function test_print_is_only_offered_on_the_cashiers_own_sales(): void
    {
        $own = $this->makeTransactionFor($this->staff1, 'Own Customer');
        $other = $this->makeTransactionFor($this->staff2, 'Other Customer');
        $ownReceipt = 'RCT-'.str_pad($own->SalesTransactionID, 6, '0', STR_PAD_LEFT);
        $otherReceipt = 'RCT-'.str_pad($other->SalesTransactionID, 6, '0', STR_PAD_LEFT);

        $response = $this->actingAs($this->cashier1)->get(route('cashier.transactions'));

        $response->assertOk();
        $response->assertSee('Other Customer');
        $response->assertSee($this->cashier2->full_name);
        $response->assertSee('View only');
        $response->assertSee(route('cashier.receipt', $ownReceipt), false);
        $response->assertDontSee(route('cashier.receipt', $otherReceipt), false);
    }

    public function test_a_cashier_with_no_sales_yet_sees_others_sales_as_view_only(): void
    {
        // Cashier 2 has sold plenty; this cashier has never processed a
        // sale, so no Staff row exists for them yet at all.
        for ($i = 0; $i < 3; $i++) {
            $this->makeTransactionFor($this->staff2);
        }

        $brandNewCashier = User::factory()->create(['name' => 'freshcashier', 'role_id' => $this->cashier1->role_id]);

        $response = $this->actingAs($brandNewCashier)->get(route('cashier.transactions'));
        $response->assertOk();
        $this->assertSame(3, $response->viewData('transactions')->total());
        $response->assertDontSee('fa-print', false);
        $response->assertSee('View only');
    }

    // --- Live search AJAX branch (item 5) -------------------------------

    public function test_ajax_request_returns_json_rows_and_pagination(): void
    {
        $this->makeTransactionFor($this->staff1, 'Juan Dela Cruz');
        $this->makeTransactionFor($this->staff1, 'Maria Santos');

        $response = $this->actingAs($this->cashier1)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get(route('cashier.transactions'));

        $response->assertOk();
        $response->assertJsonStructure(['rows', 'pagination']);
        $this->assertStringContainsString('Juan Dela Cruz', $response->json('rows'));
        $this->assertStringContainsString('Maria Santos', $response->json('rows'));
    }

    public function test_ajax_search_filters_by_customer_name_across_all_cashiers(): void
    {
        $this->makeTransactionFor($this->staff1, 'Juan Dela Cruz');
        $this->makeTransactionFor($this->staff1, 'Maria Santos');
        $this->makeTransactionFor($this->staff2, 'Juan Dela Cruz'); // same name, different cashier

        $response = $this->actingAs($this->cashier1)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get(route('cashier.transactions', ['search' => 'Juan']));

        $response->assertOk();
        $rows = $response->json('rows');
        $this->assertSame(2, substr_count($rows, 'Juan Dela Cruz'));
        $this->assertStringNotContainsString('Maria Santos', $rows);
        // The AJAX-rendered rows apply the same own-vs-others action rule.
        $this->assertSame(1, substr_count($rows, 'fa-print'));
        $this->assertStringContainsString('View only', $rows);
    }

    public function test_transactions_page_has_no_search_button_and_wires_up_live_search(): void
    {
        $response = $this->actingAs($this->cashier1)->get(route('cashier.transactions'));

        $response->assertOk();
        $response->assertSee('id="transactionsSearchInput"', false);
        $response->assertSee("searchInput.addEventListener('input'", false);
        $response->assertDontSee('>Search</button>', false);
    }

    // Regression: the Print action used to link back to the transactions
    // list itself (with an unused ?print= param the controller never read),
    // opening a duplicate copy of the list in a new tab instead of the
    // actual printable receipt.
    public function test_print_action_links_to_the_actual_receipt_not_a_duplicate_of_the_list(): void
    {
        $transaction = $this->makeTransactionFor($this->staff1, 'Juan Dela Cruz');
        $receiptNumber = 'RCT-'.str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT);

        $response = $this->actingAs($this->cashier1)->get(route('cashier.transactions'));

        $response->assertOk();
        $response->assertSee(route('cashier.receipt', $receiptNumber), false);
        $response->assertDontSee(route('cashier.transactions').'?print=', false);
    }

    // --- Refund Requests: no blue button, live filter, no Invoice # -----

    public function test_refunds_page_has_no_search_button_and_wires_up_live_filter(): void
    {
        $response = $this->actingAs($this->cashier1)->get(route('cashier.refunds'));

        $response->assertOk();
        $response->assertSee('id="refundsSearchInput"', false);
        $response->assertSee('initRefundsLiveSearch', false);
        $response->assertDontSee('<button type="submit"><i class="fas fa-search"></i> Search</button>', false);
    }

    public function test_new_return_request_modal_has_no_invoice_field(): void
    {
        $response = $this->actingAs($this->cashier1)->get(route('cashier.refunds'));

        $response->assertOk();
        $response->assertDontSee('Invoice #');
        $response->assertDontSee('Invoice Number');
        $response->assertDontSee('data-mode="invoice"', false);
        $response->assertSee('data-mode="receipt"', false);
        $response->assertSee('data-mode="customer"', false);
        $response->assertSee('data-mode="barcode"', false);
    }
}
