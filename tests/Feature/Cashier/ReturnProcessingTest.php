<?php

namespace Tests\Feature\Cashier;

use App\Models\ActivityLog;
use App\Models\Category;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReturnProcessingTest extends TestCase
{
    use RefreshDatabase;

    private User $cashierUser;

    private Staff $staff;

    private Product $product;

    private SalesTransaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $cashierRole = Role::create(['role_name' => 'cashier']);
        $this->cashierUser = User::factory()->create(['role_id' => $cashierRole->id]);
        $this->staff = Staff::create([
            'FirstName' => 'Jane', 'MiddleName' => '-', 'LastName' => 'Doe',
            'ContactNumber' => '0000', 'Email' => 'jane@example.com', 'Age' => 30, 'Gender' => 'F',
            'UserID' => $this->cashierUser->id,
        ]);

        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);

        $this->product = Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01',
            'Price' => 1000, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 5, 'Status' => 'Available']);

        $this->transaction = SalesTransaction::create([
            'CustomerName' => 'Walk-in Customer',
            'SalesTransactionDate' => now(),
            'StaffID' => $this->staff->StaffID,
        ]);

        SalesItem::create([
            'Quantity' => 2, 'UnitPrice' => 1000,
            'ProductID' => $this->product->ProductID,
            'SalesTransactionID' => $this->transaction->SalesTransactionID,
        ]);

        $discount = Discount::firstOrCreate(['DiscountRate' => 0]);
        $billing = tap(SalesTransaction::findOrFail($this->transaction->SalesTransactionID))->update([
            'VatApplied' => '12%',
            'TotalAmount' => 2000,
            'DiscountID' => $discount->DiscountID,
        ]);
        Payment::create([
            'PaymentAmount' => 2000, 'PaymentMethod' => 'cash',
            'ReceiptNumber' => 'RCT-'.str_pad($this->transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT),
            'SalesTransactionID' => $billing->SalesTransactionID,
        ]);
    }

    // A second, unrelated cashier -- used only by the ownership-scoping
    // tests below (audit finding F1) to prove one cashier can't reach
    // another's refund records.
    private function makeOtherCashier(): Staff
    {
        $otherUser = User::factory()->create(['role_id' => $this->cashierUser->role_id]);

        return Staff::create([
            'FirstName' => 'John', 'MiddleName' => '-', 'LastName' => 'Smith',
            'ContactNumber' => '0000', 'Email' => 'john@example.com', 'Age' => 28, 'Gender' => 'M',
            'UserID' => $otherUser->id,
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
            'StaffID' => $this->staff->StaffID,
        ], $overrides);

        $return = SalesReturn::create($fields);

        // Mirrors the backfill migration: every SalesReturn row has a
        // matching SalesReturnItem line that processing/approval now reads
        // from instead of the legacy header ProductID/Quantity/Reason.
        SalesReturnItem::create([
            'SalesReturnID' => $return->SalesReturnID,
            'ProductID' => $fields['ProductID'],
            'Quantity' => $fields['Quantity'],
            'UnitPrice' => 1000,
            'Reason' => $fields['Reason'],
        ]);

        return $return;
    }

    public function test_process_refund_rejects_non_approved_status(): void
    {
        $return = $this->makeReturn(['Status' => 'pending']);

        $response = $this->actingAs($this->cashierUser)->postJson(
            route('cashier.refunds.process', $return),
            ['refund_method' => 'cash']
        );

        $response->assertStatus(400);
        $this->assertSame(5, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
    }

    public function test_process_refund_rejects_replacement_type_requests(): void
    {
        $return = $this->makeReturn(['Status' => 'approved', 'ReturnType' => 'replacement']);

        $response = $this->actingAs($this->cashierUser)->postJson(
            route('cashier.refunds.process', $return),
            ['refund_method' => 'cash']
        );

        $response->assertStatus(400);
    }

    public function test_process_refund_requires_account_number_for_non_cash_methods(): void
    {
        $return = $this->makeReturn(['Status' => 'approved']);

        $response = $this->actingAs($this->cashierUser)->postJson(
            route('cashier.refunds.process', $return),
            ['refund_method' => 'gcash']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('account_number');
    }

    public function test_process_refund_does_not_require_account_number_for_cash(): void
    {
        $return = $this->makeReturn(['Status' => 'approved']);

        $response = $this->actingAs($this->cashierUser)->postJson(
            route('cashier.refunds.process', $return),
            ['refund_method' => 'cash']
        );

        $response->assertJson(['success' => true]);
    }

    public function test_process_refund_restores_inventory_and_marks_processed(): void
    {
        // Salable reason — the only case that should ever restore stock.
        $return = $this->makeReturn(['Status' => 'approved', 'Quantity' => 2, 'Reason' => 'Other']);

        $response = $this->actingAs($this->cashierUser)->postJson(
            route('cashier.refunds.process', $return),
            ['refund_method' => 'cash']
        );

        $response->assertJson(['success' => true]);
        $return->refresh();
        $this->assertSame('processed', $return->Status);
        $this->assertEquals(2000, $return->RefundAmount);
        $this->assertSame(7, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
        $this->assertTrue(ActivityLog::where('Action', 'return.refund_processed')->exists());
    }

    // A promo-discounted item must be refunded for what was actually paid,
    // not its full undiscounted price — otherwise the refund overpays the
    // customer by exactly the amount the original discount was worth.
    public function test_process_refund_prorates_by_the_original_sale_discount(): void
    {
        $transaction = SalesTransaction::create([
            'CustomerName' => 'Walk-in Customer', 'SalesTransactionDate' => now(), 'StaffID' => $this->staff->StaffID,
        ]);
        // 2 units @ 1000, with a 400 total discount (200/unit) — customer
        // actually paid 800/unit.
        SalesItem::create([
            'Quantity' => 2, 'UnitPrice' => 1000, 'DiscountAmount' => 400,
            'ProductID' => $this->product->ProductID, 'SalesTransactionID' => $transaction->SalesTransactionID,
        ]);
        $discount = Discount::firstOrCreate(['DiscountRate' => 20]);
        $billing = tap(SalesTransaction::findOrFail($transaction->SalesTransactionID))->update([
            'VatApplied' => '12%',
            'TotalAmount' => 1600,
            'DiscountID' => $discount->DiscountID,
        ]);
        Payment::create([
            'PaymentAmount' => 1600, 'PaymentMethod' => 'cash',
            'ReceiptNumber' => 'RCT-'.str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT),
            'SalesTransactionID' => $billing->SalesTransactionID,
        ]);

        $return = SalesReturn::create([
            'SalesTransactionID' => $transaction->SalesTransactionID, 'ProductID' => $this->product->ProductID,
            'Quantity' => 1, 'Reason' => 'Other', 'ReturnType' => 'refund',
            'ReturnDate' => now()->format('Y-m-d'), 'Status' => 'approved', 'StaffID' => $this->staff->StaffID,
        ]);
        SalesReturnItem::create([
            'SalesReturnID' => $return->SalesReturnID, 'ProductID' => $this->product->ProductID,
            'Quantity' => 1, 'UnitPrice' => 1000, 'Reason' => 'Other',
        ]);

        $response = $this->actingAs($this->cashierUser)->postJson(
            route('cashier.refunds.process', $return),
            ['refund_method' => 'cash']
        );

        $response->assertJson(['success' => true]);
        $return->refresh();
        // 1 unit returned x (1000 - 200 discount/unit) = 800, not 1000.
        $this->assertEquals(800, $return->RefundAmount);
    }

    public function test_process_refund_does_not_restore_inventory_for_unsalable_reasons(): void
    {
        foreach (['Factory Defect', 'Damaged Product'] as $reason) {
            Inventory::where('ProductID', $this->product->ProductID)->update(['Quantity' => 5]);
            $return = $this->makeReturn(['Status' => 'approved', 'Quantity' => 2, 'Reason' => $reason]);

            $response = $this->actingAs($this->cashierUser)->postJson(
                route('cashier.refunds.process', $return),
                ['refund_method' => 'cash']
            );

            $response->assertJson(['success' => true]);
            $return->refresh();
            $this->assertSame('processed', $return->Status, "Reason: {$reason}");
            $this->assertEquals(2000, $return->RefundAmount, "Reason: {$reason}");
            // Inventory must stay untouched — the unit was diverted to the
            // Damage module at approval time and must never come back.
            $this->assertSame(5, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity, "Reason: {$reason}");
        }
    }

    // Returns are refund-only: the replacement screens, routes, and slip
    // were removed together with the Replacement table.
    public function test_replacement_processing_is_gone_from_the_cashier_returns_page(): void
    {
        foreach (['cashier.refunds.process-replacement', 'cashier.refunds.slip', 'cashier.replacement-inventory.search'] as $name) {
            $this->assertFalse(Route::has($name), $name);
        }

        $return = $this->makeReturn(['Status' => 'approved']);
        $this->actingAs($this->cashierUser)->postJson("/cashier/refunds/{$return->SalesReturnID}/process-replacement", ['quantity' => 1])->assertNotFound();
        $this->actingAs($this->cashierUser)->get("/cashier/refunds/{$return->SalesReturnID}/slip")->assertNotFound();

        $this->actingAs($this->cashierUser)->get(route('cashier.refunds'))->assertOk()
            ->assertSee('Process Refund')
            ->assertDontSee('Process Replacement');
        $this->actingAs($this->cashierUser)->getJson(route('cashier.refunds.details', $return->SalesReturnID))
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('refund.replacement');
    }

    public function test_search_transaction_by_receipt_and_customer_and_barcode(): void
    {
        $byReceipt = $this->actingAs($this->cashierUser)->getJson(
            route('cashier.refunds.search', ['mode' => 'receipt', 'q' => 'RCT-'.str_pad($this->transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT)])
        );
        $byReceipt->assertJson(['success' => true, 'multiple' => false]);

        $byCustomer = $this->actingAs($this->cashierUser)->getJson(
            route('cashier.refunds.search', ['mode' => 'customer', 'q' => 'Walk-in'])
        );
        $byCustomer->assertJson(['success' => true]);

        $byBarcode = $this->actingAs($this->cashierUser)->getJson(
            route('cashier.refunds.search', ['mode' => 'barcode', 'q' => 'NONEXISTENT'])
        );
        $byBarcode->assertStatus(404);
    }

    // The QR code at the bottom of a receipt holds its receipt number;
    // scanning it in the SKU / Barcode search opens that receipt.
    public function test_scanning_a_receipt_qr_in_the_barcode_search_finds_the_receipt(): void
    {
        $receipt = 'RCT-'.str_pad($this->transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT);

        $this->actingAs($this->cashierUser)->getJson(route('cashier.refunds.search', ['mode' => 'barcode', 'q' => $receipt]))
            ->assertOk()
            ->assertJson(['success' => true, 'multiple' => false])
            ->assertJsonPath('transaction.SalesTransactionID', $this->transaction->SalesTransactionID);

        $this->actingAs($this->cashierUser)->getJson(route('cashier.refunds.search', ['mode' => 'barcode', 'q' => 'RCT-999999']))
            ->assertNotFound()
            ->assertJson(['success' => false, 'message' => 'Transaction not found.']);

        $this->actingAs($this->cashierUser)->get(route('cashier.refunds'))
            ->assertOk()
            ->assertSee("onkeydown=\"if (event.key === 'Enter' || event.key === 'NumpadEnter') { event.preventDefault(); searchTransaction(); }\"", false);
    }

    public function test_create_refund_rejects_requests_outside_the_return_window(): void
    {
        $oldTransaction = SalesTransaction::create([
            'CustomerName' => 'Walk-in Customer',
            'SalesTransactionDate' => now()->subDays(SalesReturn::RETURN_WINDOW_DAYS + 3),
            'StaffID' => $this->staff->StaffID,
        ]);
        SalesItem::create([
            'Quantity' => 1, 'UnitPrice' => 1000,
            'ProductID' => $this->product->ProductID,
            'SalesTransactionID' => $oldTransaction->SalesTransactionID,
        ]);

        $response = $this->actingAs($this->cashierUser)->postJson(route('cashier.refunds.create'), [
            'transaction_id' => $oldTransaction->SalesTransactionID,
            'items' => [
                ['product_id' => $this->product->ProductID, 'quantity' => 1, 'reason_code' => 'damaged_product'],
            ],
            'return_type' => 'refund',
        ]);

        $response->assertStatus(400);
        $this->assertDatabaseMissing('SalesReturn', ['SalesTransactionID' => $oldTransaction->SalesTransactionID]);
    }

    public function test_other_is_no_longer_a_return_reason(): void
    {
        $response = $this->actingAs($this->cashierUser)->postJson(route('cashier.refunds.create'), [
            'transaction_id' => $this->transaction->SalesTransactionID,
            'items' => [
                ['product_id' => $this->product->ProductID, 'quantity' => 1, 'reason_code' => 'other'],
            ],
            'return_type' => 'refund',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['items.0.reason_code']);
        $this->assertDatabaseMissing('SalesReturn', ['SalesTransactionID' => $this->transaction->SalesTransactionID]);

        $html = $this->actingAs($this->cashierUser)->get(route('cashier.refunds'))->assertOk()->getContent();
        $this->assertStringNotContainsString('<option value="other"', $html);
        $this->assertStringContainsString('<option value="" selected>Select Reason</option>', $html);
    }

    public function test_create_refund_allows_requests_within_the_return_window(): void
    {
        $response = $this->actingAs($this->cashierUser)->postJson(route('cashier.refunds.create'), [
            'transaction_id' => $this->transaction->SalesTransactionID,
            'items' => [
                ['product_id' => $this->product->ProductID, 'quantity' => 1, 'reason_code' => 'damaged_product'],
            ],
            'return_type' => 'refund',
        ]);

        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('SalesReturn', ['SalesTransactionID' => $this->transaction->SalesTransactionID]);
    }

    // ---- A later request can't push the total requested for one sale/
    // product past what was actually sold (2 units, per setUp) ----

    public function test_create_refund_rejects_a_request_that_would_exceed_the_quantity_sold(): void
    {
        $first = $this->actingAs($this->cashierUser)->postJson(route('cashier.refunds.create'), [
            'transaction_id' => $this->transaction->SalesTransactionID,
            'items' => [
                ['product_id' => $this->product->ProductID, 'quantity' => 2, 'reason_code' => 'damaged_product'],
            ],
            'return_type' => 'refund',
        ]);
        $first->assertJson(['success' => true]);

        // Only 2 units were ever sold, and both are already covered by the
        // still-pending request above — a second request for even 1 more
        // unit must be rejected, not silently allowed to double-count.
        $second = $this->actingAs($this->cashierUser)->postJson(route('cashier.refunds.create'), [
            'transaction_id' => $this->transaction->SalesTransactionID,
            'items' => [
                ['product_id' => $this->product->ProductID, 'quantity' => 1, 'reason_code' => 'damaged_product'],
            ],
            'return_type' => 'refund',
        ]);

        $second->assertStatus(400);
        $second->assertJsonPath('success', false);
        $this->assertSame(1, SalesReturn::where('SalesTransactionID', $this->transaction->SalesTransactionID)->count());
    }

    // ---- A second return request for the same cashier while the first is
    // still in flight is rejected (409), not recorded twice — even when
    // there's enough sold quantity left for both to pass on their own ----

    public function test_a_second_return_request_for_the_same_cashier_while_one_is_in_flight_is_rejected(): void
    {
        Cache::lock("return-request:user:{$this->cashierUser->id}", 10)->get();

        $response = $this->actingAs($this->cashierUser)->postJson(route('cashier.refunds.create'), [
            'transaction_id' => $this->transaction->SalesTransactionID,
            'items' => [
                ['product_id' => $this->product->ProductID, 'quantity' => 1, 'reason_code' => 'damaged_product'],
            ],
            'return_type' => 'refund',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('success', false);
        $this->assertDatabaseMissing('SalesReturn', ['SalesTransactionID' => $this->transaction->SalesTransactionID]);
    }

    public function test_return_request_lock_is_released_so_a_later_request_still_succeeds(): void
    {
        $first = $this->actingAs($this->cashierUser)->postJson(route('cashier.refunds.create'), [
            'transaction_id' => $this->transaction->SalesTransactionID,
            'items' => [
                ['product_id' => $this->product->ProductID, 'quantity' => 1, 'reason_code' => 'damaged_product'],
            ],
            'return_type' => 'refund',
        ]);
        $first->assertJson(['success' => true]);

        $second = $this->actingAs($this->cashierUser)->postJson(route('cashier.refunds.create'), [
            'transaction_id' => $this->transaction->SalesTransactionID,
            'items' => [
                ['product_id' => $this->product->ProductID, 'quantity' => 1, 'reason_code' => 'damaged_product'],
            ],
            'return_type' => 'refund',
        ]);
        $second->assertJson(['success' => true]);
    }

    // ---- Ownership scoping on the read/process endpoints (audit finding
    // F1): a Cashier may only reach their OWN refund records,
    // never another Cashier's, even by ID directly. ----

    public function test_print_refund_receipt_is_denied_for_a_different_cashiers_return(): void
    {
        $return = $this->makeReturn(['Status' => 'processed', 'ReturnType' => 'refund']);
        $otherCashier = $this->makeOtherCashier()->user;

        $own = $this->actingAs($this->cashierUser)->get(route('cashier.refunds.receipt', $return->SalesReturnID));
        $own->assertOk();

        $other = $this->actingAs($otherCashier)->get(route('cashier.refunds.receipt', $return->SalesReturnID));
        $other->assertNotFound();
    }

    public function test_get_refund_details_is_denied_for_a_different_cashiers_return(): void
    {
        $return = $this->makeReturn();
        $otherCashier = $this->makeOtherCashier()->user;

        $own = $this->actingAs($this->cashierUser)->getJson(route('cashier.refunds.details', $return->SalesReturnID));
        $own->assertJsonPath('success', true);

        $other = $this->actingAs($otherCashier)->getJson(route('cashier.refunds.details', $return->SalesReturnID));
        $other->assertStatus(404);
        $other->assertJsonPath('success', false);
    }

    public function test_process_refund_is_denied_for_a_different_cashiers_return(): void
    {
        $return = $this->makeReturn(['Status' => 'approved', 'ReturnType' => 'refund']);
        $otherCashier = $this->makeOtherCashier()->user;

        $response = $this->actingAs($otherCashier)->postJson(route('cashier.refunds.process', $return->SalesReturnID), [
            'refund_method' => 'cash',
        ]);

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
        $this->assertSame('approved', $return->fresh()->Status);
    }
}
