<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Every search box searches while typing (no Enter needed). These five
// pages' filter forms do it through data-live-search (partials/live-updates);
// the rest of the system's search boxes already had their own handlers.
class LiveSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private const LIVE_SEARCH_PAGES = [
        'admin.suppliers.index',
        'admin.purchase-orders.index',
        'admin.sales-returns.index',
        'admin.stock-adjustments.index',
        'admin.damages.index',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
    }

    public function test_filter_forms_search_while_typing(): void
    {
        foreach (self::LIVE_SEARCH_PAGES as $route) {
            $html = $this->actingAs($this->admin)->get(route($route))->assertOk()->getContent();

            $this->assertMatchesRegularExpression('#<form method="GET" action="'.preg_quote(route($route), '#').'"[^>]*data-live-search#', $html, $route);
        }

        $script = $this->actingAs($this->admin)->get(route('admin.suppliers.index'))->getContent();
        $this->assertStringContainsString("document.querySelectorAll('form[data-live-search]')", $script);
        $this->assertStringContainsString('const SEARCH_DELAY_MS = 300;', $script);
        $this->assertStringContainsString("form.addEventListener('input'", $script);
    }

    public function test_purchase_order_filters_no_longer_reload_the_page(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.purchase-orders.index'))->getContent();

        $this->assertStringNotContainsString('filterForm.submit()', $html);
    }

    public function test_searching_with_letters_numbers_or_special_characters_works(): void
    {
        Supplier::create(['SupplierName' => 'P&G Supplies 100%', 'ContactNumber' => '1', 'Email' => 'pg@mail.com', 'Address' => 'N/A']);
        Supplier::create(['SupplierName' => 'Acme', 'ContactNumber' => '2', 'Email' => 'acme@mail.com', 'Address' => 'N/A']);

        $this->actingAs($this->admin)->get(route('admin.suppliers.index', ['search' => 'P']))
            ->assertOk()->assertSee('P&amp;G Supplies', false)->assertDontSee('acme@mail.com');
        $this->actingAs($this->admin)->get(route('admin.suppliers.index', ['search' => '100']))
            ->assertOk()->assertSee('P&amp;G Supplies', false);
        $this->actingAs($this->admin)->get(route('admin.suppliers.index', ['search' => '&']))
            ->assertOk()->assertSee('P&amp;G Supplies', false);

        foreach (self::LIVE_SEARCH_PAGES as $route) {
            foreach (['P', '7', '%', "'", '&', '#', '<b>'] as $term) {
                $this->actingAs($this->admin)->get(route($route, ['search' => $term]))->assertOk();
            }
        }
    }
}
