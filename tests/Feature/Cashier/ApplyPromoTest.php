<?php

namespace Tests\Feature\Cashier;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
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
            'ProductName' => 'Bullet 2MP', 'Model' => 'CAM-01',
            'Price' => 1000, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 20, 'Status' => 'Available']);

        $this->otherProduct = Product::create([
            'ProductName' => 'DVR 8CH', 'Model' => 'DVR-01',
            'Price' => 2000, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $this->otherProduct->ProductID, 'Quantity' => 20, 'Status' => 'Available']);
    }

    private function makePromo(array $overrides = [], ?array $productIds = null): Discount
    {
        $discount = Discount::create(array_merge([
            'DiscountRate' => 20,
            'DiscountType' => Discount::TYPE_PERCENTAGE,
            'Name' => 'Summer Sale',
            'PromoCode' => 'SUMMER20',
            'StartDate' => now()->subDay()->format('Y-m-d'),
            'EndDate' => now()->addMonth()->format('Y-m-d'),
        ], $overrides));

        $discount->products()->attach($productIds ?? [$this->product->ProductID]);

        return $discount;
    }

    public function test_apply_promo_succeeds_for_a_valid_code_and_matching_product(): void
    {
        $this->makePromo();

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.pos.apply-promo'), [
            'promo_code' => 'summer20', 'product_id' => $this->product->ProductID,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'discount_rate' => 20, 'promo_code' => 'SUMMER20']);
        $this->assertSame([$this->product->ProductID], $response->json('applicable_product_ids'));
    }

    public function test_apply_promo_rejects_an_unknown_code(): void
    {
        $this->makePromo();

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.pos.apply-promo'), [
            'promo_code' => 'NOSUCHCODE', 'product_id' => $this->product->ProductID,
        ]);

        $response->assertStatus(404);
        $response->assertJsonFragment(['message' => 'Invalid promo code.']);
    }

    public function test_apply_promo_rejects_a_product_with_no_promo_at_all(): void
    {
        $response = $this->actingAs($this->cashier)->postJson(route('cashier.pos.apply-promo'), [
            'promo_code' => 'ANYTHING', 'product_id' => $this->otherProduct->ProductID,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Walang promo sa product na ito.']);
    }

    public function test_apply_promo_rejects_a_code_that_belongs_to_a_different_product(): void
    {
        $this->makePromo();
        $this->makePromo(['PromoCode' => 'DVR20'], [$this->otherProduct->ProductID]);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.pos.apply-promo'), [
            'promo_code' => 'SUMMER20', 'product_id' => $this->otherProduct->ProductID,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'No promo is available for this product.']);
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

    public function test_apply_promo_rejects_a_promo_that_has_not_started_yet(): void
    {
        $this->makePromo(['StartDate' => now()->addDays(5)->format('Y-m-d'), 'EndDate' => now()->addMonth()->format('Y-m-d')]);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.pos.apply-promo'), [
            'promo_code' => 'SUMMER20', 'product_id' => $this->product->ProductID,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'This promo code is not currently active.']);
    }

    public function test_checkout_applies_the_discount_only_to_the_promod_products_line(): void
    {
        $discount = $this->makePromo();

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [
                ['id' => $this->product->ProductID, 'qty' => 1],
                ['id' => $this->otherProduct->ProductID, 'qty' => 1],
            ],
            'payment_method' => 'cash',
            'payment_amount' => 2800,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $billing = SalesTransaction::where('DiscountID', $discount->DiscountID)->firstOrFail();
        $this->assertEquals(3000.0, (float) $billing->Subtotal);
        $this->assertEquals(200.0, (float) $billing->DiscountAmount);
        $this->assertEquals(300.0, (float) $billing->VatAmount);
        $this->assertEquals(2800.0, (float) $billing->TotalAmount);
        $this->assertSame('SUMMER20', $billing->PromoCode);
    }

    public function test_checkout_does_not_discount_a_product_the_promo_is_not_assigned_to(): void
    {
        $this->makePromo();

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $this->otherProduct->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => 2000,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $billing = SalesTransaction::latest('SalesTransactionID')->firstOrFail();
        $this->assertNull($billing->DiscountID);
        $this->assertEquals(0.0, (float) $billing->DiscountAmount);
        $this->assertEquals(2000.0, (float) $billing->TotalAmount);
    }

    public function test_checkout_does_not_discount_a_product_whose_promo_has_expired(): void
    {
        $this->makePromo(['StartDate' => '2020-01-01', 'EndDate' => '2020-01-31']);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $this->product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => 1000,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $billing = SalesTransaction::latest('SalesTransactionID')->firstOrFail();
        $this->assertNull($billing->DiscountID);
        $this->assertEquals(0.0, (float) $billing->DiscountAmount);
        $this->assertEquals(1000.0, (float) $billing->TotalAmount);
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
            'payment_amount' => 2800,
        ]);
        $sale->assertOk();

        $receiptResponse = $this->actingAs($this->cashier)->get(route('cashier.receipt', $sale->json('receipt_number')));

        $receiptResponse->assertOk();
        $receiptResponse->assertViewHas('promoCode', 'SUMMER20');
        $receiptResponse->assertViewHas('promoProductName', 'Bullet 2MP');
        $receiptResponse->assertViewHas('discountRate', 20.0);
    }

    public function test_apply_promo_reports_every_assigned_product_not_just_the_one_clicked(): void
    {
        $this->makePromo([], [$this->product->ProductID, $this->otherProduct->ProductID]);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.pos.apply-promo'), [
            'promo_code' => 'SUMMER20', 'product_id' => $this->product->ProductID,
        ]);

        $response->assertOk();
        $ids = $response->json('applicable_product_ids');
        sort($ids);
        $this->assertSame([$this->product->ProductID, $this->otherProduct->ProductID], $ids);
    }

    public function test_checkout_discounts_both_assigned_products_when_both_are_in_the_cart(): void
    {
        $discount = $this->makePromo([], [$this->product->ProductID, $this->otherProduct->ProductID]);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [
                ['id' => $this->product->ProductID, 'qty' => 1],
                ['id' => $this->otherProduct->ProductID, 'qty' => 1],
            ],
            'payment_method' => 'cash',
            'payment_amount' => 2400,
        ]);

        $response->assertOk();
        $billing = SalesTransaction::where('DiscountID', $discount->DiscountID)->firstOrFail();
        $this->assertEquals(3000.0, (float) $billing->Subtotal);
        $this->assertEquals(600.0, (float) $billing->DiscountAmount);
        $this->assertEquals(257.14, (float) $billing->VatAmount);
        $this->assertEquals(2400.0, (float) $billing->TotalAmount);
    }

    public function test_checkout_discounts_only_the_assigned_product_actually_in_the_cart(): void
    {
        $discount = $this->makePromo([], [$this->product->ProductID, $this->otherProduct->ProductID]);

        $thirdProduct = Product::create([
            'ProductName' => 'NVR 16CH', 'Model' => 'NVR-01',
            'Price' => 500, 'CategoryID' => $this->product->CategoryID,
        ]);
        Inventory::create(['ProductID' => $thirdProduct->ProductID, 'Quantity' => 20, 'Status' => 'Available']);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [
                ['id' => $this->product->ProductID, 'qty' => 1],
                ['id' => $thirdProduct->ProductID, 'qty' => 1],
            ],
            'payment_method' => 'cash',
            'payment_amount' => 1300,
        ]);

        $response->assertOk();
        $billing = SalesTransaction::where('DiscountID', $discount->DiscountID)->firstOrFail();
        $this->assertEquals(1500.0, (float) $billing->Subtotal);
        $this->assertEquals(200.0, (float) $billing->DiscountAmount);
        $this->assertEquals(139.29, (float) $billing->VatAmount);
        $this->assertEquals(1300.0, (float) $billing->TotalAmount);
    }

    public function test_checkout_computes_a_fixed_amount_discount_correctly(): void
    {
        $discount = $this->makePromo([
            'PromoCode' => 'FIXED150', 'DiscountType' => Discount::TYPE_FIXED, 'DiscountRate' => 150,
        ]);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $this->product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => 850,
        ]);

        $response->assertOk();
        $billing = SalesTransaction::where('DiscountID', $discount->DiscountID)->firstOrFail();
        $this->assertEquals(1000.0, (float) $billing->Subtotal);
        $this->assertEquals(150.0, (float) $billing->DiscountAmount);
        $this->assertEquals(91.07, (float) $billing->VatAmount);
        $this->assertEquals(850.0, (float) $billing->TotalAmount);
        $this->assertSame('FIXED150', $billing->PromoCode);
    }

    public function test_checkout_records_the_per_line_discount_amount_on_the_sales_item(): void
    {
        $this->makePromo();

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [
                ['id' => $this->product->ProductID, 'qty' => 2],
                ['id' => $this->otherProduct->ProductID, 'qty' => 1],
            ],
            'payment_method' => 'cash',
            'payment_amount' => 3600,
        ]);

        $response->assertOk();
        $transactionId = $response->json('receipt_number');
        $transactionId = (int) str_replace('RCT-', '', $transactionId);

        $promodItem = SalesItem::where('SalesTransactionID', $transactionId)
            ->where('ProductID', $this->product->ProductID)->firstOrFail();
        $this->assertEquals(400.0, (float) $promodItem->DiscountAmount);

        $unpromodItem = SalesItem::where('SalesTransactionID', $transactionId)
            ->where('ProductID', $this->otherProduct->ProductID)->firstOrFail();
        $this->assertEquals(0.0, (float) $unpromodItem->DiscountAmount);
    }

    public function test_checkout_sums_two_different_promos_but_cannot_attribute_a_single_discount_id(): void
    {
        $this->makePromo(['PromoCode' => 'PROMOA', 'DiscountRate' => 10], [$this->product->ProductID]);
        $this->makePromo(['PromoCode' => 'PROMOB', 'DiscountRate' => 20], [$this->otherProduct->ProductID]);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [
                ['id' => $this->product->ProductID, 'qty' => 1],
                ['id' => $this->otherProduct->ProductID, 'qty' => 1],
            ],
            'payment_method' => 'cash',
            'payment_amount' => 2500,
        ]);

        $response->assertOk();
        $billing = SalesTransaction::latest('SalesTransactionID')->firstOrFail();
        $this->assertEquals(3000.0, (float) $billing->Subtotal);
        $this->assertEquals(500.0, (float) $billing->DiscountAmount);
        $this->assertEquals(267.86, (float) $billing->VatAmount);
        $this->assertEquals(2500.0, (float) $billing->TotalAmount);
        $this->assertNull($billing->DiscountID);
    }

    public function test_pos_page_only_embeds_the_promo_map_entry_for_eligible_products(): void
    {
        $this->makePromo();

        $expired = Product::create([
            'ProductName' => 'Expired Promo Cam', 'Model' => 'CAM-EXP',
            'Price' => 800, 'CategoryID' => $this->product->CategoryID,
        ]);
        Inventory::create(['ProductID' => $expired->ProductID, 'Quantity' => 5, 'Status' => 'Available']);
        $this->makePromo([
            'PromoCode' => 'OLDONE', 'StartDate' => '2020-01-01', 'EndDate' => '2020-01-31',
        ], [$expired->ProductID]);

        $scheduled = Product::create([
            'ProductName' => 'Future Promo Cam', 'Model' => 'CAM-FUT',
            'Price' => 900, 'CategoryID' => $this->product->CategoryID,
        ]);
        Inventory::create(['ProductID' => $scheduled->ProductID, 'Quantity' => 5, 'Status' => 'Available']);
        $this->makePromo([
            'PromoCode' => 'NOTYET', 'StartDate' => now()->addMonth()->format('Y-m-d'), 'EndDate' => now()->addMonths(2)->format('Y-m-d'),
        ], [$scheduled->ProductID]);

        $response = $this->actingAs($this->cashier)->get(route('cashier.pos'));

        $response->assertOk();
        $response->assertSee('"'.$this->product->ProductID.'":{"discount_id"', false);
        $response->assertSee('SUMMER20', false);
        $response->assertDontSee('OLDONE', false);
        $response->assertDontSee('NOTYET', false);
        $response->assertDontSee('"'.$this->otherProduct->ProductID.'":{"discount_id"', false);
        $response->assertDontSee('"'.$expired->ProductID.'":{"discount_id"', false);
        $response->assertDontSee('"'.$scheduled->ProductID.'":{"discount_id"', false);
    }

    public function test_pos_page_product_cards_carry_the_id_and_price_the_promo_display_needs(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('cashier.pos'));

        $response->assertOk();
        $response->assertSee('data-product-id="'.$this->product->ProductID.'"', false);
        $response->assertSee('data-price="1000"', false);
        $response->assertSee('class="price-block"', false);
    }

    public function test_promo_map_endpoint_reflects_a_promo_created_after_the_page_loaded(): void
    {
        $response = $this->actingAs($this->cashier)->getJson(route('cashier.pos.promo-map'));
        $response->assertOk();
        $this->assertArrayNotHasKey((string) $this->product->ProductID, $response->json('products'));

        $this->makePromo();

        $response = $this->actingAs($this->cashier)->getJson(route('cashier.pos.promo-map'));
        $response->assertOk();
        $entry = $response->json('products.'.$this->product->ProductID);
        $this->assertNotNull($entry);
        $this->assertSame('SUMMER20', $entry['code']);
    }
}
