<?php

namespace Tests\Feature\Auth;

use App\Mail\OtpMail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AdminForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['role_name' => 'admin']);
        Role::create(['role_name' => 'cashier']);
    }

    public function test_forgot_page_masks_the_email_when_exactly_one_admin_has_one(): void
    {
        User::factory()->create(['role_id' => $this->adminRole->id, 'email' => 'administrator@cctvexpress.local']);

        $response = $this->get(route('admin.forgot'));

        $response->assertOk();
        $response->assertSee('ad***@cctvexpress.local', false);
        $response->assertDontSee('value="administrator@cctvexpress.local" disabled', false);
        $response->assertSee('type="hidden" id="email_hidden" name="email" value="administrator@cctvexpress.local"', false);
        $response->assertSee('Not you? Enter a different email');
    }

    public function test_password_reset_screens_use_the_system_colors(): void
    {
        $this->get(route('admin.forgot'))->assertOk()
            ->assertSee('Administrator/Login.css?v=2', false);

        view()->share('errors', new ViewErrorBag);
        $otp = view('auth.admin.otp', ['email' => 'admin@cctvexpress.local', 'otpExpiryMinutes' => 5, 'sentAt' => now()->toIso8601String(), 'resendCooldownSeconds' => 45])->render();
        $reset = view('auth.admin.reset')->render();
        foreach ([$otp, $reset] as $html) {
            $this->assertStringContainsString('Administrator/Login.css?v=2', $html);
        }
        $this->assertStringContainsString('class="otp-resend"', $otp);
        $this->assertStringNotContainsString('style="min-height: 44px;', $otp);

        $css = file_get_contents(public_path('Administrator/Login.css'));
        $this->assertStringContainsString('background: #3b82f6;', $css);
        $this->assertStringContainsString('background: #1a1d2d;', $css);
        $this->assertStringContainsString('background: #2d3748;', $css);
        $this->assertStringNotContainsString('linear-gradient(135deg, #3b82f6, #10b981)', $css);
    }

    public function test_forgot_page_falls_back_to_manual_entry_with_multiple_admins(): void
    {
        User::factory()->create(['role_id' => $this->adminRole->id, 'email' => 'admin-one@cctvexpress.local']);
        User::factory()->create(['role_id' => $this->adminRole->id, 'email' => 'admin-two@cctvexpress.local']);

        $response = $this->get(route('admin.forgot'));

        $response->assertOk();
        $response->assertDontSee('Not you? Enter a different email');
        $response->assertDontSee('admin-one@cctvexpress.local', false);
        $response->assertDontSee('admin-two@cctvexpress.local', false);
    }

    public function test_forgot_page_checks_the_email_while_typing(): void
    {
        $this->get(route('admin.forgot'))
            ->assertOk()
            ->assertSee('window.attachEmailCheck = function', false)
            ->assertSee("window.attachEmailCheck(document.getElementById('email'));", false)
            ->assertSee("window.attachEmailCheck(document.getElementById('email_manual'));", false)
            ->assertSee('Please enter a valid email address.');
    }

    public function test_forgot_page_falls_back_to_manual_entry_when_the_admin_has_no_email(): void
    {
        User::factory()->create(['role_id' => $this->adminRole->id, 'email' => null]);

        $response = $this->get(route('admin.forgot'));

        $response->assertOk();
        $response->assertDontSee('Not you? Enter a different email');
    }

    public function test_full_reset_flow_via_the_masked_email_completes_successfully(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'email' => 'administrator@cctvexpress.local',
        ]);

        $forgotResponse = $this->post(route('admin.forgot.post'), ['email' => $admin->email]);
        $forgotResponse->assertRedirect(route('admin.otp.form'));
        Mail::assertSent(OtpMail::class);

        $otp = DB::table('password_reset_tokens')->where('email', $admin->email)->value('token');
        $this->assertNotNull($otp);

        $verifyResponse = $this->post(route('admin.otp.verify'), ['email' => $admin->email, 'otp' => $otp]);
        $verifyResponse->assertRedirect(route('admin.password.reset.form', ['email' => $admin->email]));

        $resetResponse = $this->post(route('admin.password.reset'), [
            'email' => $admin->email,
            'password' => 'NewPass1234!',
            'password_confirmation' => 'NewPass1234!',
        ]);
        $resetResponse->assertRedirect(route('welcome'));

        $admin->refresh();
        $this->assertTrue(Hash::check('NewPass1234!', $admin->password));
    }

    public function test_reset_is_rejected_for_an_unrelated_email_without_its_own_otp_verification(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role_id' => $this->adminRole->id, 'email' => 'administrator@cctvexpress.local']);
        $otherAdmin = User::factory()->create(['role_id' => $this->adminRole->id, 'email' => 'other-admin@cctvexpress.local']);

        $this->post(route('admin.forgot.post'), ['email' => $admin->email]);
        $otp = DB::table('password_reset_tokens')->where('email', $admin->email)->value('token');
        $this->post(route('admin.otp.verify'), ['email' => $admin->email, 'otp' => $otp]);

        $response = $this->post(route('admin.password.reset'), [
            'email' => $otherAdmin->email,
            'password' => 'NewPass1234!',
            'password_confirmation' => 'NewPass1234!',
        ]);

        $response->assertRedirect(route('admin.forgot'));
        $otherAdmin->refresh();
        $this->assertFalse(Hash::check('NewPass1234!', $otherAdmin->password));
    }

    public function test_completing_a_reset_invalidates_the_accounts_active_session(): void
    {
        config(['session.driver' => 'database']);
        Mail::fake();

        $admin = User::factory()->create([
            'name' => 'administrator', 'role_id' => $this->adminRole->id,
            'email' => 'administrator@cctvexpress.local',
        ]);

        $this->post(route('login.post'), ['username' => 'administrator', 'password' => 'password']);
        $admin->refresh();
        $sessionId = $admin->current_session_id;
        $this->assertNotNull($sessionId);
        $this->assertDatabaseHas('sessions', ['id' => $sessionId]);

        $this->post(route('admin.forgot.post'), ['email' => $admin->email]);
        $otp = DB::table('password_reset_tokens')->where('email', $admin->email)->value('token');
        $this->post(route('admin.otp.verify'), ['email' => $admin->email, 'otp' => $otp]);
        $resetResponse = $this->post(route('admin.password.reset'), [
            'email' => $admin->email,
            'password' => 'NewPass1234!',
            'password_confirmation' => 'NewPass1234!',
        ]);
        $resetResponse->assertRedirect(route('welcome'));

        $admin->refresh();
        $this->assertNull($admin->current_session_id);
        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
    }

    public function test_completing_a_reset_with_no_prior_active_session_does_not_error(): void
    {
        config(['session.driver' => 'database']);
        Mail::fake();

        $admin = User::factory()->create(['role_id' => $this->adminRole->id, 'email' => 'administrator@cctvexpress.local']);
        $this->assertNull($admin->current_session_id);

        $this->post(route('admin.forgot.post'), ['email' => $admin->email]);
        $otp = DB::table('password_reset_tokens')->where('email', $admin->email)->value('token');
        $this->post(route('admin.otp.verify'), ['email' => $admin->email, 'otp' => $otp]);
        $resetResponse = $this->post(route('admin.password.reset'), [
            'email' => $admin->email,
            'password' => 'NewPass1234!',
            'password_confirmation' => 'NewPass1234!',
        ]);

        $resetResponse->assertRedirect(route('welcome'));
        $this->assertTrue(Hash::check('NewPass1234!', $admin->fresh()->password));
    }

    public function test_a_cashier_cannot_reset_their_password(): void
    {
        Mail::fake();

        $cashier = User::factory()->create([
            'role_id' => Role::where('role_name', 'cashier')->value('id'),
            'email' => 'cashier@cctvexpress.local',
        ]);
        $originalHash = $cashier->password;

        $this->from(route('admin.forgot'))
            ->post(route('admin.forgot.post'), ['email' => $cashier->email])
            ->assertRedirect(route('admin.forgot'))
            ->assertSessionHasErrors('email');
        Mail::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $cashier->email]);

        $this->withSession(['otp_verified' => true, 'otp_verified_email' => $cashier->email])
            ->from(route('admin.password.reset.form'))
            ->post(route('admin.password.reset'), [
                'email' => $cashier->email,
                'password' => 'NewPass1234!',
                'password_confirmation' => 'NewPass1234!',
            ])
            ->assertSessionHasErrors('email');
        $this->assertSame($originalHash, $cashier->fresh()->password);
    }
}
