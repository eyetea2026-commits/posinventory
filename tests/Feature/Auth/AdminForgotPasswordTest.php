<?php

namespace Tests\Feature\Auth;

use App\Mail\OtpMail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
        // The masked value only ever shows 3 asterisks, keeping at most the
        // first 2 characters of the local part — never the real address.
        $response->assertSee('ad***@cctvexpress.local', false);
        // The visible/disabled display input must show only the masked
        // form, never the real address.
        $response->assertDontSee('value="administrator@cctvexpress.local" disabled', false);
        // The real address still travels via a hidden field so submitting
        // the form without touching anything works.
        $response->assertSee('type="hidden" id="email_hidden" name="email" value="administrator@cctvexpress.local"', false);
        $response->assertSee('Not you? Enter a different email');
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

        // Step 1: submit the forgot form — the masked field's hidden input
        // is what actually gets posted, so this simulates a click on
        // "Send OTP Code" without the admin typing anything.
        $forgotResponse = $this->post(route('admin.forgot.post'), ['email' => $admin->email]);
        $forgotResponse->assertRedirect(route('admin.otp.form'));
        Mail::assertSent(OtpMail::class);

        $otp = DB::table('password_reset_tokens')->where('email', $admin->email)->value('token');
        $this->assertNotNull($otp);

        // Step 2: verify the OTP.
        $verifyResponse = $this->post(route('admin.otp.verify'), ['email' => $admin->email, 'otp' => $otp]);
        $verifyResponse->assertRedirect(route('admin.password.reset.form', ['email' => $admin->email]));

        // Step 3: set the new password.
        $resetResponse = $this->post(route('admin.password.reset'), [
            'email' => $admin->email,
            'password' => 'NewPass1234',
            'password_confirmation' => 'NewPass1234',
        ]);
        $resetResponse->assertRedirect(route('welcome'));

        $admin->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPass1234', $admin->password));
    }

    public function test_reset_is_rejected_for_an_unrelated_email_without_its_own_otp_verification(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role_id' => $this->adminRole->id, 'email' => 'administrator@cctvexpress.local']);
        $otherAdmin = User::factory()->create(['role_id' => $this->adminRole->id, 'email' => 'other-admin@cctvexpress.local']);

        $this->post(route('admin.forgot.post'), ['email' => $admin->email]);
        $otp = DB::table('password_reset_tokens')->where('email', $admin->email)->value('token');
        $this->post(route('admin.otp.verify'), ['email' => $admin->email, 'otp' => $otp]);

        // Attempting to reset a DIFFERENT admin's password using the OTP
        // session verified for $admin must be rejected.
        $response = $this->post(route('admin.password.reset'), [
            'email' => $otherAdmin->email,
            'password' => 'NewPass1234',
            'password_confirmation' => 'NewPass1234',
        ]);

        $response->assertRedirect(route('admin.forgot'));
        $otherAdmin->refresh();
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('NewPass1234', $otherAdmin->password));
    }
}
