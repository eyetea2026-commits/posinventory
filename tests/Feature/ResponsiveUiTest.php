<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Every page (Administrator, Cashier, sign-in, error) loads the shared
// responsive layer (partials/responsive-ui): fluid type, phone spacing, and
// pop-ups that fit every screen and close on Esc / a click on the backdrop.
class ResponsiveUiTest extends TestCase
{
    use RefreshDatabase;

    private const VIEWPORT = '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">';

    private function assertHasResponsiveLayer(string $html): void
    {
        $this->assertStringContainsString(self::VIEWPORT, $html);
        $this->assertStringContainsString('--text-body: clamp(0.875rem, 0.85rem + 0.15vw, 1rem);', $html);
        $this->assertStringContainsString('html.ui-modal-open,', $html);
        $this->assertStringContainsString("if (e.key !== 'Escape' || sweetAlertOpen()) return;", $html);
        $this->assertSame(1, substr_count($html, 'window.__responsiveUi = true;'));
    }

    public function test_admin_cashier_and_guest_pages_load_the_responsive_layer(): void
    {
        $admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);

        $this->assertHasResponsiveLayer($this->actingAs($admin)->get(route('admin.suppliers.index'))->assertOk()->getContent());
        $this->assertHasResponsiveLayer($this->actingAs($cashier)->get(route('cashier.transactions'))->assertOk()->getContent());

        auth()->logout();
        $this->assertHasResponsiveLayer($this->get(route('welcome'))->assertOk()->getContent());
        $this->assertHasResponsiveLayer($this->get(route('admin.forgot'))->assertOk()->getContent());
        $this->assertHasResponsiveLayer($this->get('/this-page-does-not-exist')->assertNotFound()->getContent());
    }

    public function test_pop_ups_fit_the_screen_and_phones_get_stacked_buttons(): void
    {
        $html = $this->get(route('welcome'))->getContent();

        // Never taller than the screen minus the backdrop padding; content scrolls inside.
        $this->assertStringContainsString('max-height: min(90dvh, calc(100dvh - 2 * var(--modal-gutter) - var(--safe-top) - var(--safe-bottom))) !important;', $html);
        // Phones: buttons full width, main action on top; 44px tap targets on touch screens.
        $this->assertStringContainsString('flex-direction: column-reverse;', $html);
        $this->assertStringContainsString('--tap-size: 44px;', $html);
        // Extra-small screens (JioPhone 2, folded Galaxy Z Fold).
        $this->assertStringContainsString('@media (max-width: 359px) {', $html);
        // Every pop-up window is the User Management width (860px), or the
        // screen width minus the gutters on smaller screens.
        $this->assertStringContainsString('--modal-width: 860px;', $html);
        $this->assertStringContainsString("width: var(--modal-width) !important;\n        max-width: calc(100vw - 2 * var(--modal-gutter)) !important;", str_replace("\r\n", "\n", $html));
        // Details inside pop-ups: label above value, four cards per row.
        $this->assertStringContainsString('grid-template-columns: repeat(auto-fill, minmax(min(180px, 100%), 1fr));', $html);
    }

    // Pop-ups use their 860px width: forms three fields per row, details as
    // label-above-value cards instead of one long row per value.
    public function test_pop_up_contents_fill_the_width(): void
    {
        $admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $category = Category::create(['CategoryName' => 'CCTV']);
        $product = Product::create(['ProductName' => 'Dome Camera', 'Model' => 'DC-1', 'Price' => 1500, 'CategoryID' => $category->CategoryID, 'Barcode' => 'CCTVEX-000001']);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => 3, 'ReorderThreshold' => 5, 'Status' => 'Low Stock']);
        $ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

        // Details: Product, Inventory.
        $this->assertStringContainsString('<div class="ui-kv-grid">', $this->actingAs($admin)->getJson(route('admin.products.show', $product), $ajax)->json('html'));
        $this->assertStringContainsString('<div class="ui-kv-photo">', $this->actingAs($admin)->getJson(route('admin.products.show', $product), $ajax)->json('html'));
        $this->assertStringContainsString('class="ui-kv-grid"', $this->actingAs($admin)->getJson(route('admin.inventory.show', $product), $ajax)->json('html'));

        // Forms: Product (Name / Model / Category first), Promo, Stock Adjustment, Category.
        $products = $this->actingAs($admin)->get(route('admin.products.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<div class="form-grid product-form-grid">', $products);
        $form = substr($products, strpos($products, 'product-form-grid">'));
        $this->assertTrue(strpos($form, 'name="ProductName"') < strpos($form, 'name="CategoryID"') && strpos($form, 'name="CategoryID"') < strpos($form, 'name="CostPrice"') && strpos($form, 'name="CostPrice"') < strpos($form, 'name="Description"'));
        $this->assertStringContainsString('<div class="form-grid-2 promo-form-grid">', $this->actingAs($admin)->get(route('admin.discounts.index'))->getContent());
        $this->assertStringContainsString('<div class="form-grid adjustment-form-grid">', $this->actingAs($admin)->get(route('admin.stock-adjustments.index'))->getContent());
        $this->assertStringContainsString('<div class="category-form-grid">', $this->actingAs($admin)->get(route('admin.categories.index'))->getContent());
        $this->assertStringContainsString('<div class="form-grid supplier-form-grid">', $this->actingAs($admin)->getJson(route('admin.suppliers.create'), $ajax)->json('html'));
    }

    // A misplaced </div> in the pagination block left the user list's
    // card open whenever there was a single page, so the pop-ups ended up
    // inside its blurred card and opened far down the page on phones.
    public function test_user_pop_ups_sit_outside_the_user_list_card(): void
    {
        $admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $html = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->getContent();

        $card = strpos($html, '<div class="card glass-card">');
        $modal = strpos($html, '<div id="viewModal" class="modal-overlay">');
        $between = substr($html, $card, $modal - $card);
        $this->assertSame(substr_count($between, '<div'), substr_count($between, '</div>'));
    }
}
