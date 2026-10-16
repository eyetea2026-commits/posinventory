<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    use RefreshDatabase;

    public function test_favicon_is_the_business_logo_icon(): void
    {
        $icon = file_get_contents(public_path('favicon.ico'));

        $this->assertNotEmpty($icon);
        // ICO file header: reserved 0, type 1 (icon).
        $this->assertSame("\x00\x00\x01\x00", substr($icon, 0, 4));
    }

    public function test_login_admin_and_cashier_pages_link_the_favicon(): void
    {
        $link = '<link rel="icon" href="'.asset('favicon.ico').'?v=2">';

        $this->get(route('welcome'))->assertOk()->assertSee($link, false);

        $admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->actingAs($admin)->get(route('admin.purchase-orders.index'))->assertOk()->assertSee($link, false);
        auth()->logout();

        $cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);
        $this->actingAs($cashier)->get(route('cashier.transactions'))->assertOk()->assertSee($link, false);
    }
}
