<?php

namespace Tests\Feature\Cashier;

use App\Models\Billing;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyPromoTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private Product $product;
    private Product $otherProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $cashierRole = Role::create(['role_name' => 'cashier']);
        $this->cashier = User::factory()->create(['role_id' => $cashierRole->id]);

        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $this->product = Product::create([
            'ProductName' => 'Bullet 2MP', 'Model' => 'CAM-01', 'SKU' => 'SKU-001',
            'Price' => 1000, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 20, 'Status' => 'Available']);

        $this->otherProduct = Product::create([
            'ProductName' => 'DVR 8CH', 'Model' => 'DVR-01', 'SKU' => 'SKU-002',
            'Price' => 2000, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $this->otherProduct->ProductID, 'Quantity' => 20, 'Status' => 'Available']);
    }

    private function makePromo(array $overrides = []): Discount
    {
        return Discount::create(array_merge([
            'ProductID' => $this->product->ProductID,
            'DiscountRate' => 20,
            'Name' => 'Summer Sale',
            'PromoCode' => 'SUMMER20',
            'StartDate' => now()->subDay()->format('Y-m-d'),
            'EndDate' => now()->addMonth()->format('Y-m-d'),
            'Status' => 'active',
        ], $overrides));
    }

    public function test_apply_promo_succeeds_for_a_valid_code_and_matching_product(): void
    {
        $this->makePromo();

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.pos.apply-promo'), [
            'promo_code' => 'summer20', 'product_id' => $this->product->ProductID,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'discount_rate' => 20, 'promo_code' => 'SUMMER20']);
    }

    public function test_apply_promo_rejects_an_unknown_code(): void
    {
        $response = $this->actingAs($this->cashier)->postJson(route('cashier.pos.apply-promo'), [
            'promo_code' => 'NOSUCHCODE', 'product_id' => $this->product->ProductID,
        ]);

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }

    public function test_apply_promo_rejects_a_code_that_belongs_to_a_different_product(): void
    {
        $this->makePromo();

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.pos.apply-promo'), [
            'promo_code' => 'SUMMER20', 'product_id' => $this->otherProduct->ProductID,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'This promo code does not apply to the selected product.']);
    }

    public function test_apply_promo_rejects_an_expired_promo(): void
    {
        $this->makePromo(['StartDate' => '2020-01-01', 'EndDate' => '2020-01-31']);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.pos.apply-promo'), [
            'promo_code' => 'SUMMER20', 'product_id' => $this->product->ProductID,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'This promo code has expired.']);
    }

    public function test_apply_promo_rejects_an_inactive_promo(): void
    {
        $this->makePromo(['Status' => 'inactive']);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.pos.apply-promo'), [
            'promo_code' => 'SUMMER20', 'product_id' => $this->product->ProductID,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'This promo code is not currently active.']);
    }

    public function test_checkout_applies_the_discount_only_to_the_promod_products_line(): void
    {
        $discount = $this->makePromo();

        // Cart: 1x Bullet 2MP (promo'd, 1000) + 1x DVR 8CH (not promo'd, 2000).
        // Subtotal = 3000. Discount only on the 1000 line: 1000*0.20 = 200.
        // VAT = (3000-200)*0.12 = 336. Total = 3000-200+336 = 3136.
        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [
                ['id' => $this->product->ProductID, 'qty' => 1],
                ['id' => $this->otherProduct->ProductID, 'qty' => 1],
            ],
            'payment_method' => 'cash',
            'payment_amount' => 3136,
            'discount_id' => $discount->DiscountID,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $billing = Billing::where('DiscountID', $discount->DiscountID)->firstOrFail();
        $this->assertEquals(3000.0, (float) $billing->Subtotal);
        $this->assertEquals(200.0, (float) $billing->DiscountAmount);
        $this->assertEquals(336.0, (float) $billing->VatAmount);
        $this->assertEquals(3136.0, (float) $billing->BillingAmount);
        $this->assertSame('SUMMER20', $billing->PromoCode);
    }

    public function test_checkout_rejects_a_promo_whose_product_is_not_in_the_cart(): void
    {
        $discount = $this->makePromo();

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $this->otherProduct->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => 2240,
            'discount_id' => $discount->DiscountID,
        ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Selected promo does not apply to any product in this cart.']);
    }

    public function test_checkout_rejects_an_expired_promo_even_if_the_id_is_still_valid(): void
    {
        $discount = $this->makePromo(['StartDate' => '2020-01-01', 'EndDate' => '2020-01-31']);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $this->product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => 1120,
            'discount_id' => $discount->DiscountID,
        ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Selected promo is no longer active.']);
    }

    public function test_receipt_shows_promo_code_and_correct_line_based_discount_rate(): void
    {
        $discount = $this->makePromo();

        $sale = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [
                ['id' => $this->product->ProductID, 'qty' => 1],
                ['id' => $this->otherProduct->ProductID, 'qty' => 1],
            ],
            'payment_method' => 'cash',
            'payment_amount' => 3136,
            'discount_id' => $discount->DiscountID,
        ]);
        $sale->assertOk();

        $receiptResponse = $this->actingAs($this->cashier)->get(route('cashier.receipt', $sale->json('receipt_number')));

        $receiptResponse->assertOk();
        $receiptResponse->assertViewHas('promoCode', 'SUMMER20');
        $receiptResponse->assertViewHas('promoProductName', 'Bullet 2MP');
        // 200 discount / 1000 promo'd-line subtotal = 20%, not 200/3000=6.67%.
        $receiptResponse->assertViewHas('discountRate', 20.0);
    }
}
