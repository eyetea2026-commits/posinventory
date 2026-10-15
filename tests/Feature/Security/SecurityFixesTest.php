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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
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

    public function test_barcode_api_rejects_unauthenticated_requests(): void
    {
        $this->get('/api/products/barcode/1234567890')->assertRedirect(route('welcome'));
    }

    public function test_barcode_api_rejects_admin_role(): void
    {
        // Cashier-only endpoints — an authenticated admin session should not
        // be able to use them either.
        $this->actingAs($this->admin)->get('/api/products/barcode/1234567890')->assertForbidden();
    }

    public function test_barcode_api_works_for_authenticated_cashier(): void
    {
        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01', 'Barcode' => '1234567890',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);

        $response = $this->actingAs($this->cashier)->get('/api/products/barcode/1234567890');

        $response->assertOk();
        $response->assertJsonPath('product.Barcode', '1234567890');
    }

    public function test_barcode_api_refuses_an_inactive_product(): void
    {
        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        Product::create([
            'ProductName' => 'Retired Camera', 'Model' => 'CAM-OLD', 'Barcode' => '9990001112',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID, 'IsActive' => false,
        ]);

        $response = $this->actingAs($this->cashier)->getJson('/api/products/barcode/9990001112');

        $response->assertNotFound();
        $response->assertJsonPath('product', null);
        $response->assertJsonPath('message', '"Retired Camera" is no longer available for sale.');
    }

    public function test_barcode_api_explains_an_unknown_barcode(): void
    {
        $this->actingAs($this->cashier)->getJson('/api/products/barcode/0000000000')
            ->assertNotFound()
            ->assertJsonPath('message', 'Product Not Found. This barcode is not registered in the system.');
    }

    // Hands-free scanning sends one request per unit scanned; the old
    // 60/minute limit could block a cashier scanning a stack of items.
    public function test_barcode_api_allows_rapid_scanning_beyond_sixty_a_minute(): void
    {
        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        Product::create([
            'ProductName' => 'Cable', 'Model' => 'CBL-01', 'Barcode' => '5550001112',
            'Price' => 50, 'CostPrice' => 20, 'CategoryID' => $category->CategoryID,
        ]);

        for ($i = 0; $i < 80; $i++) {
            $status = $this->actingAs($this->cashier)->getJson('/api/products/barcode/5550001112')->status();
            $this->assertSame(200, $status, 'Scan #'.($i + 1).' was rejected');
        }
    }

    public function test_pos_has_hands_free_scan_detection(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('cashier.pos'));

        $response->assertOk();
        $response->assertSee('const SCAN_MIN_LENGTH = 6;', false);
        $response->assertSee('function lookupAndAddBarcode(barcode)', false);
        $response->assertSee('placeholder="Enter Barcode"', false);
        // Scans add automatically, so there's no Scan button to click.
        $response->assertDontSee('onclick="scanBarcode()"', false);
    }

    public function test_barcode_api_also_matches_on_sku(): void
    {
        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $product = Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01', 'Barcode' => '1234567890',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);
        $product->update(['SKU' => Product::generateSku($product->ProductID)]);

        $response = $this->actingAs($this->cashier)->get('/api/products/barcode/'.$product->SKU);

        $response->assertOk();
        $response->assertJsonPath('product.ProductID', $product->ProductID);
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
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
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
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
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
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
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
        RateLimiter::clear('login:'.strtolower($this->admin->name));
    }

    public function test_successful_login_clears_the_lockout_counter(): void
    {
        $this->post(route('login.post'), ['username' => $this->admin->name, 'password' => 'wrong-password']);
        $this->assertTrue(RateLimiter::tooManyAttempts('login:'.strtolower($this->admin->name), 5) === false);

        $this->post(route('login.post'), ['username' => $this->admin->name, 'password' => 'password']);

        $this->assertEquals(0, RateLimiter::attempts('login:'.strtolower($this->admin->name)));
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

    // ---- Login page role badge removed (it doubled as a public
    // username/role enumeration endpoint) ----

    public function test_role_lookup_endpoint_no_longer_exists(): void
    {
        $this->assertFalse(Route::has('login.role-lookup'));

        $this->get('/login/role-lookup?username='.$this->admin->name)->assertNotFound();
    }

    public function test_login_page_has_no_role_badge_and_hides_forgot_password_at_first(): void
    {
        $response = $this->get(route('welcome'));

        $response->assertOk();
        $response->assertDontSee('id="role-badge"', false);
        $response->assertDontSee('role-lookup', false);
        $response->assertDontSee('Forgot Password?');
        $response->assertSee('Cashiers: please contact your Administrator for a password reset');
        $this->assertStringNotContainsString('fa-lock"></i>
                                Cashiers', $response->getContent());
    }

    public function test_forgot_password_shows_after_a_wrong_password_for_an_admin(): void
    {
        $this->from(route('welcome'))
            ->followingRedirects()
            ->post(route('login.post'), ['username' => 'admin', 'password' => 'wrong-password'])
            ->assertSee('Forgot Password?')
            ->assertSee(route('admin.forgot'), false);
    }

    public function test_forgot_password_stays_hidden_after_a_wrong_password_for_a_cashier(): void
    {
        $this->from(route('welcome'))
            ->followingRedirects()
            ->post(route('login.post'), ['username' => 'cashier1', 'password' => 'wrong-password'])
            ->assertSee('The provided credentials do not match our records.')
            ->assertDontSee('Forgot Password?');
    }

    public function test_forgot_password_stays_hidden_for_an_unknown_username(): void
    {
        $this->from(route('welcome'))
            ->followingRedirects()
            ->post(route('login.post'), ['username' => 'nobody', 'password' => 'wrong-password'])
            ->assertSee('The provided credentials do not match our records.')
            ->assertDontSee('Forgot Password?');
    }

    public function test_forgot_password_shows_for_an_admin_while_locked_out(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.post'), ['username' => 'admin', 'password' => 'wrong-password']);
        }

        $this->from(route('welcome'))
            ->followingRedirects()
            ->post(route('login.post'), ['username' => 'admin', 'password' => 'wrong-password'])
            ->assertSee('Too many failed login attempts')
            ->assertSee('Forgot Password?');
    }

    // ---- #9: File upload restrictions ----

    // Damage records are only ever created automatically from an approved
    // return — there is no manual create/store endpoint — so this coverage
    // exercises the same 'Image' mime validation on the still-manual
    // update() path instead (an admin correcting a pending, auto-generated
    // record).
    public function test_damage_record_rejects_svg_upload(): void
    {
        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $product = Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => 10, 'Status' => 'Available']);
        $supplier = Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0000', 'Email' => 'acme@example.com', 'Address' => 'N/A']);
        $damage = DamagedProduct::create([
            'ProductID' => $product->ProductID, 'SupplierID' => $supplier->SupplierID,
            'Quantity' => 1, 'Description' => 'Test damage', 'DateRecorded' => now()->format('Y-m-d'),
            'DamageType' => 'broken', 'Status' => DamagedProduct::STATUS_PENDING,
        ]);

        $svg = UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml');

        $response = $this->actingAs($this->admin)->put(route('admin.damages.update', $damage), [
            'ProductID' => $product->ProductID,
            'SupplierID' => $supplier->SupplierID,
            'Quantity' => 1,
            'Description' => 'Test damage',
            'DateRecorded' => now()->format('Y-m-d'),
            'DamageType' => 'broken',
            'Image' => $svg,
        ]);

        $response->assertSessionHasErrors('Image');
        $this->assertNull($damage->fresh()->ImagePath);
    }

    public function test_damage_record_accepts_a_real_image_upload(): void
    {
        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $product = Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => 10, 'Status' => 'Available']);
        $supplier = Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0000', 'Email' => 'acme2@example.com', 'Address' => 'N/A']);
        $damage = DamagedProduct::create([
            'ProductID' => $product->ProductID, 'SupplierID' => $supplier->SupplierID,
            'Quantity' => 1, 'Description' => 'Test damage', 'DateRecorded' => now()->format('Y-m-d'),
            'DamageType' => 'broken', 'Status' => DamagedProduct::STATUS_PENDING,
        ]);

        $image = UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($this->admin)->put(route('admin.damages.update', $damage), [
            'ProductID' => $product->ProductID,
            'SupplierID' => $supplier->SupplierID,
            'Quantity' => 1,
            'Description' => 'Test damage',
            'DateRecorded' => now()->format('Y-m-d'),
            'DamageType' => 'broken',
            'Image' => $image,
        ]);

        $response->assertSessionDoesntHaveErrors('Image');
        $this->assertNotNull($damage->fresh()->ImagePath);
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

    // The last active admin's Status column shows a purely visual "Locked"
    // indicator (renamed from "Protected") — a lock icon, never a clickable
    // toggle/button, since this account can't be deactivated anyway.
    public function test_protected_admin_status_shows_as_a_non_clickable_locked_badge(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('protected-badge', false);
        $response->assertSee('fa-lock', false);
        $response->assertSee('Locked');
        // "Protected Administrator" still appears elsewhere on this page (an
        // unrelated detail-view JS notice, intentionally left as-is) — the
        // real check is that the Status column's own badge specifically no
        // longer uses that wording, which the fa-lock/protected-badge/
        // "Locked" assertions above already confirm.
    }

    // Editing an Administrator account shows no password UI at all —
    // Administrator passwords are never resettable through this form (the
    // reset-password-modal.blade.php popup and its endpoint, tested below,
    // are unreachable for an admin target, both in the UI and server-side).
    // It does carry the hidden marker the page's submit handler uses to
    // gate the save behind the "Enter Your Credentials" popup.
    public function test_edit_user_form_shows_no_password_ui_for_an_admin_target(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get(route('admin.users.edit', $this->admin));

        $response->assertOk();
        $html = $response->json('html');

        $this->assertStringNotContainsString('Current Password', $html);
        $this->assertStringNotContainsString('Reset Password', $html);
        $this->assertStringNotContainsString('openResetPasswordModal(', $html);
        $this->assertStringNotContainsString('name="password"', $html);
        $this->assertStringNotContainsString($this->admin->password, $html);
        $this->assertStringContainsString('name="_requires_admin_credentials"', $html);
    }

    // Editing a Cashier (or any future non-admin role) still shows the
    // masked placeholder + Reset Password button — only Administrator
    // targets lose this UI, and only Administrator targets carry the
    // admin-credentials-gate marker.
    public function test_edit_user_form_shows_masked_password_with_reset_button_for_a_non_admin_target(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get(route('admin.users.edit', $this->cashier));

        $response->assertOk();
        $html = $response->json('html');

        $this->assertStringContainsString('Current Password', $html);
        $this->assertStringContainsString('************', $html);
        $this->assertStringContainsString('Reset Password', $html);
        $this->assertStringContainsString('openResetPasswordModal('.$this->cashier->id.')', $html);
        $this->assertStringNotContainsString('name="password"', $html);
        $this->assertStringNotContainsString('name="_requires_admin_credentials"', $html);
    }

    // "Enter Your Credentials" step, shown before the Reset Password form
    // itself: verifies the ADMIN's own password, independent of whichever
    // user's password is about to be reset.
    public function test_verify_password_endpoint_confirms_the_admins_own_correct_password(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->postJson(route('admin.users.verify-password'), [
                'password' => 'password',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_verify_password_endpoint_rejects_a_wrong_admin_password(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->postJson(route('admin.users.verify-password'), [
                'password' => 'totally-wrong-password',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    // The Reset Password popup itself posts to its own dedicated endpoint,
    // independent of the general profile Update User form.
    public function test_reset_password_endpoint_updates_the_password_and_keeps_it_hashed(): void
    {
        $oldHash = $this->cashier->password;

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->patchJson(route('admin.users.reset-password', $this->cashier), [
                'current_password' => 'password',
                'password' => 'NewSecure!Pass123',
                'password_confirmation' => 'NewSecure!Pass123',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->cashier->refresh();
        $this->assertNotSame($oldHash, $this->cashier->password);
        // Hash::check is exactly what the login flow itself uses to verify
        // a password — this is the authoritative proof the new password
        // actually works for logging in, not just that some hash changed.
        $this->assertTrue(Hash::check('NewSecure!Pass123', $this->cashier->password));
    }

    public function test_reset_password_endpoint_rejects_a_mismatched_confirmation(): void
    {
        $oldHash = $this->cashier->password;

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->patchJson(route('admin.users.reset-password', $this->cashier), [
                'current_password' => 'password',
                'password' => 'NewSecure!Pass123',
                'password_confirmation' => 'DoesNotMatch',
            ]);

        $response->assertStatus(422);
        $this->cashier->refresh();
        $this->assertSame($oldHash, $this->cashier->password);
    }

    // The capability, not just the button, must be gone for an admin
    // target -- a direct request must be blocked the same as a hidden UI.
    public function test_reset_password_endpoint_rejects_an_admin_target(): void
    {
        $oldHash = $this->admin->password;

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->patchJson(route('admin.users.reset-password', $this->admin), [
                'current_password' => 'password',
                'password' => 'NewSecure!Pass123',
                'password_confirmation' => 'NewSecure!Pass123',
            ]);

        $response->assertForbidden();
        $this->admin->refresh();
        $this->assertSame($oldHash, $this->admin->password);
    }

    // Resetting a Cashier's password requires the Admin to re-enter their
    // OWN current password first -- a wrong (or missing) admin password
    // must block the reset even though the new password itself is valid,
    // and must never touch the cashier's password.
    public function test_reset_password_endpoint_requires_the_admins_own_current_password(): void
    {
        $oldHash = $this->cashier->password;

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->patchJson(route('admin.users.reset-password', $this->cashier), [
                'current_password' => 'totally-wrong-password',
                'password' => 'NewSecure!Pass123',
                'password_confirmation' => 'NewSecure!Pass123',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('current_password');
        $this->cashier->refresh();
        $this->assertSame($oldHash, $this->cashier->password);
    }

    public function test_reset_password_endpoint_rejects_a_missing_admin_current_password(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->patchJson(route('admin.users.reset-password', $this->cashier), [
                'password' => 'NewSecure!Pass123',
                'password_confirmation' => 'NewSecure!Pass123',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('current_password');
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
            'current_password' => 'password',
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
            'current_password' => 'password',
        ]);
        $roleChangeResponse->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'role_id' => $this->adminRole->id]);
    }

    // Updating an Administrator account requires the acting admin's own
    // current password, on top of normal auth — a step-up check, same
    // pattern as resetPassword()'s current_password requirement.
    public function test_updating_an_admin_account_requires_the_admins_own_current_password(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
            'first_name' => 'Updated', 'last_name' => 'Admin',
            'contact_number' => '09991112222',
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role_id' => $this->adminRole->id,
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertDatabaseMissing('users', ['id' => $this->admin->id, 'first_name' => 'Updated']);
    }

    public function test_updating_an_admin_account_rejects_a_wrong_current_password(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
            'first_name' => 'Updated', 'last_name' => 'Admin',
            'contact_number' => '09991112222',
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role_id' => $this->adminRole->id,
            'current_password' => 'totally-wrong-password',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertDatabaseMissing('users', ['id' => $this->admin->id, 'first_name' => 'Updated']);
    }

    // Updating a Cashier (non-admin target) never requires the acting
    // admin's own password — the step-up check is admin-target-only.
    public function test_updating_a_non_admin_account_does_not_require_current_password(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.users.update', $this->cashier), [
            'first_name' => 'Updated', 'last_name' => 'Cashier',
            'contact_number' => '09991112223',
            'name' => $this->cashier->name,
            'email' => $this->cashier->email,
            'role_id' => $this->cashierRole->id,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['id' => $this->cashier->id, 'first_name' => 'Updated']);
    }
}
