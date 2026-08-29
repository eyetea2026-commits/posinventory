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

    // Creates a promo and assigns it to the given product(s) via the pivot —
    // matches how the admin's "Apply Discount/Promo" tab actually attaches a
    // promo, instead of the old single-ProductID column.
    private function makePromo(array $overrides = [], array $productIds = null): Discount
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
        // A promo exists for this product, so the "unknown code" path is
        // isolated from the separate "product has no promo at all" path
        // covered by test_apply_promo_rejects_a_product_with_no_promo_at_all().
        $this->makePromo();

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.pos.apply-promo'), [
            'promo_code' => 'NOSUCHCODE', 'product_id' => $this->product->ProductID,
        ]);

        $response->assertStatus(404);
        $response->assertJsonFragment(['message' => 'Invalid promo code.']);
    }

    public function test_apply_promo_rejects_a_product_with_no_promo_at_all(): void
    {
        // otherProduct has zero Discount rows assigned to it in this test —
        // neither active, expired, nor future — matching a product that has
        // simply never had a promo.
        $response = $this->actingAs($this->cashier)->postJson(route('cashier.pos.apply-promo'), [
            'promo_code' => 'ANYTHING', 'product_id' => $this->otherProduct->ProductID,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Walang promo sa product na ito.']);
    }

    public function test_apply_promo_rejects_a_code_that_belongs_to_a_different_product(): void
    {
        $this->makePromo();
        // otherProduct has its own (different) promo, so this test isolates
        // "wrong code for this product" from "product has no promo at all"
        // (covered separately by test_apply_promo_rejects_a_product_with_no_promo_at_all()).
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

    // Apply Promo is fully automatic now — processSale() independently
    // determines each cart line's own active promo straight from the
    // database, there's no client-sent discount_id to reject. A promo not
    // assigned to a cart's product simply doesn't discount anything; the
    // sale still succeeds at full price rather than failing.
    public function test_checkout_does_not_discount_a_product_the_promo_is_not_assigned_to(): void
    {
        $this->makePromo();

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $this->otherProduct->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => 2240,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $billing = Billing::latest('BillingID')->firstOrFail();
        $this->assertNull($billing->DiscountID);
        $this->assertEquals(0.0, (float) $billing->DiscountAmount);
        $this->assertEquals(2240.0, (float) $billing->BillingAmount);
    }

    // Even though the DiscountProduct assignment row still exists, an
    // expired promo must never be auto-applied — checked fresh against
    // today's date at checkout time, not trusted from anything sent by the
    // client (there's nothing to trust here in the first place: no
    // discount_id is sent at all anymore).
    public function test_checkout_does_not_discount_a_product_whose_promo_has_expired(): void
    {
        $this->makePromo(['StartDate' => '2020-01-01', 'EndDate' => '2020-01-31']);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $this->product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => 1120,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $billing = Billing::latest('BillingID')->firstOrFail();
        $this->assertNull($billing->DiscountID);
        $this->assertEquals(0.0, (float) $billing->DiscountAmount);
        $this->assertEquals(1120.0, (float) $billing->BillingAmount);
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
        ]);
        $sale->assertOk();

        $receiptResponse = $this->actingAs($this->cashier)->get(route('cashier.receipt', $sale->json('receipt_number')));

        $receiptResponse->assertOk();
        $receiptResponse->assertViewHas('promoCode', 'SUMMER20');
        $receiptResponse->assertViewHas('promoProductName', 'Bullet 2MP');
        // 200 discount / 1000 promo'd-line subtotal = 20%, not 200/3000=6.67%.
        $receiptResponse->assertViewHas('discountRate', 20.0);
    }

    // ---- Multi-product promo behavior (new) ----

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

        // Cart: 1x Bullet 2MP (1000) + 1x DVR 8CH (2000), both assigned to
        // the promo. Subtotal = 3000. Discount on the whole 3000: 600.
        // VAT = (3000-600)*0.12 = 288. Total = 3000-600+288 = 2688.
        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [
                ['id' => $this->product->ProductID, 'qty' => 1],
                ['id' => $this->otherProduct->ProductID, 'qty' => 1],
            ],
            'payment_method' => 'cash',
            'payment_amount' => 2688,
        ]);

        $response->assertOk();
        $billing = Billing::where('DiscountID', $discount->DiscountID)->firstOrFail();
        $this->assertEquals(3000.0, (float) $billing->Subtotal);
        $this->assertEquals(600.0, (float) $billing->DiscountAmount);
        $this->assertEquals(288.0, (float) $billing->VatAmount);
        $this->assertEquals(2688.0, (float) $billing->BillingAmount);
    }

    public function test_checkout_discounts_only_the_assigned_product_actually_in_the_cart(): void
    {
        $discount = $this->makePromo([], [$this->product->ProductID, $this->otherProduct->ProductID]);

        $thirdProduct = Product::create([
            'ProductName' => 'NVR 16CH', 'Model' => 'NVR-01', 'SKU' => 'SKU-003',
            'Price' => 500, 'CategoryID' => $this->product->CategoryID,
        ]);
        Inventory::create(['ProductID' => $thirdProduct->ProductID, 'Quantity' => 20, 'Status' => 'Available']);

        // Cart: 1x Bullet 2MP (1000, assigned) + 1x NVR 16CH (500, NOT
        // assigned). Subtotal = 1500. Discount only on the assigned 1000
        // line: 200. VAT = (1500-200)*0.12 = 156. Total = 1500-200+156 = 1456.
        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [
                ['id' => $this->product->ProductID, 'qty' => 1],
                ['id' => $thirdProduct->ProductID, 'qty' => 1],
            ],
            'payment_method' => 'cash',
            'payment_amount' => 1456,
        ]);

        $response->assertOk();
        $billing = Billing::where('DiscountID', $discount->DiscountID)->firstOrFail();
        $this->assertEquals(1500.0, (float) $billing->Subtotal);
        $this->assertEquals(200.0, (float) $billing->DiscountAmount);
        $this->assertEquals(156.0, (float) $billing->VatAmount);
        $this->assertEquals(1456.0, (float) $billing->BillingAmount);
    }

    // Apply Promo is fully automatic now, computed straight from
    // Discount::discountedPriceFor() for whichever type the promo actually
    // is — this locks in the 'fixed' branch at checkout (the existing
    // coverage above is all 'percentage').
    public function test_checkout_computes_a_fixed_amount_discount_correctly(): void
    {
        $discount = $this->makePromo([
            'PromoCode' => 'FIXED150', 'DiscountType' => Discount::TYPE_FIXED, 'DiscountRate' => 150,
        ]);

        // Bullet 2MP: 1000 - 150 fixed = 850. Subtotal (pre-discount) = 1000.
        // Discount = 150. VAT = (1000-150)*0.12 = 102. Total = 1000-150+102 = 952.
        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $this->product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => 952,
        ]);

        $response->assertOk();
        $billing = Billing::where('DiscountID', $discount->DiscountID)->firstOrFail();
        $this->assertEquals(1000.0, (float) $billing->Subtotal);
        $this->assertEquals(150.0, (float) $billing->DiscountAmount);
        $this->assertEquals(102.0, (float) $billing->VatAmount);
        $this->assertEquals(952.0, (float) $billing->BillingAmount);
        $this->assertSame('FIXED150', $billing->PromoCode);
    }

    // Two different products can each carry their own, entirely independent
    // active promo at once (nothing stops that — the "no overlap" rule only
    // blocks two promos targeting the SAME product). Billing only has room
    // for one DiscountID/PromoCode, predating per-product assignment, so
    // this can't attribute the receipt's promo-code line to a single promo
    // — but the total discount charged must still be exactly correct.
    public function test_checkout_sums_two_different_promos_but_cannot_attribute_a_single_discount_id(): void
    {
        $this->makePromo(['PromoCode' => 'PROMOA', 'DiscountRate' => 10], [$this->product->ProductID]);
        $this->makePromo(['PromoCode' => 'PROMOB', 'DiscountRate' => 20], [$this->otherProduct->ProductID]);

        // Bullet 2MP: 1000 * 10% = 100 off. DVR 8CH: 2000 * 20% = 400 off.
        // Subtotal = 3000. Discount = 500. VAT = (3000-500)*0.12 = 300.
        // Total = 3000-500+300 = 2800.
        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [
                ['id' => $this->product->ProductID, 'qty' => 1],
                ['id' => $this->otherProduct->ProductID, 'qty' => 1],
            ],
            'payment_method' => 'cash',
            'payment_amount' => 2800,
        ]);

        $response->assertOk();
        $billing = Billing::latest('BillingID')->firstOrFail();
        $this->assertEquals(3000.0, (float) $billing->Subtotal);
        $this->assertEquals(500.0, (float) $billing->DiscountAmount);
        $this->assertEquals(300.0, (float) $billing->VatAmount);
        $this->assertEquals(2800.0, (float) $billing->BillingAmount);
        $this->assertNull($billing->DiscountID);
    }

    // The POS page's "Apply Promo" button only ever renders for a product
    // PRODUCT_PROMO_MAP actually names (see pos.blade.php's renderCart()) —
    // this locks in that CashierAuthController::pos() builds that map
    // correctly: present for a product with a currently-active promo,
    // absent for one with none, an expired one, or a not-yet-started one.
    public function test_pos_page_only_embeds_the_promo_map_entry_for_eligible_products(): void
    {
        $this->makePromo();

        $expired = Product::create([
            'ProductName' => 'Expired Promo Cam', 'Model' => 'CAM-EXP', 'SKU' => 'SKU-EXP',
            'Price' => 800, 'CategoryID' => $this->product->CategoryID,
        ]);
        Inventory::create(['ProductID' => $expired->ProductID, 'Quantity' => 5, 'Status' => 'Available']);
        $this->makePromo([
            'PromoCode' => 'OLDONE', 'StartDate' => '2020-01-01', 'EndDate' => '2020-01-31',
        ], [$expired->ProductID]);

        $scheduled = Product::create([
            'ProductName' => 'Future Promo Cam', 'Model' => 'CAM-FUT', 'SKU' => 'SKU-FUT',
            'Price' => 900, 'CategoryID' => $this->product->CategoryID,
        ]);
        Inventory::create(['ProductID' => $scheduled->ProductID, 'Quantity' => 5, 'Status' => 'Available']);
        $this->makePromo([
            'PromoCode' => 'NOTYET', 'StartDate' => now()->addMonth()->format('Y-m-d'), 'EndDate' => now()->addMonths(2)->format('Y-m-d'),
        ], [$scheduled->ProductID]);

        $response = $this->actingAs($this->cashier)->get(route('cashier.pos'));

        $response->assertOk();
        $response->assertSee('"' . $this->product->ProductID . '":{"discount_id"', false);
        $response->assertSee('SUMMER20', false);
        $response->assertDontSee('OLDONE', false);
        $response->assertDontSee('NOTYET', false);
        $response->assertDontSee('"' . $this->otherProduct->ProductID . '":{"discount_id"', false);
        $response->assertDontSee('"' . $expired->ProductID . '":{"discount_id"', false);
        $response->assertDontSee('"' . $scheduled->ProductID . '":{"discount_id"', false);
    }

    // The POS page polls this endpoint (refreshPromoMap() in pos.blade.php)
    // to pick up a promo created/assigned/expired in the admin's Discount
    // Module without needing a page reload — same underlying query as the
    // initial page load, exposed as JSON.
    public function test_promo_map_endpoint_reflects_a_promo_created_after_the_page_loaded(): void
    {
        $response = $this->actingAs($this->cashier)->getJson(route('cashier.pos.promo-map'));
        $response->assertOk();
        $this->assertArrayNotHasKey((string) $this->product->ProductID, $response->json('products'));

        $this->makePromo();

        $response = $this->actingAs($this->cashier)->getJson(route('cashier.pos.promo-map'));
        $response->assertOk();
        $entry = $response->json('products.' . $this->product->ProductID);
        $this->assertNotNull($entry);
        $this->assertSame('SUMMER20', $entry['code']);
    }
}
