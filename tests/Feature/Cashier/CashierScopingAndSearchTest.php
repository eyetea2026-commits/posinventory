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

    public function test_walk_in_customer_is_listed_as_na_like_the_receipt(): void
    {
        $this->makeTransactionFor($this->staff1, 'Walk-in Customer');
        $this->makeTransactionFor($this->staff1, 'Narvaiz');

        $rows = $this->actingAs($this->cashier1)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get(route('cashier.transactions'))->json('rows');

        $this->assertStringContainsString('<td>N/A</td>', $rows);
        $this->assertStringContainsString('<td>Narvaiz</td>', $rows);
        $this->assertStringNotContainsString('Walk-in Customer', $rows);
    }

    public function test_search_finds_a_receipt_number_in_any_common_format(): void
    {
        $this->makeTransactionFor($this->staff1, 'Juan Dela Cruz');
        $target = $this->makeTransactionFor($this->staff1, 'Maria Santos');
        $receipt = 'RCT-'.str_pad($target->SalesTransactionID, 6, '0', STR_PAD_LEFT);

        foreach ([$receipt, strtolower($receipt), 'rct-'.$target->SalesTransactionID, str_pad($target->SalesTransactionID, 6, '0', STR_PAD_LEFT), (string) $target->SalesTransactionID] as $term) {
            $rows = $this->actingAs($this->cashier1)
                ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
                ->get(route('cashier.transactions', ['search' => $term]))->json('rows');

            $this->assertStringContainsString($receipt, $rows, "Search \"{$term}\" should find {$receipt}");
            $this->assertStringNotContainsString('Juan Dela Cruz', $rows, "Search \"{$term}\" should match only {$receipt}");
        }
    }

    // The list searches live on every keystroke, so a partly-typed receipt
    // number must already match, not only the complete one.
    public function test_partly_typed_receipt_numbers_match_while_typing(): void
    {
        $a = $this->makeTransactionFor($this->staff1, 'Juan Dela Cruz');
        $b = $this->makeTransactionFor($this->staff1, 'Maria Santos');
        $receiptB = 'RCT-'.str_pad($b->SalesTransactionID, 6, '0', STR_PAD_LEFT);

        $count = fn (string $term) => $this->actingAs($this->cashier1)
            ->get(route('cashier.transactions', ['search' => $term]))
            ->viewData('transactions')->total();

        foreach (['R', 'RCT', 'rct', 'RCT-', 'RCT-0000'] as $term) {
            $this->assertSame(2, $count($term), "\"{$term}\" should list both of today's receipts");
        }
        $this->assertSame(1, $count($receiptB));
        $this->assertSame(0, $count('RCX'));
    }

    public function test_receipt_search_still_respects_the_today_only_rule(): void
    {
        $old = SalesTransaction::create([
            'CustomerName' => 'Old Customer', 'SalesTransactionDate' => now()->subDays(3), 'StaffID' => $this->staff1->StaffID,
        ]);
        $receipt = 'RCT-'.str_pad($old->SalesTransactionID, 6, '0', STR_PAD_LEFT);

        $response = $this->actingAs($this->cashier1)->get(route('cashier.transactions', ['search' => $receipt]));

        $this->assertSame(0, $response->viewData('transactions')->total());
    }

    public function test_searching_na_finds_walk_in_sales(): void
    {
        $this->makeTransactionFor($this->staff1, 'Walk-in Customer');
        $this->makeTransactionFor($this->staff1, 'Narvaiz');

        $response = $this->actingAs($this->cashier1)->get(route('cashier.transactions', ['search' => 'N/A']));

        $this->assertSame(1, $response->viewData('transactions')->total());
        $this->assertSame('Walk-in Customer', $response->viewData('transactions')->first()->CustomerName);
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
        // Print asks for confirmation before printing.
        $response->assertSee("confirmPrintReceipt(event, '{$receiptNumber}', this.href)", false);
        $response->assertSee("title: 'Print Receipt'", false);
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
