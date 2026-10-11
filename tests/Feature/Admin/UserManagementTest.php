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

    // Regression: the Add New User form has no Email field at all (it's
    // optional and collected nowhere in the UI), so $data['email'] was
    // undefined rather than null and store() threw on every real-world
    // submission — "Something Went Wrong" with no useful detail on screen.
    public function test_admin_can_create_a_cashier_without_providing_an_email(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $cashierRole = Role::create(['role_name' => 'cashier']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'contact_number' => '09171234567',
            'name' => 'juandelacruz',
            'role_id' => $cashierRole->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'name' => 'juandelacruz',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => null,
        ]);
    }

    // The Email field's validation/uniqueness check and JS error-handling
    // wiring all already existed, but the <input> itself was missing from
    // the shared form partial — so an email could never actually be
    // entered anywhere in the UI. Covers both that it now renders and that
    // a submitted value actually persists.
    public function test_add_user_form_renders_an_email_field(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->actingAs($admin)->get(route('admin.users.create'));

        $response->assertOk();
        $response->assertSee('name="email"', false);
    }

    public function test_admin_can_create_a_cashier_with_an_email(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $cashierRole = Role::create(['role_name' => 'cashier']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'contact_number' => '09171234999',
            'email' => 'maria.santos@example.com',
            'name' => 'mariasantos',
            'role_id' => $cashierRole->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'name' => 'mariasantos',
            'email' => 'maria.santos@example.com',
        ]);
    }

    private function baseCashierPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Test',
            'last_name' => 'Cashier',
            'contact_number' => '09171234567',
            'name' => 'testcashier',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ], $overrides);
    }

    public function test_user_store_rejects_an_applicant_under_18(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $cashierRole = Role::create(['role_name' => 'cashier']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->actingAs($admin)->post(
            route('admin.users.store'),
            $this->baseCashierPayload(['role_id' => $cashierRole->id, 'age' => 17])
        );

        $response->assertSessionHasErrors('age');
        $this->assertDatabaseMissing('users', ['name' => 'testcashier']);
    }

    public function test_user_store_accepts_an_applicant_exactly_18(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $cashierRole = Role::create(['role_name' => 'cashier']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->actingAs($admin)->post(
            route('admin.users.store'),
            $this->baseCashierPayload(['role_id' => $cashierRole->id, 'age' => 18])
        );

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['name' => 'testcashier', 'age' => 18]);
    }

    public function test_user_store_rejects_a_contact_number_that_is_not_exactly_11_digits(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $cashierRole = Role::create(['role_name' => 'cashier']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->actingAs($admin)->post(
            route('admin.users.store'),
            $this->baseCashierPayload(['role_id' => $cashierRole->id, 'contact_number' => '0917123456'])
        );

        $response->assertSessionHasErrors('contact_number');
        $this->assertDatabaseMissing('users', ['name' => 'testcashier']);
    }

    public function test_user_store_rejects_a_contact_number_containing_letters(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $cashierRole = Role::create(['role_name' => 'cashier']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->actingAs($admin)->post(
            route('admin.users.store'),
            $this->baseCashierPayload(['role_id' => $cashierRole->id, 'contact_number' => '0917abc4567'])
        );

        $response->assertSessionHasErrors('contact_number');
        $this->assertDatabaseMissing('users', ['name' => 'testcashier']);
    }

    public function test_user_store_rejects_gender_other(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $cashierRole = Role::create(['role_name' => 'cashier']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->actingAs($admin)->post(
            route('admin.users.store'),
            $this->baseCashierPayload(['role_id' => $cashierRole->id, 'gender' => 'Other'])
        );

        $response->assertSessionHasErrors('gender');
        $this->assertDatabaseMissing('users', ['name' => 'testcashier']);
    }

    public function test_user_store_rejects_a_password_with_no_symbol(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $cashierRole = Role::create(['role_name' => 'cashier']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), $this->baseCashierPayload([
            'role_id' => $cashierRole->id,
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['name' => 'testcashier']);
    }

    public function test_add_user_form_renders_the_password_requirements_checklist_and_eye_toggle(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->actingAs($admin)->get(route('admin.users.create'));

        $response->assertOk();
        $response->assertSee('id="password-requirements"', false);
        $response->assertSee('password-toggle-btn', false);
        $response->assertSee('maxlength="11"', false);
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
        $response->assertSee('data-user-id="'.$targetUser->id.'"', false);
        $response->assertSee('id="viewModal"', false);
    }
}
