<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The Super Admin adds Administrators and is the only one who can activate
// or deactivate them; Administrators manage Cashier accounts only; nobody
// can deactivate or delete the Super Admin.
class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $cashierRole;

    private User $superAdmin;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['role_name' => 'admin']);
        $this->cashierRole = Role::create(['role_name' => 'cashier']);
        $this->superAdmin = User::factory()->create(['role_id' => $this->adminRole->id, 'is_super_admin' => true]);
        $this->admin = User::factory()->create(['role_id' => $this->adminRole->id]);
        $this->cashier = User::factory()->create(['role_id' => $this->cashierRole->id]);
    }

    /**
     * @return array<string, mixed>
     */
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

    public function test_the_existing_administrator_becomes_the_super_admin(): void
    {
        $this->assertTrue($this->superAdmin->isSuperAdmin());
        $this->assertSame('Super Admin', $this->superAdmin->role_label);
        $this->assertFalse($this->admin->isSuperAdmin());
        $this->assertSame('Admin', $this->admin->role_label);
        $this->assertFalse($this->cashier->isSuperAdmin());

        $this->actingAs($this->superAdmin)->get(route('admin.users.index'))->assertOk()
            ->assertSee('badge-super-admin', false)
            ->assertSee('Super Admin');
    }

    public function test_the_super_admin_can_add_an_administrator(): void
    {
        $this->actingAs($this->superAdmin)->get(route('admin.users.index'))->assertOk()
            ->assertSee('<option value="'.$this->adminRole->id.'" >Administrator</option>', false);

        $this->actingAs($this->superAdmin)->post(route('admin.users.store'), $this->newAccount($this->adminRole->id))
            ->assertSessionHasNoErrors();

        $created = User::where('name', 'msantos')->firstOrFail();
        $this->assertTrue($created->isAdmin());
        $this->assertFalse($created->isSuperAdmin());
    }

    public function test_an_administrator_can_only_add_cashiers(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk()
            ->assertDontSee('<option value="'.$this->adminRole->id.'" >Administrator</option>', false);

        $this->actingAs($this->admin)->post(route('admin.users.store'), $this->newAccount($this->adminRole->id))
            ->assertSessionHasErrors(['role_id' => 'Only the Super Admin can add Administrator accounts.']);
        $this->assertDatabaseMissing('users', ['name' => 'msantos']);

        $this->actingAs($this->admin)->post(route('admin.users.store'), $this->newAccount($this->cashierRole->id))
            ->assertSessionHasNoErrors();
        $this->assertTrue(User::where('name', 'msantos')->firstOrFail()->isCashier());
    }

    public function test_only_the_super_admin_can_activate_and_deactivate_administrators(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.users.deactivate', $this->superAdmin))
            ->assertForbidden()->assertJson(['error' => 'The Super Admin account cannot be deactivated.']);
        $otherAdmin = User::factory()->create(['role_id' => $this->adminRole->id]);
        $this->actingAs($this->admin)->postJson(route('admin.users.deactivate', $otherAdmin))
            ->assertForbidden()->assertJson(['error' => 'Only the Super Admin can deactivate Administrator accounts.']);
        $this->assertTrue($otherAdmin->fresh()->is_active);

        $this->actingAs($this->superAdmin)->postJson(route('admin.users.deactivate', $otherAdmin))->assertOk();
        $this->assertFalse($otherAdmin->fresh()->is_active);

        $this->actingAs($this->admin)->postJson(route('admin.users.activate', $otherAdmin))
            ->assertForbidden()->assertJson(['error' => 'Only the Super Admin can activate Administrator accounts.']);
        $this->assertFalse($otherAdmin->fresh()->is_active);

        $this->actingAs($this->superAdmin)->postJson(route('admin.users.activate', $otherAdmin))->assertOk();
        $this->assertTrue($otherAdmin->fresh()->is_active);
    }

    public function test_administrators_can_still_activate_and_deactivate_cashiers(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.users.deactivate', $this->cashier))->assertOk();
        $this->assertFalse($this->cashier->fresh()->is_active);

        $this->actingAs($this->admin)->postJson(route('admin.users.activate', $this->cashier))->assertOk();
        $this->assertTrue($this->cashier->fresh()->is_active);
    }

    public function test_nobody_can_deactivate_or_delete_the_super_admin(): void
    {
        $this->actingAs($this->superAdmin)->postJson(route('admin.users.deactivate', $this->superAdmin))->assertForbidden();
        $this->actingAs($this->admin)->deleteJson(route('admin.users.destroy', $this->superAdmin))->assertForbidden();
        $this->actingAs($this->admin)->deleteJson(route('admin.users.destroy', $this->admin))->assertForbidden();

        $this->assertTrue($this->superAdmin->fresh()->is_active);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_the_users_list_only_offers_the_switch_the_viewer_may_use(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Super Admin only', $html);
        $this->assertStringContainsString('onchange="handleStatusChange('.$this->cashier->id.', this)"', $html);
        $this->assertStringNotContainsString('onchange="handleStatusChange('.$this->superAdmin->id.', this)"', $html);

        $html = $this->actingAs($this->superAdmin)->get(route('admin.users.index'))->assertOk()->getContent();
        $this->assertStringContainsString('onchange="handleStatusChange('.$this->admin->id.', this)"', $html);
        $this->assertStringNotContainsString('onchange="handleStatusChange('.$this->superAdmin->id.', this)"', $html);
    }

    public function test_administrator_roles_and_accounts_are_the_super_admins_to_change(): void
    {
        // An Administrator can't edit the Super Admin or promote a Cashier.
        $this->actingAs($this->admin)->getJson(route('admin.users.edit', $this->superAdmin))->assertForbidden();

        $promote = [
            'first_name' => 'Juan', 'last_name' => 'Cruz',
            'contact_number' => '09170000001', 'name' => $this->cashier->name, 'email' => $this->cashier->email,
            'role_id' => $this->adminRole->id,
        ];
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->cashier), $promote)
            ->assertSessionHasErrors(['role_id' => 'Only the Super Admin can give or remove the Administrator role.']);
        $this->assertTrue($this->cashier->fresh()->isCashier());

        $this->actingAs($this->superAdmin)->put(route('admin.users.update', $this->cashier), $promote)
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->cashier->fresh()->isAdmin());

        $this->actingAs($this->superAdmin)->getJson(route('admin.users.show', $this->superAdmin))
            ->assertJson(['user' => ['role_label' => 'Super Admin', 'is_super_admin' => true, 'can_edit' => true]]);
        $this->actingAs($this->admin)->getJson(route('admin.users.show', $this->superAdmin))
            ->assertJson(['user' => ['can_edit' => false]]);
    }
}
