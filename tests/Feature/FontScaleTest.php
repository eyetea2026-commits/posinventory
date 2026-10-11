<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Every on-screen page enlarges the base text size (partials/font-scale);
// almost all text is sized in rem, so it all grows together.
class FontScaleTest extends TestCase
{
    use RefreshDatabase;

    private const FONT_SCALE_RULE = 'html { font-size: 112.5%; }';

    public function test_login_page_uses_the_larger_base_font(): void
    {
        $this->get(route('welcome'))->assertOk()->assertSee(self::FONT_SCALE_RULE, false);
    }

    public function test_forgot_password_page_uses_the_larger_base_font(): void
    {
        $this->get(route('admin.forgot'))->assertOk()->assertSee(self::FONT_SCALE_RULE, false);
    }

    public function test_admin_pages_use_the_larger_base_font(): void
    {
        $admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);

        $this->actingAs($admin)->get(route('admin.suppliers.index'))->assertOk()->assertSee(self::FONT_SCALE_RULE, false);
    }
}
