<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Every on-screen page sets the base text size to 15px (partials/font-scale);
// almost all text is sized in rem, so it all scales together.
class FontScaleTest extends TestCase
{
    use RefreshDatabase;

    private const FONT_SCALE_RULE = 'html { font-size: 93.75%; }';

    public function test_login_page_uses_the_15px_base_font(): void
    {
        $this->get(route('welcome'))->assertOk()->assertSee(self::FONT_SCALE_RULE, false);
    }

    public function test_forgot_password_page_uses_the_15px_base_font(): void
    {
        $this->get(route('admin.forgot'))->assertOk()->assertSee(self::FONT_SCALE_RULE, false);
    }

    public function test_admin_pages_use_the_15px_base_font(): void
    {
        $admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);

        $this->actingAs($admin)->get(route('admin.suppliers.index'))->assertOk()->assertSee(self::FONT_SCALE_RULE, false);
    }
}
