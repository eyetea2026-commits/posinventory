<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $cashierRole;

    private User $admin;

    private User $otherAdmin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['role_name' => 'admin']);
        $this->cashierRole = Role::create(['role_name' => 'cashier']);
        $this->admin = User::factory()->create(['role_id' => $this->adminRole->id]);
        $this->otherAdmin = User::factory()->create(['role_id' => $this->adminRole->id]);
        $this->cashier = User::factory()->create(['role_id' => $this->cashierRole->id]);
    }

    private function newAccount(int $roleId): array
    {
        return [
            'first_name' => 'Maria', 'last_name' => 'Santos',
            'birthdate' => now()->subYears(30)->toDateString(),
            'contact_number' => '09171234567',
            'name' => 'msantos', 'email' => 'msantos@example.com',
            'role_id' => $roleId,
            'password' => 'Secret123!', 'password_confirmation' => 'Secret123!',
        ];
    }

    public function test_the_super_admin_flag_is_gone(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'is_super_admin'));
        $this->assertFalse(method_exists(User::class, 'isSuperAdmin'));
        $this->assertSame('Admin', $this->admin->role_label);

        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk()
            ->assertDontSee('badge-super-admin', false)
            ->assertDontSee('Super Admin');

        $this->actingAs($this->admin)->getJson(route('admin.users.show', $this->otherAdmin))
            ->assertJsonMissingPath('user.is_super_admin')
            ->assertJson(['user' => ['role_label' => 'Admin', 'can_edit' => true]]);
    }

    public function test_any_administrator_can_add_an_administrator(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk()
            ->assertSee('<option value="'.$this->adminRole->id.'" >Administrator</option>', false);

        $this->actingAs($this->admin)->post(route('admin.users.store'), $this->newAccount($this->adminRole->id))
            ->assertSessionHasNoErrors();

        $this->assertTrue(User::where('name', 'msantos')->firstOrFail()->isAdmin());
    }

    public function test_any_administrator_can_add_a_cashier(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), $this->newAccount($this->cashierRole->id))
            ->assertSessionHasNoErrors();

        $this->assertTrue(User::where('name', 'msantos')->firstOrFail()->isCashier());
    }

    public function test_a_cashier_cannot_add_accounts(): void
    {
        $this->actingAs($this->cashier)->post(route('admin.users.store'), $this->newAccount($this->adminRole->id));

        $this->assertDatabaseMissing('users', ['name' => 'msantos']);
    }

    public function test_any_administrator_can_activate_and_deactivate_another_administrator(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.users.deactivate', $this->otherAdmin))->assertOk();
        $this->assertFalse($this->otherAdmin->fresh()->is_active);

        $this->actingAs($this->admin)->postJson(route('admin.users.activate', $this->otherAdmin))->assertOk();
        $this->assertTrue($this->otherAdmin->fresh()->is_active);
    }

    public function test_administrators_can_still_activate_and_deactivate_cashiers(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.users.deactivate', $this->cashier))->assertOk();
        $this->assertFalse($this->cashier->fresh()->is_active);

        $this->actingAs($this->admin)->postJson(route('admin.users.activate', $this->cashier))->assertOk();
        $this->assertTrue($this->cashier->fresh()->is_active);
    }

    public function test_an_administrator_cannot_deactivate_or_delete_their_own_account(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.users.deactivate', $this->admin))
            ->assertForbidden()->assertJson(['error' => 'You can\'t deactivate your own account.']);
        $this->actingAs($this->admin)->deleteJson(route('admin.users.destroy', $this->admin))
            ->assertForbidden()->assertJson(['error' => 'You can\'t delete your own account.']);

        $this->assertTrue($this->admin->fresh()->is_active);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_the_last_active_administrator_cannot_be_deactivated(): void
    {
        $this->otherAdmin->update(['is_active' => false]);
        $thirdAdmin = User::factory()->create(['role_id' => $this->adminRole->id, 'is_active' => false]);

        $this->actingAs($thirdAdmin)->postJson(route('admin.users.deactivate', $this->admin))->assertForbidden();

        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_the_users_list_offers_every_switch_except_the_viewers_own(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertStringContainsString('onchange="handleStatusChange('.$this->cashier->id.', this)"', $html);
        $this->assertStringContainsString('onchange="handleStatusChange('.$this->otherAdmin->id.', this)"', $html);
        $this->assertStringNotContainsString('onchange="handleStatusChange('.$this->admin->id.', this)"', $html);
        $this->assertStringContainsString('Your account', $html);
    }

    public function test_any_administrator_can_edit_another_administrator_and_change_roles(): void
    {
        $this->actingAs($this->admin)->getJson(route('admin.users.edit', $this->otherAdmin))->assertOk();

        $promote = [
            'first_name' => 'Juan', 'last_name' => 'Cruz',
            'contact_number' => '09170000001', 'name' => $this->cashier->name, 'email' => $this->cashier->email,
            'role_id' => $this->adminRole->id,
        ];
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->cashier), $promote)
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->cashier->fresh()->isAdmin());

        $demote = [
            'first_name' => 'Ana', 'last_name' => 'Lopez',
            'contact_number' => '09170000003', 'name' => $this->otherAdmin->name, 'email' => $this->otherAdmin->email,
            'role_id' => $this->cashierRole->id, 'current_password' => 'password',
        ];
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->otherAdmin), $demote)
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->otherAdmin->fresh()->isCashier());
    }

    public function test_an_administrator_cannot_change_their_own_role(): void
    {
        $html = $this->actingAs($this->admin)->getJson(route('admin.users.edit', $this->admin))->assertOk()->json('html');
        $this->assertStringContainsString('<select name="role_id" class="form-select" disabled required>', $html);
        $this->assertStringContainsString('You can&#039;t change your own role.', $html);

        $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
            'first_name' => 'Ana', 'last_name' => 'Reyes',
            'contact_number' => '09170000002', 'name' => $this->admin->name, 'email' => $this->admin->email,
            'current_password' => 'password', 'role_id' => $this->cashierRole->id,
        ])->assertSessionHasErrors(['role_id' => 'You can\'t change your own role.']);
        $this->assertTrue($this->admin->fresh()->isAdmin());
    }

    public function test_an_administrator_can_still_change_another_users_role(): void
    {
        $html = $this->actingAs($this->admin)->getJson(route('admin.users.edit', $this->cashier))->assertOk()->json('html');
        $this->assertStringContainsString('<select name="role_id" class="form-select"  required>', $html);
        $this->assertStringNotContainsString('<input type="hidden" name="role_id"', $html);
    }
}
