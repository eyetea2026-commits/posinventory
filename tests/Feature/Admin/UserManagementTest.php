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
            'birthdate' => '1995-06-15',
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

    // Three fields per row: First / Middle / Last Name; Birthdate / Contact
    // Number / Email; Address on its own row (a box that can be dragged
    // taller); Gender / Role; Username / Password / Confirm Password.
    public function test_add_user_form_lays_the_fields_out_three_per_row(): void
    {
        $admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);

        $html = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertStringContainsString('.form-grid.user-form-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }', $html);
        $this->assertStringContainsString('<textarea name="address" class="form-input user-address-input" rows="2" maxlength="255">', $html);
        $this->assertStringContainsString('resize: vertical;', $html);
        $this->assertStringContainsString('<div class="form-group user-row-start">', $html);
        // Password rules: two per column, right under both password boxes.
        $this->assertStringContainsString('<div class="form-group password-requirements-row">', $html);
        $this->assertStringContainsString('grid-template-columns: max-content minmax(0, 1fr);', $html);
        $this->assertStringContainsString('grid-auto-flow: column;', $html);
        // The pop-up is 860px at most, not the full screen width.
        $this->assertMatchesRegularExpression('/\.modal-content\.form-modal-content \{\s+max-width: 860px;/', $html);

        $form = substr($html, strpos($html, 'id="addUserForm"'));
        $order = array_map(fn (string $field) => strpos($form, 'name="'.$field.'"'), [
            'first_name', 'middle_name', 'last_name', 'birthdate', 'contact_number', 'email', 'address',
            'gender', 'role_id', 'name', 'password', 'password_confirmation',
        ]);
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order);
        // ...and the rules list comes after Confirm Password.
        $this->assertLessThan(strpos($form, 'id="password-requirements"'), strpos($form, 'name="password_confirmation"'));
    }

    public function test_add_user_popup_checks_the_email_while_typing(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('window.attachEmailCheck = function', false)
            ->assertSee("window.attachEmailCheck(document.querySelector('#addUserForm input[name=\"email\"]'), document.getElementById('error-email'));", false)
            ->assertSee('Please enter a valid email address.');
    }

    public function test_admin_can_create_a_cashier_with_an_email(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $cashierRole = Role::create(['role_name' => 'cashier']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'first_name' => 'Maria',
            'birthdate' => '1995-06-15',
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
            'birthdate' => '1995-06-15',
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
            $this->baseCashierPayload(['role_id' => $cashierRole->id, 'birthdate' => today()->subYears(18)->addDay()->toDateString()])
        );

        $response->assertSessionHasErrors(['birthdate' => 'User must be at least 18 years old.']);
        $this->assertDatabaseMissing('users', ['name' => 'testcashier']);
    }

    public function test_user_store_accepts_an_applicant_exactly_18(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $cashierRole = Role::create(['role_name' => 'cashier']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->actingAs($admin)->post(
            route('admin.users.store'),
            $this->baseCashierPayload(['role_id' => $cashierRole->id, 'birthdate' => today()->subYears(18)->toDateString()])
        );

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['name' => 'testcashier', 'age' => 18]);
        $this->assertSame(today()->subYears(18)->toDateString(), User::where('name', 'testcashier')->first()->birthdate->toDateString());
    }

    public function test_user_store_requires_a_birthdate_and_works_out_the_age(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $cashierRole = Role::create(['role_name' => 'cashier']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $payload = $this->baseCashierPayload(['role_id' => $cashierRole->id]);
        unset($payload['birthdate']);
        $this->actingAs($admin)->post(route('admin.users.store'), $payload)
            ->assertSessionHasErrors(['birthdate' => 'Birthdate is required.']);

        $this->actingAs($admin)->post(route('admin.users.store'), $this->baseCashierPayload(['role_id' => $cashierRole->id, 'birthdate' => today()->subYears(25)->subDays(3)->toDateString()]))
            ->assertRedirect(route('admin.users.index'));
        $user = User::where('name', 'testcashier')->first();
        $this->assertSame(25, $user->age);
        $this->assertSame(25, $user->current_age);

        // The Add User form asks for a birthdate, not an age.
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()
            ->assertSee('<input type="date" name="birthdate"', false)
            ->assertSee('max="'.today()->subYears(18)->toDateString().'"', false)
            ->assertDontSee('name="age"', false);
    }

    public function test_the_age_goes_up_by_itself_on_each_birthday(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $cashierRole = Role::create(['role_name' => 'cashier']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        // Turns 25 three days from now.
        $this->actingAs($admin)->post(route('admin.users.store'), $this->baseCashierPayload([
            'role_id' => $cashierRole->id, 'birthdate' => today()->subYears(25)->addDays(3)->toDateString(),
        ]))->assertRedirect(route('admin.users.index'));
        $user = User::where('name', 'testcashier')->first();
        $this->assertSame(24, $user->age);

        $this->travel(3)->days();
        $this->assertSame(25, $user->fresh()->age);
        $this->actingAs($admin)->getJson(route('admin.users.show', $user))->assertJsonPath('user.age', 25);

        $this->travel(1)->years();
        $this->assertSame(26, $user->fresh()->age);

        // Users without a birthdate keep the age saved for them.
        $older = User::factory()->create(['role_id' => $cashierRole->id, 'age' => 40]);
        $this->assertSame(40, $older->fresh()->age);
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

    public function test_user_store_accepts_gender_prefer_not_to_say(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $cashierRole = Role::create(['role_name' => 'cashier']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->actingAs($admin)->post(
            route('admin.users.store'),
            $this->baseCashierPayload(['role_id' => $cashierRole->id, 'gender' => 'Prefer not to say'])
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['name' => 'testcashier', 'gender' => 'Prefer not to say']);
    }

    public function test_user_form_offers_prefer_not_to_say(): void
    {
        $admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()->assertSee('<option value="Prefer not to say"', false);
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
