<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// One account = one active authenticated session. A second login attempt for
// the SAME account while its session is still fresh must be denied
// server-side (no reliance on JS/cookies/IP/user-agent); a different account
// is never affected; logout or natural session expiry releases the slot.
class SingleSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        // The feature under test relies on the real `sessions` DB table
        // (SESSION_DRIVER=database in production) -- phpunit.xml defaults
        // tests to the in-memory "array" driver, which never touches that
        // table at all, so this class opts back into the real one.
        config(['session.driver' => 'database']);

        $adminRole = Role::create(['role_name' => 'admin']);
        $cashierRole = Role::create(['role_name' => 'cashier']);

        $this->admin = User::factory()->create([
            'name' => 'admin', 'email' => 'admin@example.com', 'role_id' => $adminRole->id,
        ]);
        $this->cashier = User::factory()->create([
            'name' => 'cashier1', 'email' => 'cashier1@example.com', 'role_id' => $cashierRole->id,
        ]);
    }

    // Every $this->post(route('login.post'), ...) call below is a fresh,
    // cookie-less request — exactly what a brand new browser/incognito
    // window looks like to the server, with no session of its own yet.

    public function test_first_login_succeeds_and_registers_the_session(): void
    {
        $response = $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($this->admin);
        $this->assertNotNull($this->admin->fresh()->current_session_id);
    }

    public function test_second_login_for_the_same_account_is_denied_while_the_first_is_active(): void
    {
        // Browser 1 (normal browser).
        $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);
        $registeredSessionId = $this->admin->fresh()->current_session_id;
        $this->assertNotNull($registeredSessionId);

        // Browser 2 (incognito) — same credentials, no session cookie.
        $response = $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);

        $response->assertSessionHasErrors('username');
        $this->assertStringContainsString(
            'already logged in on another device or browser',
            collect(session('errors')->get('username'))->implode(' ')
        );
        $this->assertGuest();

        // The first session must remain exactly as it was — untouched by
        // the denied second attempt.
        $this->assertSame($registeredSessionId, $this->admin->fresh()->current_session_id);
    }

    public function test_second_login_denial_does_not_create_an_authenticated_session_for_the_new_browser(): void
    {
        $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);
        $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);

        // A fresh request (no cookie from either prior call) must still be
        // rejected by the ordinary auth middleware — the denied login never
        // established any authenticated state to exploit.
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect(route('welcome'));
    }

    public function test_a_different_account_can_log_in_while_admin_has_an_active_session(): void
    {
        $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);

        $response = $this->post(route('login.post'), ['username' => 'cashier1', 'password' => 'password']);

        $response->assertRedirect('/cashier/pos');
        $this->assertAuthenticatedAs($this->cashier);
    }

    public function test_logout_releases_the_session_slot_so_another_browser_can_log_in(): void
    {
        $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);
        $sessionId = $this->admin->fresh()->current_session_id;
        $this->assertNotNull($sessionId);

        // Presenting the exact registered session id as this browser's own
        // cookie (Laravel's test client encrypts it the same way a real
        // Set-Cookie round-trip would) makes the logout call genuinely "the
        // same browser" logging out — not a fresh, unrelated request.
        $this->withCookie(config('session.cookie'), $sessionId)
            ->post(route('admin.logout'))
            ->assertRedirect('/');

        $this->assertNull($this->admin->fresh()->current_session_id);

        // Incognito can now log in.
        $response = $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);
        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_a_stale_session_past_the_configured_lifetime_allows_a_new_login(): void
    {
        $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);
        $sessionId = $this->admin->fresh()->current_session_id;

        // Simulate the existing session naturally expiring, per the app's
        // own SESSION_LIFETIME — no manual database "unlock" of the account.
        DB::table('sessions')->where('id', $sessionId)->update([
            'last_activity' => now()->subMinutes(config('session.lifetime') + 5)->timestamp,
        ]);

        $response = $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_rapid_back_to_back_login_attempts_only_let_one_succeed(): void
    {
        // The closest sequential proxy for two browsers racing to log in at
        // nearly the same instant: neither attempt has any head start, and
        // the server-side transaction + row lock (not a plain
        // if-active-then-deny read) is what guarantees only one can win —
        // see PurchaseOrderController-style lockForUpdate usage in
        // AuthController::login().
        $responseA = $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);
        $responseB = $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);

        $reachedDashboard = fn ($r) => $r->status() === 302 && $r->headers->get('Location') === url('/admin/dashboard');

        // Exactly one of the two reaches the dashboard — never both, never
        // neither.
        $this->assertNotSame($reachedDashboard($responseA), $reachedDashboard($responseB));
    }

    // ---- Regression: normal login behavior must be unaffected ----

    public function test_wrong_password_is_still_denied_when_no_active_session_exists(): void
    {
        $response = $this->post(route('login.post'), ['username' => 'admin', 'password' => 'wrong-password']);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->assertNull($this->admin->fresh()->current_session_id);
    }

    public function test_deactivated_user_is_still_denied_and_never_registers_a_session(): void
    {
        $this->admin->update(['is_active' => false]);

        $response = $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->assertNull($this->admin->fresh()->current_session_id);
    }

    public function test_cashier_login_is_unaffected_by_the_single_session_rule_on_first_login(): void
    {
        $response = $this->post(route('login.post'), ['username' => 'cashier1', 'password' => 'password']);

        $response->assertRedirect('/cashier/pos');
        $this->assertAuthenticatedAs($this->cashier);
    }

    public function test_two_different_cashier_accounts_can_both_be_logged_in_at_once(): void
    {
        $cashierRole = Role::where('role_name', 'cashier')->first();
        $secondCashier = User::factory()->create([
            'name' => 'cashier2', 'email' => 'cashier2@example.com', 'role_id' => $cashierRole->id,
        ]);

        $this->post(route('login.post'), ['username' => 'cashier1', 'password' => 'password']);
        $response = $this->post(route('login.post'), ['username' => 'cashier2', 'password' => 'password']);

        $response->assertRedirect('/cashier/pos');
        $this->assertAuthenticatedAs($secondCashier);
    }
}
