<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Only the admin pages get the bigger base text size (partials/font-scale,
// fluid: 16px on phones up to 18px on large screens);
// the cashier side and the login pages keep the original size.
class FontScaleTest extends TestCase
{
    use RefreshDatabase;

    private const FONT_SCALE_RULE = 'html { font-size: clamp(100%, 85% + 0.45vw, 112.5%); }';

    public function test_admin_pages_use_the_bigger_base_font(): void
    {
        $admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);

        $this->actingAs($admin)->get(route('admin.suppliers.index'))->assertOk()->assertSee(self::FONT_SCALE_RULE, false);
    }

    public function test_login_page_keeps_the_original_font_size(): void
    {
        $this->get(route('welcome'))->assertOk()->assertDontSee(self::FONT_SCALE_RULE, false);
    }

    public function test_forgot_password_page_keeps_the_original_font_size(): void
    {
        $this->get(route('admin.forgot'))->assertOk()->assertDontSee(self::FONT_SCALE_RULE, false);
    }
}
