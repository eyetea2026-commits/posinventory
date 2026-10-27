<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Return & Damage is one module, like Discounts & Promos: one sidebar item,
// and Return Approval / Damage as the two tabs at the top of it.
class ReturnDamageModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
    }

    public function test_the_sidebar_has_one_return_and_damage_item(): void
    {
        $page = $this->actingAs($this->admin)->get(route('admin.suppliers.index'))->assertOk();

        $page->assertSee('<span>Return &amp; Damage</span>', false);
        $page->assertSee('href="'.route('admin.sales-returns.index').'"', false);
        // No more "Return" dropdown with Approval / Damage under it.
        $page->assertDontSee('<span class="flex-1 text-left">Return</span>', false);
        $page->assertDontSee('<span>Approval</span>', false);
    }

    public function test_both_pages_show_the_two_tabs_with_the_current_one_active(): void
    {
        $returns = $this->actingAs($this->admin)->get(route('admin.sales-returns.index'))->assertOk();
        $returns->assertSee('<h1>Return &amp; Damage</h1>', false);
        $returns->assertSeeInOrder(['class="rd-tab active"', 'Return Approval', 'class="rd-tab "', 'Damage'], false);

        $damage = $this->actingAs($this->admin)->get(route('admin.damages.index'))->assertOk();
        $damage->assertSee('<h1>Return &amp; Damage</h1>', false);
        $damage->assertSeeInOrder(['class="rd-tab "', 'Return Approval', 'class="rd-tab active"', 'Damage'], false);
        $damage->assertSee('href="'.route('admin.sales-returns.index').'"', false);

        // The single sidebar item stays highlighted on either tab.
        foreach ([$returns, $damage] as $page) {
            $page->assertSee('bg-[#273449] font-medium text-white', false);
        }
    }

    // The status tabs are coloured: Pending yellow, Approved green, Declined
    // red, filled solid when selected.
    public function test_the_status_tabs_are_coloured_per_status(): void
    {
        $page = $this->actingAs($this->admin)->get(route('admin.sales-returns.index'))->assertOk();
        $page->assertSee('class="btn status-tab--pending"', false);
        $page->assertSee('class="btn status-tab--approved"', false);
        $page->assertSee('class="btn status-tab--declined"', false);
        $page->assertSee('.status-tabs .status-tab--declined.active { color: #fff; background: #ef4444;', false);

        $this->actingAs($this->admin)->get(route('admin.sales-returns.index', ['status' => 'approved']))->assertOk()
            ->assertSee('class="btn status-tab--approved active"', false)
            ->assertSee('class="btn status-tab--pending"', false);
    }
}
