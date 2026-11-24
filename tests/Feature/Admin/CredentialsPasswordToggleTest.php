<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CredentialsPasswordToggleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
    }

    public function test_the_credentials_password_has_a_show_button_on_user_management(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertCredentialsToggle($html);
    }

    public function test_the_credentials_password_has_a_show_button_on_edit_user(): void
    {
        $other = User::factory()->create(['role_id' => $this->admin->role_id]);

        $html = $this->actingAs($this->admin)->get(route('admin.users.edit', $other))->assertOk()->getContent();

        $this->assertCredentialsToggle($html);
    }

    private function assertCredentialsToggle(string $html): void
    {
        $this->assertMatchesRegularExpression(
            '#<div class="password-input-wrapper">\s*<input type="password" id="adminCredentialsPassword"[^>]*>\s*<button type="button" class="password-toggle-btn" aria-label="Show password">\s*<i class="fas fa-eye"></i>#',
            $html
        );
        $this->assertStringContainsString("document.querySelectorAll('#adminCredentialsModal .password-toggle-btn, #resetPasswordModal .password-toggle-btn')", $html);
        $this->assertStringContainsString("setPasswordVisible(document.querySelector('#adminCredentialsModal .password-toggle-btn'), false);", $html);
    }
}
