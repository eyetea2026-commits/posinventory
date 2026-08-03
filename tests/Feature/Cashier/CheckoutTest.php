<?php

namespace Tests\Feature\Cashier;

use App\Models\Billing;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $cashierRole = Role::create(['role_name' => 'cashier']);
        $this->cashier = User::factory()->create(['role_id' => $cashierRole->id]);
    }

    private function makeProduct(float $price, int $stock = 50): Product
    {
        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $product = Product::create([
            'ProductName' => 'Test Camera', 'Model' => 'CAM-X', 'SKU' => 'SKU-' . uniqid(),
            'Price' => $price, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => $stock, 'Status' => 'Available']);

        return $product;
    }

    // ---- Payment sufficiency applies to every method, not just cash ----

    public function test_gcash_payment_below_total_is_rejected(): void
    {
        $product = $this->makeProduct(1000);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'payment_method' => 'gcash',
            'payment_amount' => 500,
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $this->assertDatabaseMissing('SalesTransaction', ['CustomerName' => 'Walk-in Customer']);
    }

    public function test_gcash_payment_meeting_total_succeeds(): void
    {
        $product = $this->makeProduct(1000);

        // 1000 subtotal, no discount, 12% VAT -> total 1120
        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'payment_method' => 'gcash',
            'payment_amount' => 1120,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    // ---- Money rounding: a discount rate that produces a repeating binary
    // fraction must not make an exact-total payment look insufficient ----

    public function test_payment_exactly_matching_a_rounded_total_is_accepted(): void
    {
        $product = $this->makeProduct(99.99);
        // A rate chosen so intermediate float math produces trailing-fraction
        // noise before rounding (99.99 * 0.0725 etc.) — this is the scenario
        // the round(...,2) fix at every step of processSale() protects.
        $discount = Discount::create([
            'ProductID' => $product->ProductID, 'DiscountRate' => 7.25, 'Name' => 'Odd Rate',
            'PromoCode' => 'ODDRATE', 'StartDate' => now()->subDay(), 'EndDate' => now()->addMonth(), 'Status' => 'active',
        ]);

        $subtotal = round(99.99, 2);
        $discountAmount = round($subtotal * (7.25 / 100), 2);
        $vatAmount = round(($subtotal - $discountAmount) * 0.12, 2);
        $total = round($subtotal - $discountAmount + $vatAmount, 2);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => $total,
            'discount_id' => $discount->DiscountID,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    // ---- Receipt breakdown is stored at sale time and read back verbatim,
    // immune to the Discount's rate being edited afterward ----

    public function test_receipt_reads_back_the_stored_breakdown_not_a_live_recompute(): void
    {
        $product = $this->makeProduct(1000);
        $discount = Discount::create([
            'ProductID' => $product->ProductID, 'DiscountRate' => 10, 'Name' => 'Ten Percent',
            'PromoCode' => 'TENOFF', 'StartDate' => now()->subDay(), 'EndDate' => now()->addMonth(), 'Status' => 'active',
        ]);

        $sale = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => 1008.00, // (1000 - 100) * 1.12 = 1008
            'discount_id' => $discount->DiscountID,
        ]);
        $sale->assertOk();
        $receiptNumber = $sale->json('receipt_number');

        $billing = Billing::where('DiscountID', $discount->DiscountID)->firstOrFail();
        $this->assertEquals(1000.00, (float) $billing->Subtotal);
        $this->assertEquals(100.00, (float) $billing->DiscountAmount);
        $this->assertEquals(108.00, (float) $billing->VatAmount);

        // Now edit the discount's rate — a live-recompute receipt would
        // silently show a different amount than what was actually charged.
        $discount->update(['DiscountRate' => 50]);

        $receiptResponse = $this->actingAs($this->cashier)->get(route('cashier.receipt', $receiptNumber));
        $receiptResponse->assertOk();
        $receiptResponse->assertViewHas('discountAmount', 100.00);
        $receiptResponse->assertViewHas('total', 1008.00);
    }

    public function test_receipt_falls_back_to_live_recompute_for_rows_predating_the_breakdown_columns(): void
    {
        $product = $this->makeProduct(1000);
        $discount = Discount::create(['DiscountRate' => 10, 'Name' => 'Legacy Rate']);

        $transaction = SalesTransaction::create([
            'CustomerName' => 'Walk-in Customer',
            'SalesTransactionDate' => now(),
            'StaffID' => \App\Models\Staff::create([
                'FirstName' => 'Legacy', 'MiddleName' => '-', 'LastName' => 'Cashier',
                'ContactNumber' => '0000', 'Email' => 'legacy@example.com', 'Age' => 30, 'Gender' => 'F',
                'UserID' => $this->cashier->id,
            ])->StaffID,
        ]);
        \App\Models\SalesItem::create([
            'Quantity' => 1, 'UnitPrice' => 1000, 'ProductID' => $product->ProductID,
            'SalesTransactionID' => $transaction->SalesTransactionID,
        ]);
        // Simulate a historical row: Subtotal/DiscountAmount/VatAmount left null.
        $billing = Billing::create([
            'CustomerName' => 'Walk-in Customer', 'VatApplied' => '12%', 'BillingAmount' => 1008,
            'BillingDate' => now(), 'DiscountID' => $discount->DiscountID,
            'SalesTransactionID' => $transaction->SalesTransactionID,
        ]);
        Payment::create([
            'PaymentAmount' => 1008, 'PaymentMethod' => 'cash',
            'ReceiptNumber' => 'RCT-' . str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT),
            'BillingID' => $billing->BillingID,
        ]);

        $receiptNumber = 'RCT-' . str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT);

        $receiptResponse = $this->actingAs($this->cashier)->get(route('cashier.receipt', $receiptNumber));
        $receiptResponse->assertOk();
        $receiptResponse->assertViewHas('discountRate', 10);
        $receiptResponse->assertViewHas('discountAmount', 100.0);
    }
}
