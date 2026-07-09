<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_user_details_page_for_browser_requests(): void
    {
        $role = Role::create(['role_name' => 'admin']);
        $admin = User::factory()->create([
            'name' => 'admin',
            'email' => 'admin@example.com',
            'role_id' => $role->id,
            'first_name' => 'System',
            'last_name' => 'Admin',
        ]);

        $targetUser = User::factory()->create([
            'name' => 'cashier1',
            'email' => 'cashier@example.com',
            'role_id' => $role->id,
            'first_name' => 'Cashier',
            'last_name' => 'One',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.show', $targetUser));

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.show');
        $response->assertViewHas('user', fn ($user) => $user->id === $targetUser->id);
    }

    public function test_user_details_route_returns_json_for_ajax_requests(): void
    {
        $role = Role::create(['role_name' => 'admin']);
        $admin = User::factory()->create([
            'name' => 'admin2',
            'email' => 'admin2@example.com',
            'role_id' => $role->id,
        ]);

        $targetUser = User::factory()->create([
            'name' => 'cashier2',
            'email' => 'cashier2@example.com',
            'role_id' => $role->id,
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.users.show', $targetUser));

        $response->assertStatus(200);
        $response->assertJsonPath('user.id', $targetUser->id);
    }

    public function test_user_management_index_renders_view_details_trigger(): void
    {
        $role = Role::create(['role_name' => 'admin']);
        $admin = User::factory()->create([
            'name' => 'admin3',
            'email' => 'admin3@example.com',
            'role_id' => $role->id,
        ]);

        $targetUser = User::factory()->create([
            'name' => 'cashier3',
            'email' => 'cashier3@example.com',
            'role_id' => $role->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertStatus(200);
        $response->assertSee('view-user-btn', false);
        $response->assertSee('data-user-id="' . $targetUser->id . '"', false);
        $response->assertSee('id="viewModal"', false);
    }
}
