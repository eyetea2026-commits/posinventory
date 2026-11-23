<?php

namespace Tests\Feature\Cashier;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Models\Staff;
use App\Models\User;
use App\Services\PaymentProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
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
            'ProductName' => 'Test Camera', 'Model' => 'CAM-X'.uniqid(),
            'Price' => $price, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => $stock, 'Status' => 'Available']);

        return $product;
    }

    public function test_a_product_with_no_selling_price_cannot_be_sold_for_zero(): void
    {
        $priced = $this->makeProduct(272.73);
        $unpriced = Product::create([
            'ProductName' => '2 Way Splitter', 'Model' => 'SPL-2', 'Barcode' => 'SPLIT-2WAY',
            'Price' => 0, 'CategoryID' => $priced->CategoryID,
        ]);
        Inventory::create(['ProductID' => $unpriced->ProductID, 'Quantity' => 50, 'Status' => 'Available']);

        $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $unpriced->ProductID, 'qty' => 1], ['id' => $priced->ProductID, 'qty' => 1]],
            'payment_method' => 'cash', 'payment_amount' => 500,
        ])->assertStatus(400)
            ->assertJsonPath('message', '"2 Way Splitter" has no selling price yet. Ask the admin to set its price in Products, then remove it from the cart.');

        $this->assertSame(0, SalesTransaction::count());
        $this->assertSame(50, Inventory::where('ProductID', $unpriced->ProductID)->value('Quantity'));

        $this->actingAs($this->cashier)->getJson('/api/products/barcode/SPLIT-2WAY')->assertNotFound()
            ->assertJsonPath('message', '"2 Way Splitter" has no selling price yet. Ask the admin to set its price in Products.');
        $this->actingAs($this->cashier)->get(route('cashier.pos'))->assertOk()
            ->assertSee('onclick="warnNoPrice(&quot;2 Way Splitter&quot;)"', false);

        $unpriced->update(['Price' => 50]);
        $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $unpriced->ProductID, 'qty' => 1]],
            'payment_method' => 'cash', 'payment_amount' => 50,
        ])->assertOk();
    }

    #[DataProvider('vatOnTotalProvider')]
    public function test_checkout_computes_vat_as_a_percent_of_total_without_inflating_total(float $price, float $expectedVat): void
    {
        $product = $this->makeProduct($price);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => $price,
        ]);

        $response->assertOk();
        $billing = SalesTransaction::latest('SalesTransactionID')->firstOrFail();
        $this->assertEquals($price, (float) $billing->Subtotal);
        $this->assertEquals($expectedVat, (float) $billing->VatAmount);
        $this->assertEquals($price, (float) $billing->TotalAmount);
    }

    public static function vatOnTotalProvider(): array
    {
        return [
            'P1500' => [1500.00, 160.71],
            'P1000' => [1000.00, 107.14],
            'P2500' => [2500.00, 267.86],
            'P213' => [213.00, 22.82],
        ];
    }

    public function test_checkout_computes_vat_correctly_across_multiple_quantity(): void
    {
        $product = $this->makeProduct(1500);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 3]],
            'payment_method' => 'cash',
            'payment_amount' => 4500,
        ]);

        $response->assertOk();
        $billing = SalesTransaction::latest('SalesTransactionID')->firstOrFail();
        $this->assertEquals(4500.0, (float) $billing->Subtotal);
        $this->assertEquals(482.14, (float) $billing->VatAmount);
        $this->assertEquals(round(4500 / 1.12, 2), round(4500 - (float) $billing->VatAmount, 2));
        $this->assertEquals(4500.0, (float) $billing->TotalAmount);
    }

    public function test_receipt_item_table_shows_description_quantity_and_line_total_amount(): void
    {
        $product = $this->makeProduct(2500);

        $sale = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 2]],
            'payment_method' => 'cash',
            'payment_amount' => 5000,
        ]);
        $sale->assertOk();

        $receiptResponse = $this->actingAs($this->cashier)->get(route('cashier.receipt', $sale->json('receipt_number')));

        $receiptResponse->assertOk();
        $receiptResponse->assertViewHas('items', function ($items) use ($product) {
            return $items[0]['id'] === $product->ProductID
                && $items[0]['qty'] === 2
                && (float) $items[0]['price'] === 2500.0;
        });

        $receiptResponse->assertSee('<td class="col-qty">2</td>', false);
        $receiptResponse->assertSee('<td class="col-amt">5,000.00</td>', false);
        $onScreen = preg_replace('#<div class="print-text">.*?<pre class="pl-tail">.*?</pre>#s', '', $receiptResponse->getContent());
        $this->assertStringNotContainsString('2 x', $onScreen);
        $receiptResponse->assertDontSee('<td class="col-amt">2,500.00</td>', false);
    }

    public function test_receipt_shows_the_configured_business_address_when_set(): void
    {
        config(['app.business_address' => 'MMQG+3X City of Tacurong, Sultan Kudarat']);
        $product = $this->makeProduct(1000);

        $sale = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => 1000,
        ]);
        $sale->assertOk();

        $receiptResponse = $this->actingAs($this->cashier)->get(route('cashier.receipt', $sale->json('receipt_number')));
        $receiptResponse->assertOk();
        $receiptResponse->assertSee('MMQG+3X City of Tacurong, Sultan Kudarat');
    }

    public function test_receipt_omits_the_address_line_when_not_configured(): void
    {
        config(['app.business_address' => null]);
        $product = $this->makeProduct(1000);

        $sale = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => 1000,
        ]);
        $sale->assertOk();

        $receiptResponse = $this->actingAs($this->cashier)->get(route('cashier.receipt', $sale->json('receipt_number')));
        $receiptResponse->assertOk();
        $receiptResponse->assertDontSee('MMQG+3X');
    }

    public function test_gcash_payment_below_total_is_rejected(): void
    {
        Storage::fake(PaymentProof::DISK);
        $product = $this->makeProduct(1000);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'payment_method' => 'gcash',
            'payment_amount' => 500,
            'payment_proof' => UploadedFile::fake()->image('gcash.jpg'),
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $this->assertDatabaseMissing('SalesTransaction', ['CustomerName' => 'N/A']);
    }

    public function test_gcash_payment_meeting_total_succeeds(): void
    {
        Storage::fake(PaymentProof::DISK);
        $product = $this->makeProduct(1000);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'payment_method' => 'gcash',
            'payment_amount' => 1000,
            'payment_proof' => UploadedFile::fake()->image('gcash.jpg'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_a_sale_without_a_customer_name_is_saved_as_na(): void
    {
        $product = $this->makeProduct(1000);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'customer_name' => '',
            'payment_method' => 'cash',
            'payment_amount' => 1000,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('SalesTransaction', ['CustomerName' => 'N/A']);
        $this->assertDatabaseMissing('SalesTransaction', ['CustomerName' => 'Walk-in Customer']);
    }

    public function test_the_migration_changes_saved_walk_in_customers_to_na(): void
    {
        $product = $this->makeProduct(1000);
        foreach (['', 'Narvaiz'] as $name) {
            $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
                'items' => [['id' => $product->ProductID, 'qty' => 1]],
                'customer_name' => $name,
                'payment_method' => 'cash',
                'payment_amount' => 1000,
            ])->assertOk();
        }
        DB::table('SalesTransaction')->where('CustomerName', 'N/A')->update(['CustomerName' => 'Walk-in Customer']);

        $migration = require database_path('migrations/2026_10_13_111641_change_walk_in_customer_names_to_na.php');
        $migration->up();

        $this->assertSame(['N/A', 'Narvaiz'], DB::table('SalesTransaction')->orderBy('SalesTransactionID')->pluck('CustomerName')->all());
    }

    public function test_checkout_rejects_a_sale_that_exceeds_available_stock(): void
    {
        $product = $this->makeProduct(500, stock: 3);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 5]],
            'payment_method' => 'cash',
            'payment_amount' => 2500,
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $this->assertDatabaseMissing('SalesTransaction', ['CustomerName' => 'N/A']);
        $this->assertSame(3, Inventory::where('ProductID', $product->ProductID)->value('Quantity'));
    }

    public function test_a_second_checkout_for_the_same_cashier_while_one_is_in_flight_is_rejected(): void
    {
        $product = $this->makeProduct(500);
        Cache::lock("checkout:user:{$this->cashier->id}", 10)->get();

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => 500,
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('success', false);
        $this->assertDatabaseMissing('SalesTransaction', ['CustomerName' => 'N/A']);
    }

    public function test_payment_exactly_matching_a_rounded_total_is_accepted(): void
    {
        $product = $this->makeProduct(99.99);
        $discount = Discount::create([
            'DiscountRate' => 7.25, 'Name' => 'Odd Rate',
            'PromoCode' => 'ODDRATE', 'StartDate' => now()->subDay(), 'EndDate' => now()->addMonth(), 'Status' => 'active',
        ]);
        $discount->products()->attach($product->ProductID);

        $subtotal = round(99.99, 2);
        $discountAmount = round($subtotal * (7.25 / 100), 2);
        $total = round($subtotal - $discountAmount, 2);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => $total,
            'discount_id' => $discount->DiscountID,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_payment_amount_above_seven_digits_is_rejected(): void
    {
        $product = $this->makeProduct(500);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => 10000000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('payment_amount');
        $this->assertSame(0, SalesTransaction::count());
    }

    public function test_payment_amount_at_the_seven_digit_maximum_is_accepted(): void
    {
        $product = $this->makeProduct(500);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => 9999999.99,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_pos_screen_wires_up_the_payment_amount_modes(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('cashier.pos'));

        $response->assertOk();
        $response->assertSee('id="auto-fill-payment"', false);
        $response->assertSee('id="change-group"', false);
        $response->assertSee('maxIntegerDigits: 7', false);
        $response->assertSee('function syncPaymentAmountMode()', false);
        $response->assertSee("const hidesChange = isExactOnly || selectedPaymentMethod === 'gcash';", false);
        $response->assertSee("document.getElementById('change-group').style.display = hidesChange ? 'none' : '';", false);
        $response->assertDontSee('oninput="calculateChange()"', false);
        $response->assertSee('function scrollCartLineIntoView(container, itemId)', false);
        $response->assertSee('container.scrollTop = previousScrollTop;', false);
        $response->assertSee('data-cart-item-id="${item.id}"', false);
        $response->assertSee('function confirmRemoveFromCart(id)', false);
        $response->assertSee('onclick="confirmRemoveFromCart(${item.id})"', false);
        $response->assertDontSee('onclick="removeFromCart(${item.id})"', false);
        $response->assertSee('min-height: calc(7 * 79px)', false);
        $response->assertDontSee('html { font-size:', false);
    }

    public function test_a_fully_discounted_sale_with_a_zero_total_completes(): void
    {
        $product = $this->makeProduct(500);
        $discount = Discount::create([
            'DiscountRate' => 100, 'Name' => 'Free Item',
            'PromoCode' => 'FREE100', 'StartDate' => now()->subDay(), 'EndDate' => now()->addMonth(), 'Status' => 'active',
        ]);
        $discount->products()->attach($product->ProductID);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => 0,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertEquals(0.0, (float) SalesTransaction::latest('SalesTransactionID')->value('TotalAmount'));
        $this->assertSame(49, Inventory::where('ProductID', $product->ProductID)->value('Quantity'));
    }

    public function test_receipt_reads_back_the_stored_breakdown_not_a_live_recompute(): void
    {
        $product = $this->makeProduct(1000);
        $discount = Discount::create([
            'DiscountRate' => 10, 'Name' => 'Ten Percent',
            'PromoCode' => 'TENOFF', 'StartDate' => now()->subDay(), 'EndDate' => now()->addMonth(), 'Status' => 'active',
        ]);
        $discount->products()->attach($product->ProductID);

        $sale = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => 900.00,
            'discount_id' => $discount->DiscountID,
        ]);
        $sale->assertOk();
        $receiptNumber = $sale->json('receipt_number');

        $billing = SalesTransaction::where('DiscountID', $discount->DiscountID)->firstOrFail();
        $this->assertEquals(1000.00, (float) $billing->Subtotal);
        $this->assertEquals(100.00, (float) $billing->DiscountAmount);
        $this->assertEquals(96.43, (float) $billing->VatAmount);

        $discount->update(['DiscountRate' => 50]);

        $receiptResponse = $this->actingAs($this->cashier)->get(route('cashier.receipt', $receiptNumber));
        $receiptResponse->assertOk();
        $receiptResponse->assertViewHas('discountAmount', 100.00);
        $receiptResponse->assertViewHas('total', 900.00);
    }

    public function test_receipt_falls_back_to_live_recompute_for_rows_predating_the_breakdown_columns(): void
    {
        $product = $this->makeProduct(1000);
        $discount = Discount::create(['DiscountRate' => 10, 'Name' => 'Legacy Rate']);

        $transaction = SalesTransaction::create([
            'CustomerName' => 'Walk-in Customer',
            'SalesTransactionDate' => now(),
            'StaffID' => Staff::create([
                'FirstName' => 'Legacy', 'MiddleName' => '-', 'LastName' => 'Cashier',
                'ContactNumber' => '0000', 'Email' => 'legacy@example.com', 'Age' => 30, 'Gender' => 'F',
                'UserID' => $this->cashier->id,
            ])->StaffID,
        ]);
        SalesItem::create([
            'Quantity' => 1, 'UnitPrice' => 1000, 'ProductID' => $product->ProductID,
            'SalesTransactionID' => $transaction->SalesTransactionID,
        ]);
        $billing = tap(SalesTransaction::findOrFail($transaction->SalesTransactionID))->update([
            'VatApplied' => '12%',
            'TotalAmount' => 1008,
            'DiscountID' => $discount->DiscountID,
        ]);
        Payment::create([
            'PaymentAmount' => 1008, 'PaymentMethod' => 'cash',
            'ReceiptNumber' => 'RCT-'.str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT),
            'SalesTransactionID' => $billing->SalesTransactionID,
        ]);

        $receiptNumber = 'RCT-'.str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT);

        $receiptResponse = $this->actingAs($this->cashier)->get(route('cashier.receipt', $receiptNumber));
        $receiptResponse->assertOk();
        $receiptResponse->assertViewHas('discountRate', 10);
        $receiptResponse->assertViewHas('discountAmount', 100.0);
    }
}
