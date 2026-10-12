<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Every dropdown on admin and cashier pages opens a searchable list with the
// search box at the top (partials/searchable-select, loaded by both layouts).
class SearchableSelectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_load_the_searchable_dropdown(): void
    {
        $admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);

        $html = $this->actingAs($admin)->get(route('admin.products.index'))->assertOk()->getContent();

        $this->assertStringContainsString('window.__searchableSelect', $html);
        $this->assertStringContainsString('placeholder="Search..."', $html);
        // The search box is the first thing inside the panel, above the options.
        $searchBox = strpos($html, 'class="ss-search" placeholder="Search..."');
        $optionList = strpos($html, '<ul class="ss-list"></ul>');
        $this->assertNotFalse($searchBox);
        $this->assertNotFalse($optionList);
        $this->assertLessThan($optionList, $searchBox);
        // The real <select> keeps firing its normal events, so existing handlers still run.
        $this->assertStringContainsString("select.dispatchEvent(new Event('change', { bubbles: true }));", $html);
    }

    public function test_cashier_pages_load_the_searchable_dropdown(): void
    {
        $cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);

        $this->actingAs($cashier)->get(route('cashier.pos'))
            ->assertOk()->assertSee('window.__searchableSelect', false);
    }
}
