<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeModeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);
    }

    public function test_admin_pages_have_the_light_and_dark_mode_toggle(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.purchase-orders.index'))->assertOk()->getContent();

        $this->assertThemeSwitcher($html, 1);
        $header = substr($html, strpos($html, '<div class="header-controls"'));
        $this->assertLessThan(strpos($header, 'fa-bell'), strpos($header, 'data-theme-toggle'));
        $sidebar = substr($html, strpos($html, '<aside'), strpos($html, '</aside>') - strpos($html, '<aside'));
        $this->assertStringNotContainsString('data-theme-toggle', $sidebar);
    }

    public function test_cashier_pages_have_the_light_and_dark_mode_toggle(): void
    {
        $html = $this->actingAs($this->cashier)->get(route('cashier.pos'))->assertOk()->getContent();

        $this->assertThemeSwitcher($html, 2);
        $sidebar = substr($html, strpos($html, '<aside'), strpos($html, '</aside>') - strpos($html, '<aside'));
        $this->assertStringNotContainsString('data-theme-toggle', $sidebar);
    }

    public function test_printed_reports_are_not_themed(): void
    {
        $this->actingAs($this->admin)->get(route('admin.reports.print', ['type' => 'sales']))->assertOk()
            ->assertDontSee('data-theme-toggle', false)
            ->assertDontSee('window.__themeEngine', false);
    }

    private function assertThemeSwitcher(string $html, int $toggles): void
    {
        $this->assertSame($toggles, preg_match_all('/<button[^>]*\sdata-theme-toggle[\s>]/', $html));
        $this->assertSame($toggles, substr_count($html, '<span class="theme-label-light" title="Light Mode"><i class="fas fa-sun"></i></span>'));
        $this->assertSame($toggles, substr_count($html, '<span class="theme-label-dark" title="Dark Mode"><i class="fas fa-moon"></i></span>'));
        $this->assertStringContainsString('aria-label="Switch between Light Mode and Dark Mode"', $html);
        $this->assertSame(1, substr_count($html, 'window.__themeEngine = true;'));

        $head = substr($html, 0, strpos($html, '</head>'));
        $afterSwitcher = substr($head, strpos($head, 'window.__themeEngine = true;'));
        $this->assertStringContainsString('<link rel="stylesheet"', substr($head, 0, strpos($head, 'window.__themeEngine = true;')));
        $this->assertStringNotContainsString('<link rel="stylesheet"', $afterSwitcher);
        $this->assertStringNotContainsString('<style', $afterSwitcher);
    }
}
