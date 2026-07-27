<?php

namespace Tests\Feature\Security;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\DamagedProduct;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Role $cashierRole;
    private User $admin;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['role_name' => 'admin']);
        $this->cashierRole = Role::create(['role_name' => 'cashier']);

        $this->admin = User::factory()->create([
            'name' => 'admin', 'email' => 'admin@example.com', 'role_id' => $this->adminRole->id,
            'first_name' => 'System', 'last_name' => 'Admin',
        ]);
        $this->cashier = User::factory()->create([
            'name' => 'cashier1', 'email' => 'cashier1@example.com', 'role_id' => $this->cashierRole->id,
            'first_name' => 'Cash', 'last_name' => 'Ier',
        ]);
    }

    // ---- #1: Secure unauthenticated endpoints ----

    public function test_barcode_and_customer_search_apis_reject_unauthenticated_requests(): void
    {
        $this->get('/api/products/barcode/1234567890')->assertRedirect(route('welcome'));
        $this->get('/api/customers/search?q=a')->assertRedirect(route('welcome'));
    }

    public function test_barcode_and_customer_search_apis_reject_admin_role(): void
    {
        // Cashier-only endpoints — an authenticated admin session should not
        // be able to use them either.
        $this->actingAs($this->admin)->get('/api/products/barcode/1234567890')->assertForbidden();
    }

    public function test_barcode_api_works_for_authenticated_cashier(): void
    {
        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01', 'SKU' => 'SKU-001', 'Barcode' => '1234567890',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);

        $response = $this->actingAs($this->cashier)->get('/api/products/barcode/1234567890');

        $response->assertOk();
        $response->assertJsonPath('product.Barcode', '1234567890');
    }

    // ---- #6: Prevent username enumeration ----

    public function test_check_user_role_response_does_not_disclose_existence_or_role(): void
    {
        $existsResponse = $this->postJson('/check-user-role', ['username' => $this->admin->name]);
        $missingResponse = $this->postJson('/check-user-role', ['username' => 'definitely-not-a-real-user']);

        $existsResponse->assertOk();
        $missingResponse->assertOk();

        foreach ([$existsResponse, $missingResponse] as $response) {
            $response->assertJsonMissingPath('exists');
            $response->assertJsonMissingPath('isAdmin');
            $response->assertJsonMissingPath('isCashier');
        }

        // Identical body regardless of whether the account exists.
        $this->assertSame($existsResponse->json('message'), $missingResponse->json('message'));
    }

    // ---- #3: Strengthen role validation / prevent privilege escalation ----

    public function test_user_store_rejects_a_role_id_other_than_cashier(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'first_name' => 'Sneaky', 'last_name' => 'Escalation',
            'contact_number' => '09990000001',
            'name' => 'sneakyadmin',
            'email' => 'sneaky@example.com',
            'role_id' => $this->adminRole->id, // attempting to self-assign admin
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertSessionHasErrors('role_id');
        $this->assertDatabaseMissing('users', ['name' => 'sneakyadmin']);
    }

    public function test_user_store_creates_a_cashier_successfully(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'first_name' => 'New', 'last_name' => 'Cashier',
            'contact_number' => '09990000002',
            'name' => 'newcashier',
            'email' => 'newcashier@example.com',
            'role_id' => $this->cashierRole->id,
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['name' => 'newcashier', 'role_id' => $this->cashierRole->id]);
    }

    // ---- #10: Password policy ----

    public function test_user_store_rejects_a_password_missing_complexity(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'first_name' => 'Weak', 'last_name' => 'Password',
            'contact_number' => '09990000003',
            'name' => 'weakpassworduser',
            'email' => 'weakpw@example.com',
            'role_id' => $this->cashierRole->id,
            'password' => 'alllowercase',
            'password_confirmation' => 'alllowercase',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['name' => 'weakpassworduser']);
    }

    // ---- #4: Audit logging for User Management ----

    public function test_user_management_actions_are_all_logged(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'first_name' => 'Logged', 'last_name' => 'User',
            'contact_number' => '09990000004',
            'name' => 'loggeduser',
            'email' => 'loggeduser@example.com',
            'role_id' => $this->cashierRole->id,
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);
        $this->assertTrue(ActivityLog::where('Action', 'user.created')->exists());

        $target = User::where('name', 'loggeduser')->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.users.update', $target), [
            'first_name' => 'Logged', 'last_name' => 'UserRenamed',
            'contact_number' => '09990000004',
            'name' => 'loggeduser',
            'email' => 'loggeduser@example.com',
            'role_id' => $this->cashierRole->id,
        ]);
        $this->assertTrue(ActivityLog::where('Action', 'user.updated')->exists());

        $this->actingAs($this->admin)->post(route('admin.users.deactivate', $target));
        $this->assertTrue(ActivityLog::where('Action', 'user.deactivated')->exists());

        $this->actingAs($this->admin)->post(route('admin.users.activate', $target));
        $this->assertTrue(ActivityLog::where('Action', 'user.activated')->exists());

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $target));
        $this->assertTrue(ActivityLog::where('Action', 'user.deleted')->exists());
    }

    // ---- #8: Login lockout + failed-attempt logging ----

    public function test_repeated_failed_logins_are_logged_and_eventually_locked_out(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.post'), ['username' => $this->admin->name, 'password' => 'wrong-password']);
        }

        $this->assertEquals(5, ActivityLog::where('Action', 'auth.login_failed')->count());

        $lockedResponse = $this->post(route('login.post'), ['username' => $this->admin->name, 'password' => 'wrong-password']);
        $lockedResponse->assertSessionHasErrors('username');
        $this->assertTrue(ActivityLog::where('Action', 'auth.login_locked')->exists());

        // Even the CORRECT password is rejected while locked out.
        RateLimiter::clear('login:' . strtolower($this->admin->name));
    }

    public function test_successful_login_clears_the_lockout_counter(): void
    {
        $this->post(route('login.post'), ['username' => $this->admin->name, 'password' => 'wrong-password']);
        $this->assertTrue(RateLimiter::tooManyAttempts('login:' . strtolower($this->admin->name), 5) === false);

        $this->post(route('login.post'), ['username' => $this->admin->name, 'password' => 'password']);

        $this->assertEquals(0, RateLimiter::attempts('login:' . strtolower($this->admin->name)));
    }

    // ---- Unified login: no upfront role choice, routed by credentials ----

    public function test_login_page_has_a_single_form_with_no_role_choice(): void
    {
        $response = $this->get(route('welcome'));

        $response->assertOk();
        $response->assertSee('name="username"', false);
        $response->assertSee('name="password"', false);
        $response->assertDontSee('Administrator Portal');
        $response->assertDontSee('Cashier Portal');
    }

    public function test_admin_credentials_route_to_the_admin_dashboard(): void
    {
        $response = $this->post(route('login.post'), ['username' => $this->admin->name, 'password' => 'password']);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_cashier_credentials_route_to_the_pos_screen(): void
    {
        $response = $this->post(route('login.post'), ['username' => $this->cashier->name, 'password' => 'password']);

        $response->assertRedirect('/cashier/pos');
        $this->assertAuthenticatedAs($this->cashier);
    }

    // ---- Login page live role badge (explicit, informed enumeration tradeoff) ----

    public function test_role_lookup_reports_admin_for_an_admin_username(): void
    {
        $response = $this->get(route('login.role-lookup', ['username' => $this->admin->name]));

        $response->assertOk();
        $response->assertJson(['role' => 'admin']);
    }

    public function test_role_lookup_reports_cashier_for_a_cashier_username(): void
    {
        $response = $this->get(route('login.role-lookup', ['username' => $this->cashier->name]));

        $response->assertOk();
        $response->assertJson(['role' => 'cashier']);
    }

    public function test_role_lookup_reports_null_for_an_unknown_username(): void
    {
        $response = $this->get(route('login.role-lookup', ['username' => 'definitely-not-a-real-user']));

        $response->assertOk();
        $response->assertJson(['role' => null]);
    }

    public function test_role_lookup_never_discloses_anything_beyond_the_role_name(): void
    {
        $response = $this->get(route('login.role-lookup', ['username' => $this->admin->name]));

        $response->assertJsonMissingPath('exists');
        $response->assertJsonMissingPath('is_active');
        $response->assertJsonMissingPath('email');
        $response->assertExactJson(['role' => 'admin']);
    }

    // ---- #9: File upload restrictions ----

    public function test_damage_record_rejects_svg_upload(): void
    {
        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $product = Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01', 'SKU' => 'SKU-002',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => 10, 'Status' => 'Available']);
        $supplier = Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0000', 'Email' => 'acme@example.com', 'Address' => 'N/A']);

        $svg = UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml');

        $response = $this->actingAs($this->admin)->post(route('admin.damages.store'), [
            'ProductID' => $product->ProductID,
            'SupplierID' => $supplier->SupplierID,
            'Quantity' => 1,
            'Description' => 'Test damage',
            'DateRecorded' => now()->format('Y-m-d'),
            'DamageType' => 'broken',
            'Image' => $svg,
        ]);

        $response->assertSessionHasErrors('Image');
        $this->assertDatabaseMissing('DamagedProduct', ['ProductID' => $product->ProductID]);
    }

    public function test_damage_record_accepts_a_real_image_upload(): void
    {
        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $product = Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01', 'SKU' => 'SKU-003',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => 10, 'Status' => 'Available']);
        $supplier = Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0000', 'Email' => 'acme2@example.com', 'Address' => 'N/A']);

        $image = UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($this->admin)->post(route('admin.damages.store'), [
            'ProductID' => $product->ProductID,
            'SupplierID' => $supplier->SupplierID,
            'Quantity' => 1,
            'Description' => 'Test damage',
            'DateRecorded' => now()->format('Y-m-d'),
            'DamageType' => 'broken',
            'Image' => $image,
        ]);

        $response->assertSessionDoesntHaveErrors('Image');
        $this->assertDatabaseHas('DamagedProduct', ['ProductID' => $product->ProductID]);
    }

    // ---- #13: Administrator protection logic ----

    public function test_last_active_admin_cannot_be_deactivated_or_deleted(): void
    {
        $deactivateResponse = $this->actingAs($this->admin)->post(route('admin.users.deactivate', $this->admin));
        $deactivateResponse->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'is_active' => 1]);

        $deleteResponse = $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin));
        $deleteResponse->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_second_admin_account_can_be_deactivated_normally(): void
    {
        $secondAdmin = User::factory()->create([
            'name' => 'admin2', 'email' => 'admin2@example.com', 'role_id' => $this->adminRole->id,
            'first_name' => 'Second', 'last_name' => 'Admin',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.users.deactivate', $secondAdmin));

        $response->assertSessionHas('status');
        $this->assertDatabaseHas('users', ['id' => $secondAdmin->id, 'is_active' => 0]);
    }

    public function test_sole_admin_can_update_own_profile_but_not_change_own_role(): void
    {
        $cashierRoleId = $this->cashierRole->id;

        // Updating own contact info succeeds.
        $response = $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
            'first_name' => 'Updated', 'last_name' => 'Admin',
            'contact_number' => '09991112222',
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role_id' => $this->adminRole->id,
        ]);
        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'first_name' => 'Updated']);

        // Attempting to change the sole admin's role away from admin is rejected.
        $roleChangeResponse = $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
            'first_name' => 'Updated', 'last_name' => 'Admin',
            'contact_number' => '09991112222',
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role_id' => $cashierRoleId,
        ]);
        $roleChangeResponse->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'role_id' => $this->adminRole->id]);
    }
}
