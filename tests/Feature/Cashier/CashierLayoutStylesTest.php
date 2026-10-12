<?php

namespace Tests\Feature\Cashier;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierLayoutStylesTest extends TestCase
{
    use RefreshDatabase;

    // The notification dropdown's styles must load before the page content,
    // or a long page (the POS) briefly draws the hidden dropdown as raw links.
    public function test_notification_dropdown_styles_load_in_the_head_before_the_content(): void
    {
        $role = Role::create(['role_name' => 'cashier']);
        $cashier = User::factory()->create(['role_id' => $role->id]);

        foreach (['cashier.pos', 'cashier.transactions', 'cashier.refunds'] as $page) {
            $html = $this->actingAs($cashier)->get(route($page))->assertOk()->getContent();

            $head = substr($html, 0, strpos($html, '</head>'));
            $this->assertStringContainsString('.notif-dropdown {', $head, $page);
            $this->assertStringContainsString('.notif-dropdown.active { display: flex; }', $head, $page);
            $this->assertStringContainsString('.toast.show', $head, $page);
            $this->assertStringNotContainsString('.notif-dropdown {', substr($html, strpos($html, '</head>')), $page);
        }
    }
}
