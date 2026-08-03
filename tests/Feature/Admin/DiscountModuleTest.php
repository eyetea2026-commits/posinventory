<?php

namespace Tests\Feature\Admin;

use App\Models\Billing;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);

        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $this->product = Product::create([
            'ProductName' => 'Bullet 2MP', 'Model' => 'CAM-01', 'SKU' => 'SKU-001',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);
    }

    private function basePromoPayload(array $overrides = []): array
    {
        return array_merge([
            'ProductID' => $this->product->ProductID,
            'DiscountRate' => 20,
            'Name' => 'Summer Sale',
            'PromoCode' => 'SUMMER20',
            'Description' => 'Seasonal promo',
            'StartDate' => now()->format('Y-m-d'),
            'EndDate' => now()->addDays(30)->format('Y-m-d'),
            'Status' => 'active',
        ], $overrides);
    }

    public function test_store_creates_a_product_specific_promo(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.discounts.store'), $this->basePromoPayload());

        $response->assertRedirect(route('admin.discounts.index'));
        $this->assertDatabaseHas('Discount', [
            'ProductID' => $this->product->ProductID, 'PromoCode' => 'SUMMER20', 'DiscountRate' => 20,
        ]);
    }

    public function test_promo_code_is_normalized_to_uppercase(): void
    {
        $this->actingAs($this->admin)->post(route('admin.discounts.store'), $this->basePromoPayload(['PromoCode' => 'summer20']));

        $this->assertDatabaseHas('Discount', ['PromoCode' => 'SUMMER20']);
    }

    public function test_discount_percentage_must_be_between_1_and_100(): void
    {
        $tooLow = $this->actingAs($this->admin)->post(route('admin.discounts.store'), $this->basePromoPayload(['DiscountRate' => 0, 'PromoCode' => 'A']));
        $tooLow->assertSessionHasErrors('DiscountRate');

        $tooHigh = $this->actingAs($this->admin)->post(route('admin.discounts.store'), $this->basePromoPayload(['DiscountRate' => 101, 'PromoCode' => 'B']));
        $tooHigh->assertSessionHasErrors('DiscountRate');
    }

    public function test_promo_code_must_be_unique_case_insensitively(): void
    {
        Discount::create(array_merge($this->basePromoPayload(), ['PromoCode' => 'SUMMER20']));

        $response = $this->actingAs($this->admin)->post(route('admin.discounts.store'), $this->basePromoPayload(['PromoCode' => 'summer20']));

        $response->assertSessionHasErrors('PromoCode');
    }

    public function test_end_date_cannot_be_earlier_than_start_date(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.discounts.store'), $this->basePromoPayload([
            'StartDate' => '2026-06-10', 'EndDate' => '2026-06-01',
        ]));

        $response->assertSessionHasErrors('EndDate');
    }

    public function test_prevents_overlapping_active_promotions_for_the_same_product(): void
    {
        Discount::create(array_merge($this->basePromoPayload(), [
            'PromoCode' => 'FIRST', 'StartDate' => '2026-06-01', 'EndDate' => '2026-06-30', 'Status' => 'active',
        ]));

        $response = $this->actingAs($this->admin)->post(route('admin.discounts.store'), $this->basePromoPayload([
            'PromoCode' => 'SECOND', 'StartDate' => '2026-06-15', 'EndDate' => '2026-07-15', 'Status' => 'active',
        ]));

        $response->assertSessionHasErrors('ProductID');
        $this->assertDatabaseMissing('Discount', ['PromoCode' => 'SECOND']);
    }

    public function test_allows_a_future_promo_for_the_same_product_when_windows_dont_overlap(): void
    {
        Discount::create(array_merge($this->basePromoPayload(), [
            'PromoCode' => 'FIRST', 'StartDate' => '2026-06-01', 'EndDate' => '2026-06-30', 'Status' => 'active',
        ]));

        $response = $this->actingAs($this->admin)->post(route('admin.discounts.store'), $this->basePromoPayload([
            'PromoCode' => 'SECOND', 'StartDate' => '2026-07-01', 'EndDate' => '2026-07-31', 'Status' => 'active',
        ]));

        $response->assertRedirect(route('admin.discounts.index'));
        $this->assertDatabaseHas('Discount', ['PromoCode' => 'SECOND']);
    }

    public function test_inactive_promos_dont_block_overlap_check(): void
    {
        Discount::create(array_merge($this->basePromoPayload(), [
            'PromoCode' => 'FIRST', 'StartDate' => '2026-06-01', 'EndDate' => '2026-06-30', 'Status' => 'inactive',
        ]));

        $response = $this->actingAs($this->admin)->post(route('admin.discounts.store'), $this->basePromoPayload([
            'PromoCode' => 'SECOND', 'StartDate' => '2026-06-15', 'EndDate' => '2026-07-15', 'Status' => 'active',
        ]));

        $response->assertRedirect(route('admin.discounts.index'));
        $this->assertDatabaseHas('Discount', ['PromoCode' => 'SECOND']);
    }

    public function test_check_promo_code_flags_a_case_insensitive_duplicate(): void
    {
        Discount::create($this->basePromoPayload());

        $response = $this->actingAs($this->admin)->postJson(route('admin.discounts.check-promo-code'), ['PromoCode' => 'summer20']);

        $response->assertOk();
        $response->assertJson(['promo_code' => true]);
    }

    public function test_check_promo_code_excludes_its_own_record_when_editing(): void
    {
        $discount = Discount::create($this->basePromoPayload());

        $response = $this->actingAs($this->admin)->postJson(route('admin.discounts.check-promo-code'), [
            'PromoCode' => 'SUMMER20', 'exclude_id' => $discount->DiscountID,
        ]);

        $response->assertOk();
        $response->assertJson(['promo_code' => false]);
    }

    public function test_discounted_price_is_computed_from_product_price(): void
    {
        $discount = Discount::create($this->basePromoPayload(['DiscountRate' => 25]));
        $discount->load('product');

        $this->assertEquals(750.0, $discount->discounted_price); // 1000 * (1 - 0.25)
    }

    public function test_effective_status_is_expired_once_end_date_passes(): void
    {
        $discount = Discount::create(array_merge($this->basePromoPayload(), [
            'StartDate' => '2020-01-01', 'EndDate' => '2020-01-31', 'Status' => 'active',
        ]));

        $this->assertSame(Discount::STATUS_EXPIRED, $discount->effective_status);
    }

    public function test_effective_status_reflects_stored_status_when_not_expired(): void
    {
        $discount = Discount::create($this->basePromoPayload(['Status' => 'inactive']));

        $this->assertSame(Discount::STATUS_INACTIVE, $discount->effective_status);
    }

    public function test_activate_is_blocked_for_an_expired_promo(): void
    {
        $discount = Discount::create(array_merge($this->basePromoPayload(), [
            'StartDate' => '2020-01-01', 'EndDate' => '2020-01-31', 'Status' => 'inactive',
        ]));

        $response = $this->actingAs($this->admin)->post(route('admin.discounts.activate', $discount));

        $response->assertSessionHas('error');
        $this->assertSame('inactive', $discount->fresh()->Status);
    }

    public function test_activate_and_deactivate_toggle_status(): void
    {
        $discount = Discount::create(array_merge($this->basePromoPayload(), ['Status' => 'inactive']));

        $this->actingAs($this->admin)->post(route('admin.discounts.deactivate', $discount));
        $this->assertSame('inactive', $discount->fresh()->Status);

        $this->actingAs($this->admin)->post(route('admin.discounts.activate', $discount));
        $this->assertSame('active', $discount->fresh()->Status);
    }

    public function test_destroy_is_blocked_once_referenced_by_a_billing_record(): void
    {
        $discount = Discount::create($this->basePromoPayload());

        $cashierRole = Role::create(['role_name' => 'cashier']);
        $cashier = User::factory()->create(['role_id' => $cashierRole->id]);
        $staff = Staff::create([
            'FirstName' => 'Jane', 'MiddleName' => '-', 'LastName' => 'Doe',
            'ContactNumber' => '0000', 'Email' => 'jane@example.com', 'Age' => 30, 'Gender' => 'F', 'UserID' => $cashier->id,
        ]);
        $transaction = SalesTransaction::create(['CustomerName' => 'Walk-in', 'SalesTransactionDate' => now(), 'StaffID' => $staff->StaffID]);
        Billing::create([
            'CustomerName' => 'Walk-in', 'VatApplied' => '12%', 'BillingAmount' => 800,
            'BillingDate' => now(), 'DiscountID' => $discount->DiscountID, 'SalesTransactionID' => $transaction->SalesTransactionID,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.discounts.destroy', $discount));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('Discount', ['DiscountID' => $discount->DiscountID]);
    }

    public function test_destroy_soft_deletes_an_unused_promo(): void
    {
        $discount = Discount::create($this->basePromoPayload());

        $this->actingAs($this->admin)->delete(route('admin.discounts.destroy', $discount));

        $this->assertSoftDeleted('Discount', ['DiscountID' => $discount->DiscountID]);
    }

    public function test_show_page_renders_promo_and_product_details(): void
    {
        $discount = Discount::create($this->basePromoPayload());

        $response = $this->actingAs($this->admin)->get(route('admin.discounts.show', $discount));

        $response->assertOk();
        $response->assertSee('Bullet 2MP');
        $response->assertSee('SUMMER20');
        $response->assertSee('Summer Sale');
    }

    public function test_show_page_displays_the_correct_discounted_price(): void
    {
        $discount = Discount::create($this->basePromoPayload(['DiscountRate' => 20]));

        $response = $this->actingAs($this->admin)->get(route('admin.discounts.show', $discount));

        $response->assertOk();
        $response->assertSee('800.00'); // 1000 * (1 - 0.20)
    }

    // Regression guard: same fetch()-follows-redirect collision already
    // fixed on the Category/Damage modules — the Add/Edit Promo modal's
    // AJAX submit carries X-Requested-With and follows its redirect back to
    // this same index route, so a header-only AJAX check would hijack that
    // redirect-follow into JSON instead of the full HTML page the modal's
    // shared submit helper expects.
    public function test_store_via_the_modals_xhr_flow_still_returns_html_after_the_redirect(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->followingRedirects()
            ->post(route('admin.discounts.store'), $this->basePromoPayload());

        $response->assertOk();
        $response->assertSee('Auto-show session messages', false);
        $response->assertSee('Promo code created successfully.');
    }

    public function test_xhr_headers_alone_without_ajax_flag_still_return_the_full_page(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('admin.discounts.index'));

        $response->assertOk();
        $response->assertSee('Promo Codes');
        $response->assertDontSee('"rows":', false);
    }

    public function test_live_search_ajax_returns_filtered_rows_as_json(): void
    {
        Discount::create($this->basePromoPayload(['PromoCode' => 'MATCHME']));

        $otherProduct = Product::create([
            'ProductName' => 'DVR 8CH', 'Model' => 'DVR-01', 'SKU' => 'SKU-002',
            'Price' => 2000, 'CostPrice' => 1200, 'CategoryID' => $this->product->CategoryID,
        ]);
        Discount::create($this->basePromoPayload(['ProductID' => $otherProduct->ProductID, 'PromoCode' => 'NOMATCH']));

        $response = $this->actingAs($this->admin)->getJson(route('admin.discounts.index', ['search' => 'MATCHME', 'ajax' => 1]));

        $response->assertOk();
        $response->assertJsonStructure(['rows', 'pagination']);
        $this->assertStringContainsString('MATCHME', $response->json('rows'));
        $this->assertStringNotContainsString('NOMATCH', $response->json('rows'));
    }

    public function test_currently_active_scope_only_returns_product_tied_non_expired_active_promos(): void
    {
        // Legacy general-rate row from before the redesign (ProductID null).
        Discount::create(['DiscountRate' => 10, 'Name' => 'Old General Discount']);

        $expired = Discount::create(array_merge($this->basePromoPayload(), ['PromoCode' => 'EXPIRED', 'StartDate' => '2020-01-01', 'EndDate' => '2020-01-31']));
        $inactive = Discount::create(array_merge($this->basePromoPayload(), ['PromoCode' => 'INACTIVE', 'Status' => 'inactive']));
        $valid = Discount::create(array_merge($this->basePromoPayload(), ['PromoCode' => 'VALID']));

        $results = Discount::currentlyActive()->pluck('PromoCode');

        $this->assertTrue($results->contains('VALID'));
        $this->assertFalse($results->contains('EXPIRED'));
        $this->assertFalse($results->contains('INACTIVE'));
        $this->assertFalse($results->contains(null));
    }
}
