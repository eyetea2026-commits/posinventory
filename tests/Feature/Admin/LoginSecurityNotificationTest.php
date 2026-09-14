<?php

namespace Tests\Feature\Admin;

use App\Models\LoginSecurityEvent;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AdminLoginSecurityAlert;
use App\Notifications\UnauthorizedLoginReported;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

// "Was this you?" security check on Admin login: a structured, updatable
// LoginSecurityEvent row per login (Pending -> Confirmed/Not Recognized),
// a self-addressed notification, and a broadcast to every admin only if
// the login is flagged as not recognized.
class LoginSecurityNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        // The affected-session termination path touches the real `sessions`
        // table -- phpunit.xml defaults to the in-memory "array" driver,
        // which never writes there at all (same reasoning as SingleSessionTest).
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

    public function test_admin_login_creates_exactly_one_pending_event_and_notifies_that_admin(): void
    {
        Notification::fake();

        $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password'])
            ->assertRedirect('/admin/dashboard');

        $this->assertDatabaseCount('LoginSecurityEvent', 1);
        $event = LoginSecurityEvent::first();
        $this->assertSame($this->admin->id, $event->UserID);
        $this->assertTrue($event->isPending());
        $this->assertNotNull($event->SessionID);

        Notification::assertSentTo($this->admin, AdminLoginSecurityAlert::class);
        Notification::assertNotSentTo($this->cashier, AdminLoginSecurityAlert::class);
    }

    public function test_cashier_login_creates_no_security_event_and_no_notification(): void
    {
        Notification::fake();

        $this->post(route('login.post'), ['username' => 'cashier1', 'password' => 'password'])
            ->assertRedirect('/cashier/pos');

        $this->assertDatabaseCount('LoginSecurityEvent', 0);
        Notification::assertNothingSent();
    }

    public function test_revisiting_the_dashboard_does_not_create_another_event(): void
    {
        $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);
        $this->assertDatabaseCount('LoginSecurityEvent', 1);

        // Plain navigation/refresh after login -- only login() itself ever
        // creates a LoginSecurityEvent, so revisiting any admin page (even
        // repeatedly) must never add another row.
        $this->get(route('admin.dashboard'));
        $this->get(route('admin.dashboard'));

        $this->assertDatabaseCount('LoginSecurityEvent', 1);
    }

    public function test_a_second_real_login_creates_a_second_event(): void
    {
        $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);
        $sessionCookieName = config('session.cookie');
        $sessionId = $this->admin->fresh()->current_session_id;

        $this->withCookie($sessionCookieName, $sessionId)->post(route('admin.logout'));

        $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);

        $this->assertDatabaseCount('LoginSecurityEvent', 2);
    }

    public function test_confirm_via_signed_url_marks_confirmed_and_logs_activity(): void
    {
        $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);
        $event = LoginSecurityEvent::first();

        // Resets this test method's shared SessionGuard instance so the
        // signed-URL request below is genuinely anonymous rather than
        // riding on the earlier login's cached-in-memory auth state (a
        // PHPUnit shared-process artifact — the guard caches its resolved
        // user for the lifetime of the test method, not just one request).
        Auth::logout();

        $url = URL::signedRoute('admin.security.confirm', ['loginSecurityEvent' => $event->LoginSecurityEventID]);
        $response = $this->post($url, [], ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJson(['success' => true, 'status' => 'confirmed']);
        $this->assertSame(LoginSecurityEvent::STATUS_CONFIRMED, $event->fresh()->ConfirmationStatus);
        $this->assertNotNull($event->fresh()->ConfirmedAt);
        $this->assertDatabaseHas('ActivityLog', ['Action' => 'security.login_confirmed']);
    }

    public function test_deny_via_signed_url_flags_event_ends_session_and_notifies_all_admins(): void
    {
        Notification::fake();

        $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);
        $event = LoginSecurityEvent::first();
        $sessionId = $event->SessionID;
        $this->assertDatabaseHas('sessions', ['id' => $sessionId]);

        $secondAdmin = User::factory()->create(['name' => 'admin2', 'email' => 'admin2@example.com', 'role_id' => $this->admin->role_id]);

        Auth::logout(); // see test_confirm_via_signed_url_... for why

        $url = URL::signedRoute('admin.security.deny', ['loginSecurityEvent' => $event->LoginSecurityEventID]);
        $response = $this->post($url, [], ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJson(['success' => true, 'status' => 'not_recognized']);
        $this->assertSame(LoginSecurityEvent::STATUS_NOT_RECOGNIZED, $event->fresh()->ConfirmationStatus);
        $this->assertDatabaseHas('ActivityLog', ['Action' => 'security.login_not_recognized']);

        // The affected session is gone, and the one-session-per-account
        // slot is released so a legitimate re-login isn't blocked.
        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
        $this->assertNull($this->admin->fresh()->current_session_id);

        Notification::assertSentTo($this->admin, UnauthorizedLoginReported::class);
        Notification::assertSentTo($secondAdmin, UnauthorizedLoginReported::class);
    }

    public function test_deny_does_not_release_a_newer_legitimate_session_slot(): void
    {
        // First login (the one about to be denied)...
        $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);
        $firstEvent = LoginSecurityEvent::first();

        // ...properly logs out...
        $sessionCookieName = config('session.cookie');
        $this->withCookie($sessionCookieName, $this->admin->fresh()->current_session_id)->post(route('admin.logout'));

        // ...and a genuinely new login happens before the first one is denied.
        $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);
        $newSessionId = $this->admin->fresh()->current_session_id;
        $this->assertNotSame($firstEvent->SessionID, $newSessionId);

        Auth::logout(); // see test_confirm_via_signed_url_... for why

        $url = URL::signedRoute('admin.security.deny', ['loginSecurityEvent' => $firstEvent->LoginSecurityEventID]);
        $this->post($url, [], ['Accept' => 'application/json']);

        // The newer session's slot must survive denying the OLD login.
        $this->assertSame($newSessionId, $this->admin->fresh()->current_session_id);
        $this->assertDatabaseHas('sessions', ['id' => $newSessionId]);
    }

    public function test_confirming_an_already_resolved_event_is_a_safe_no_op(): void
    {
        $this->post(route('login.post'), ['username' => 'admin', 'password' => 'password']);
        $event = LoginSecurityEvent::first();
        Auth::logout(); // see test_confirm_via_signed_url_... for why

        $confirmUrl = URL::signedRoute('admin.security.confirm', ['loginSecurityEvent' => $event->LoginSecurityEventID]);
        $this->post($confirmUrl, [], ['Accept' => 'application/json']);
        $firstConfirmedAt = $event->fresh()->ConfirmedAt;

        $denyUrl = URL::signedRoute('admin.security.deny', ['loginSecurityEvent' => $event->LoginSecurityEventID]);
        $this->post($denyUrl, [], ['Accept' => 'application/json']);

        // Already resolved as "confirmed" — a later deny attempt on the same
        // (already-handled) event must not flip it or fire a second time.
        $this->assertSame(LoginSecurityEvent::STATUS_CONFIRMED, $event->fresh()->ConfirmationStatus);
        $this->assertEquals($firstConfirmedAt, $event->fresh()->ConfirmedAt);
    }

    public function test_unsigned_unauthenticated_request_is_rejected(): void
    {
        // Built directly (not via a real login POST): Auth::attempt() inside
        // AuthController::login() would leave this test method's shared
        // SessionGuard instance holding an authenticated user for the rest
        // of this method regardless of cookies (a known PHPUnit
        // shared-process quirk — the guard caches its resolved user for the
        // lifetime of the test, not just the request) — which would
        // silently defeat the very thing this test is checking.
        $event = LoginSecurityEvent::create([
            'UserID' => $this->admin->id,
            'IPAddress' => '10.0.0.1',
            'SessionID' => 'irrelevant-session-id',
            'LoginAt' => now(),
        ]);

        $this->post(route('admin.security.confirm', ['loginSecurityEvent' => $event->LoginSecurityEventID]))
            ->assertForbidden();
        $this->post(route('admin.security.deny', ['loginSecurityEvent' => $event->LoginSecurityEventID]))
            ->assertForbidden();
        $this->get(route('admin.security.review', ['loginSecurityEvent' => $event->LoginSecurityEventID]))
            ->assertForbidden();

        $this->assertSame(LoginSecurityEvent::STATUS_PENDING, $event->fresh()->ConfirmationStatus);
    }

    public function test_authenticated_admin_without_a_signature_is_allowed(): void
    {
        $event = LoginSecurityEvent::create([
            'UserID' => $this->admin->id,
            'IPAddress' => '10.0.0.1',
            'SessionID' => 'irrelevant-session-id',
            'LoginAt' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.security.confirm', ['loginSecurityEvent' => $event->LoginSecurityEventID]), [], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertSame(LoginSecurityEvent::STATUS_CONFIRMED, $event->fresh()->ConfirmationStatus);
    }

    public function test_admin_with_no_registered_email_still_gets_the_in_app_notification_only(): void
    {
        Notification::fake();

        $adminRole = $this->admin->role_id;
        $noEmailAdmin = User::factory()->create(['name' => 'admin_noemail', 'email' => null, 'role_id' => $adminRole]);

        $this->post(route('login.post'), ['username' => 'admin_noemail', 'password' => 'password']);

        $this->assertDatabaseCount('LoginSecurityEvent', 1);
        Notification::assertSentTo($noEmailAdmin, AdminLoginSecurityAlert::class, function ($notification, $channels) {
            return $channels === ['database'];
        });
    }
}
