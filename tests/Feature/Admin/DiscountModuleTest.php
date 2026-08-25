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

    // Creating/editing a promo (Tab 1) no longer selects a product — that's
    // a separate step in the "Apply Discount/Promo" tab (Tab 2).
    private function basePromoPayload(array $overrides = []): array
    {
        return array_merge([
            'DiscountRate' => 20,
            'DiscountType' => Discount::TYPE_PERCENTAGE,
            'Name' => 'Summer Sale',
            'PromoCode' => 'SUMMER20',
            'Description' => 'Seasonal promo',
            'StartDate' => now()->format('Y-m-d'),
            'EndDate' => now()->addDays(30)->format('Y-m-d'),
        ], $overrides);
    }

    public function test_store_creates_a_promo_with_no_product_assigned(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.discounts.store'), $this->basePromoPayload());

        $response->assertRedirect(route('admin.discounts.index'));
        $discount = Discount::where('PromoCode', 'SUMMER20')->firstOrFail();
        $this->assertSame(20.0, (float) $discount->DiscountRate);
        $this->assertTrue($discount->products->isEmpty());
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

    public function test_discount_type_must_be_percentage(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.discounts.store'), $this->basePromoPayload(['DiscountType' => 'fixed']));

        $response->assertSessionHasErrors('DiscountType');
    }

    // ---- Tab 2: assignProducts() ----

    public function test_assign_products_attaches_a_promo_to_multiple_products(): void
    {
        $discount = Discount::create($this->basePromoPayload());
        $secondProduct = Product::create([
            'ProductName' => 'DVR 8CH', 'Model' => 'DVR-01', 'SKU' => 'SKU-002',
            'Price' => 2000, 'CostPrice' => 1200, 'CategoryID' => $this->product->CategoryID,
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('admin.discounts.assign-products', $discount), [
            'product_ids' => [$this->product->ProductID, $secondProduct->ProductID],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertCount(2, $discount->fresh()->products);
        $this->assertDatabaseHas('DiscountProduct', ['DiscountID' => $discount->DiscountID, 'ProductID' => $this->product->ProductID]);
        $this->assertDatabaseHas('DiscountProduct', ['DiscountID' => $discount->DiscountID, 'ProductID' => $secondProduct->ProductID]);
    }

    public function test_assign_products_never_applies_to_a_product_not_selected(): void
    {
        $discount = Discount::create($this->basePromoPayload());
        $unrelatedProduct = Product::create([
            'ProductName' => 'DVR 8CH', 'Model' => 'DVR-01', 'SKU' => 'SKU-002',
            'Price' => 2000, 'CostPrice' => 1200, 'CategoryID' => $this->product->CategoryID,
        ]);

        $this->actingAs($this->admin)->postJson(route('admin.discounts.assign-products', $discount), [
            'product_ids' => [$this->product->ProductID],
        ]);

        $this->assertFalse($discount->fresh()->products->contains('ProductID', $unrelatedProduct->ProductID));
    }

    // The "overlapping promo" invariant now applies at assign-time (per
    // product being assigned), not at promo-creation time, since a promo no
    // longer has a product until it's explicitly assigned one.
    public function test_assign_products_skips_a_product_already_covered_by_an_overlapping_promo(): void
    {
        $first = Discount::create(array_merge($this->basePromoPayload(), [
            'PromoCode' => 'FIRST', 'StartDate' => '2026-06-01', 'EndDate' => '2026-06-30',
        ]));
        $first->products()->attach($this->product->ProductID);

        $second = Discount::create(array_merge($this->basePromoPayload(), [
            'PromoCode' => 'SECOND', 'StartDate' => '2026-06-15', 'EndDate' => '2026-07-15',
        ]));

        $response = $this->actingAs($this->admin)->postJson(route('admin.discounts.assign-products', $second), [
            'product_ids' => [$this->product->ProductID],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'assigned' => [], 'rejected' => [$this->product->ProductID]]);
        $this->assertFalse($second->fresh()->products->contains('ProductID', $this->product->ProductID));
    }

    public function test_assign_products_allows_a_future_promo_for_the_same_product_when_windows_dont_overlap(): void
    {
        $first = Discount::create(array_merge($this->basePromoPayload(), [
            'PromoCode' => 'FIRST', 'StartDate' => '2026-06-01', 'EndDate' => '2026-06-30',
        ]));
        $first->products()->attach($this->product->ProductID);

        $second = Discount::create(array_merge($this->basePromoPayload(), [
            'PromoCode' => 'SECOND', 'StartDate' => '2026-07-01', 'EndDate' => '2026-07-31',
        ]));

        $response = $this->actingAs($this->admin)->postJson(route('admin.discounts.assign-products', $second), [
            'product_ids' => [$this->product->ProductID],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'assigned' => [$this->product->ProductID]]);
        $this->assertTrue($second->fresh()->products->contains('ProductID', $this->product->ProductID));
    }

    public function test_detach_product_removes_a_single_assignment(): void
    {
        $discount = Discount::create($this->basePromoPayload());
        $discount->products()->attach($this->product->ProductID);

        $response = $this->actingAs($this->admin)->deleteJson(route('admin.discounts.detach-product', [$discount, $this->product]));

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertFalse($discount->fresh()->products->contains('ProductID', $this->product->ProductID));
    }

    public function test_detach_product_is_blocked_once_the_promo_has_been_used_in_a_transaction(): void
    {
        $discount = Discount::create($this->basePromoPayload());
        $discount->products()->attach($this->product->ProductID);
        $this->createBillingFor($discount);

        $response = $this->actingAs($this->admin)->deleteJson(route('admin.discounts.detach-product', [$discount, $this->product]));

        $response->assertStatus(422);
        $this->assertTrue($discount->fresh()->products->contains('ProductID', $this->product->ProductID));
    }

    // ---- History ----

    public function test_history_lists_expired_promos(): void
    {
        $expired = Discount::create(array_merge($this->basePromoPayload(), [
            'PromoCode' => 'EXPIRED', 'StartDate' => '2020-01-01', 'EndDate' => '2020-01-31',
        ]));
        $expired->products()->attach($this->product->ProductID);

        $response = $this->actingAs($this->admin)->getJson(route('admin.discounts.history'));

        $response->assertOk();
        $response->assertJsonStructure(['expiredHtml', 'usedHtml']);
        $this->assertStringContainsString('EXPIRED', $response->json('expiredHtml'));
    }

    public function test_history_lists_used_promos_from_billings(): void
    {
        $discount = Discount::create(array_merge($this->basePromoPayload(), ['PromoCode' => 'USEDPROMO']));
        $discount->products()->attach($this->product->ProductID);
        $this->createBillingFor($discount);

        $response = $this->actingAs($this->admin)->getJson(route('admin.discounts.history'));

        $response->assertOk();
        $this->assertStringContainsString('USEDPROMO', $response->json('usedHtml'));
    }

    // ---- Unrelated products stay unaffected ----

    public function test_index_renders_the_two_tab_apply_ui_and_embeds_the_product_promo_map(): void
    {
        $discount = Discount::create($this->basePromoPayload());
        $discount->products()->attach($this->product->ProductID);

        $response = $this->actingAs($this->admin)->get(route('admin.discounts.index'));

        $response->assertOk();
        $response->assertSee('Create Discount/Promo');
        $response->assertSee('Applied Discount/Promo List');
        $response->assertSee('Bullet 2MP');
        // Main page: only the small "Select a Promo Discount" combo box and
        // the Applied List — product selection lives entirely inside the
        // Apply Discount/Promo popup, triggered by choosing a promo.
        $response->assertSee('Select a Promo Discount');
        $response->assertSee('Choose Promo Discount');
        $response->assertSee('applyProductsModal', false);
        $response->assertSee('applyModalPromoLine', false);
        $response->assertDontSee('Apply to Products');
        $response->assertDontSee('Select Promo Discount', false);
        $response->assertDontSee('Assign Selected');
        $response->assertDontSee('Select Product(s)');
        $response->assertDontSee('applyPromoModal', false);
        $response->assertSee('DISCOUNT_META', false);
    }

    public function test_index_ajax_products_flag_returns_a_capped_json_product_list(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('admin.discounts.index', ['ajax_products' => 1]));

        $response->assertOk();
        $response->assertJsonStructure(['products' => [['id', 'name', 'sku', 'category', 'price']]]);
        $names = collect($response->json('products'))->pluck('name');
        $this->assertTrue($names->contains('Bullet 2MP'));
    }

    public function test_index_ajax_products_flag_filters_by_search(): void
    {
        Product::create([
            'ProductName' => 'DVR 8CH', 'Model' => 'DVR-01', 'SKU' => 'SKU-002',
            'Price' => 2000, 'CostPrice' => 1200, 'CategoryID' => $this->product->CategoryID,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('admin.discounts.index', ['ajax_products' => 1, 'product_search' => 'Bullet']));

        $response->assertOk();
        $names = collect($response->json('products'))->pluck('name');
        $this->assertTrue($names->contains('Bullet 2MP'));
        $this->assertFalse($names->contains('DVR 8CH'));
    }

    public function test_index_ajax_applied_flag_returns_the_applied_assignments_as_json(): void
    {
        $discount = Discount::create($this->basePromoPayload());
        $discount->products()->attach($this->product->ProductID);

        $response = $this->actingAs($this->admin)->getJson(route('admin.discounts.index', ['ajax_applied' => 1]));

        $response->assertOk();
        $response->assertJsonStructure(['rows', 'pagination']);
        $this->assertStringContainsString('Bullet 2MP', $response->json('rows'));
        $this->assertStringContainsString('SUMMER20', $response->json('rows'));
    }

    public function test_applied_list_shows_view_details_and_not_a_delete_action(): void
    {
        $discount = Discount::create($this->basePromoPayload());
        $discount->products()->attach($this->product->ProductID);

        $response = $this->actingAs($this->admin)->getJson(route('admin.discounts.index', ['ajax_applied' => 1]));

        $response->assertOk();
        $rows = $response->json('rows');
        $this->assertStringContainsString('View Details', $rows);
        $this->assertStringContainsString('openPromoDetails', $rows);
        $this->assertStringNotContainsString('detachAppliedProduct', $rows);
        $this->assertStringNotContainsString('fa-trash', $rows);
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

    public function test_discounted_price_for_is_computed_from_product_price(): void
    {
        $discount = Discount::create($this->basePromoPayload(['DiscountRate' => 25]));

        $this->assertEquals(750.0, $discount->discountedPriceFor($this->product)); // 1000 * (1 - 0.25)
    }

    public function test_effective_status_is_expired_once_end_date_passes(): void
    {
        $discount = Discount::create(array_merge($this->basePromoPayload(), [
            'StartDate' => '2020-01-01', 'EndDate' => '2020-01-31',
        ]));

        $this->assertSame(Discount::STATUS_EXPIRED, $discount->effective_status);
    }

    public function test_effective_status_is_inactive_before_start_date(): void
    {
        $discount = Discount::create(array_merge($this->basePromoPayload(), [
            'StartDate' => now()->addDays(10)->format('Y-m-d'), 'EndDate' => now()->addDays(40)->format('Y-m-d'),
        ]));

        $this->assertSame(Discount::STATUS_INACTIVE, $discount->effective_status);
    }

    public function test_effective_status_is_active_within_the_date_window(): void
    {
        $discount = Discount::create($this->basePromoPayload());

        $this->assertSame(Discount::STATUS_ACTIVE, $discount->effective_status);
    }

    // No manual activate/deactivate exists anymore — status is derived
    // purely from the date fields, so it can't drift out of sync with the
    // calendar (and there's no admin action left that could leave a promo
    // "active" past its own End Date, or "inactive" within its own window).

    public function test_destroy_is_blocked_once_referenced_by_a_billing_record(): void
    {
        $discount = Discount::create($this->basePromoPayload());
        $this->createBillingFor($discount);

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

    public function test_show_page_renders_promo_and_assigned_product_details(): void
    {
        $discount = Discount::create($this->basePromoPayload());
        $discount->products()->attach($this->product->ProductID);

        $response = $this->actingAs($this->admin)->get(route('admin.discounts.show', $discount));

        $response->assertOk();
        $response->assertSee('Bullet 2MP');
        $response->assertSee('SUMMER20');
        $response->assertSee('Summer Sale');
    }

    public function test_show_page_displays_the_correct_discounted_price(): void
    {
        $discount = Discount::create($this->basePromoPayload(['DiscountRate' => 20]));
        $discount->products()->attach($this->product->ProductID);

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
        $response->assertSee('Apply Discount/Promo');
        $response->assertDontSee('"rows":', false);
    }

    public function test_live_search_ajax_returns_filtered_rows_as_json(): void
    {
        Discount::create($this->basePromoPayload(['PromoCode' => 'MATCHME']));
        Discount::create($this->basePromoPayload(['PromoCode' => 'NOMATCH']));

        $response = $this->actingAs($this->admin)->getJson(route('admin.discounts.index', ['search' => 'MATCHME', 'ajax' => 1]));

        $response->assertOk();
        $response->assertJsonStructure(['rows', 'pagination']);
        $this->assertStringContainsString('MATCHME', $response->json('rows'));
        $this->assertStringNotContainsString('NOMATCH', $response->json('rows'));
    }

    // Regression guard: the Tab 1 live-search/refresh response must also
    // carry fresh promo data, so the Apply tab's "Choose Promo Discount"
    // dropdown and View Details data stay in sync after a promo is created
    // or edited via the Add/Edit popup — without requiring a page reload.
    public function test_live_search_ajax_also_returns_fresh_apply_tab_data(): void
    {
        $discount = Discount::create($this->basePromoPayload());

        $response = $this->actingAs($this->admin)->getJson(route('admin.discounts.index', ['ajax' => 1]));

        $response->assertOk();
        $response->assertJsonStructure(['rows', 'pagination', 'allDiscounts', 'discountMeta', 'discountProductMap']);
        $names = collect($response->json('allDiscounts'))->pluck('name');
        $this->assertTrue($names->contains('Summer Sale'));
        $this->assertArrayHasKey((string) $discount->DiscountID, $response->json('discountMeta'));
    }

    // Regression guard: View Details must work even for a legacy promo that
    // predates PromoCode (discountMeta/discountProductMap must cover every
    // Discount row, not just ones with a code) — the Apply tab's own
    // "Choose Promo Discount" dropdown is correctly still code-only.
    public function test_apply_tab_data_covers_legacy_discounts_without_a_promo_code(): void
    {
        $legacy = Discount::create(['DiscountRate' => 10, 'Name' => 'Old General Discount', 'DiscountType' => Discount::TYPE_PERCENTAGE]);

        $response = $this->actingAs($this->admin)->get(route('admin.discounts.index'));

        $response->assertOk();
        $this->assertMatchesRegularExpression('/let DISCOUNT_META = ({.*?});/', $response->getContent());
        preg_match('/let DISCOUNT_META = ({.*?});/', $response->getContent(), $matches);
        $discountMeta = json_decode($matches[1], true);

        // Present in discountMeta (View Details data)...
        $this->assertArrayHasKey((string) $legacy->DiscountID, $discountMeta);
        $this->assertSame('Old General Discount', $discountMeta[(string) $legacy->DiscountID]['name']);
        // ...but never selectable from the "Choose Promo Discount" dropdown.
        $response->assertDontSee('Old General Discount</option>', false);
    }

    // Regression guard: Tab 1's "View" action must open the same View
    // Details popup as the Applied List, not navigate to a separate page.
    public function test_promo_list_view_action_opens_the_details_popup_not_a_separate_page(): void
    {
        $discount = Discount::create($this->basePromoPayload());

        $response = $this->actingAs($this->admin)->get(route('admin.discounts.index'));

        $response->assertOk();
        $response->assertSee('window.openPromoDetails(' . $discount->DiscountID . ')', false);
        $response->assertSee('event.preventDefault(); window.openPromoDetails', false);
    }

    // Regression guard: a successful Create must not force a full page
    // reload — Tab 1's table and the Apply tab's promo data both refresh
    // via AJAX instead (see refreshDiscountsTable()/updateApplyTabData()).
    public function test_add_promo_success_handler_does_not_reload_the_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.discounts.index'));

        $response->assertOk();
        $response->assertDontSee('window.location.reload();', false);
    }

    // A promo not yet assigned to any product doesn't count as "currently
    // active" — scopeCurrentlyActive() requires at least one pivot row, so
    // it never applies to a product until the admin explicitly assigns it.
    public function test_currently_active_scope_only_returns_promos_assigned_to_a_product_and_non_expired_started(): void
    {
        Discount::create(['DiscountRate' => 10, 'Name' => 'Unassigned Promo', 'PromoCode' => 'UNASSIGNED', 'DiscountType' => Discount::TYPE_PERCENTAGE]);

        $expired = Discount::create(array_merge($this->basePromoPayload(), ['PromoCode' => 'EXPIRED', 'StartDate' => '2020-01-01', 'EndDate' => '2020-01-31']));
        $expired->products()->attach($this->product->ProductID);

        $notYetStarted = Discount::create(array_merge($this->basePromoPayload(), [
            'PromoCode' => 'FUTURE', 'StartDate' => now()->addDays(10)->format('Y-m-d'), 'EndDate' => now()->addDays(40)->format('Y-m-d'),
        ]));
        $notYetStarted->products()->attach($this->product->ProductID);

        $valid = Discount::create(array_merge($this->basePromoPayload(), ['PromoCode' => 'VALID']));
        $valid->products()->attach($this->product->ProductID);

        $results = Discount::currentlyActive()->pluck('PromoCode');

        $this->assertTrue($results->contains('VALID'));
        $this->assertFalse($results->contains('EXPIRED'));
        $this->assertFalse($results->contains('FUTURE'));
        $this->assertFalse($results->contains('UNASSIGNED'));
    }

    public function test_index_no_longer_shows_activate_deactivate_or_delete_controls(): void
    {
        Discount::create($this->basePromoPayload());

        $response = $this->actingAs($this->admin)->get(route('admin.discounts.index'));

        $response->assertOk();
        $response->assertDontSee('Deactivate');
        $response->assertDontSee('title="Activate"', false);
        $response->assertDontSee('deleteDiscount', false);
    }

    public function test_create_form_no_longer_has_a_status_field(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.discounts.create'));

        $response->assertOk();
        $response->assertDontSee('id="Status"', false);
    }

    public function test_activate_and_deactivate_routes_no_longer_exist(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.discounts.activate'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.discounts.deactivate'));
    }

    private function createBillingFor(Discount $discount): Billing
    {
        $cashierRole = Role::firstOrCreate(['role_name' => 'cashier']);
        $cashier = User::factory()->create(['role_id' => $cashierRole->id]);
        $staff = Staff::create([
            'FirstName' => 'Jane', 'MiddleName' => '-', 'LastName' => 'Doe',
            'ContactNumber' => '0000', 'Email' => 'jane-' . uniqid() . '@example.com', 'Age' => 30, 'Gender' => 'F', 'UserID' => $cashier->id,
        ]);
        $transaction = SalesTransaction::create(['CustomerName' => 'Walk-in', 'SalesTransactionDate' => now(), 'StaffID' => $staff->StaffID]);

        return Billing::create([
            'CustomerName' => 'Walk-in', 'VatApplied' => '12%', 'BillingAmount' => 800,
            'BillingDate' => now(), 'DiscountID' => $discount->DiscountID, 'PromoCode' => $discount->PromoCode,
            'SalesTransactionID' => $transaction->SalesTransactionID,
        ]);
    }
}
