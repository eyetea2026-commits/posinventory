<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Pressing Back after logging out used to show a stored copy of the
// signed-in page. Pages must never be cached, and every page carries the
// session guard that re-checks sign-in state when it's shown again.
class SessionFreshnessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);
    }

    private function assertNotCacheable($response): void
    {
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
    }

    // Closing the whole browser signs the user out.
    public function test_the_login_cookie_is_deleted_when_the_browser_closes(): void
    {
        $this->assertTrue(config('session.expire_on_close'));
        $this->cashier->update(['name' => 'closecheck']);
        $tokenBefore = $this->cashier->fresh()->remember_token;

        $response = $this->post(route('login.post'), ['username' => 'closecheck', 'password' => 'password', 'remember' => 'on']);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($this->cashier);
        $cookies = collect($response->headers->getCookies());
        $session = $cookies->first(fn ($c) => $c->getName() === config('session.cookie'));
        $this->assertNotNull($session);
        // Expiry 0 = a browser-session cookie, removed when the browser closes.
        $this->assertSame(0, $session->getExpiresTime());
        // No "remember me" cookie that would sign them back in, even if the
        // old checkbox value is still sent.
        $this->assertNull($cookies->first(fn ($c) => str_starts_with($c->getName(), 'remember_')));
        // A remembered login would have written a new remember token.
        $this->assertSame($tokenBefore, $this->cashier->fresh()->remember_token);
    }

    public function test_the_login_page_no_longer_offers_remember_me(): void
    {
        $this->get(route('welcome'))->assertOk()->assertDontSee('name="remember"', false)->assertDontSee('Remember Me');
    }

    public function test_signed_in_pages_are_never_cached_by_the_browser(): void
    {
        $this->assertNotCacheable($this->actingAs($this->cashier)->get(route('cashier.pos')));
        $this->assertNotCacheable($this->actingAs($this->cashier)->get(route('cashier.transactions')));
        $this->assertNotCacheable($this->actingAs($this->admin)->get(route('admin.products.index')));
    }

    public function test_the_login_page_is_never_cached_either(): void
    {
        $this->assertNotCacheable($this->get(route('welcome')));
    }

    public function test_after_logout_revisiting_the_pos_goes_to_the_login_page(): void
    {
        $this->actingAs($this->cashier)->get(route('cashier.pos'))->assertOk();

        $this->actingAs($this->cashier)->post(route('cashier.logout'))->assertRedirect('/');

        $this->get(route('cashier.pos'))->assertRedirect(route('welcome'));
    }

    public function test_after_admin_logout_revisiting_an_admin_page_goes_to_the_login_page(): void
    {
        $this->actingAs($this->admin)->get(route('admin.products.index'))->assertOk();

        $this->post(route('admin.logout'));

        $this->get(route('admin.products.index'))->assertRedirect(route('welcome'));
    }

    public function test_session_status_reports_guest_and_signed_in_user(): void
    {
        $this->getJson(route('session.status'))
            ->assertOk()
            ->assertExactJson(['authenticated' => false, 'user_id' => null]);

        $this->actingAs($this->cashier)->getJson(route('session.status'))
            ->assertOk()
            ->assertExactJson(['authenticated' => true, 'user_id' => $this->cashier->id]);
    }

    public function test_every_page_carries_the_session_guard_with_the_right_user(): void
    {
        $this->actingAs($this->cashier)->get(route('cashier.pos'))
            ->assertSee('const expectedUserId = '.$this->cashier->id.';', false)
            ->assertSee("new BroadcastChannel('cctv-session')", false);

        $this->actingAs($this->admin)->get(route('admin.products.index'))
            ->assertSee('const expectedUserId = '.$this->admin->id.';', false);

        // Login page: guard present, expecting nobody signed in.
        $this->post(route('admin.logout'));
        $this->get(route('welcome'))->assertSee('const expectedUserId = null;', false);
    }
}
