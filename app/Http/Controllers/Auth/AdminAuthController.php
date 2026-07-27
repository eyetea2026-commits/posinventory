<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use App\Mail\OtpMail;
use App\Notifications\PasswordResetRequested;
use Throwable;

class AdminAuthController extends Controller
{
    private const OTP_EXPIRY_MINUTES = 5;
    private const OTP_RESEND_COOLDOWN_SECONDS = 45;

    // OTP lockout threshold (login itself is now handled by the unified
    // AuthController — this controller only owns the admin-only forgot
    // password / OTP reset flow, plus logout).
    private const MAX_OTP_ATTEMPTS = 5;
    private const OTP_LOCKOUT_SECONDS = 60;

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    public function showForgot()
    {
        return view('auth.admin.forgot');
    }

    public function sendOtp(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->isAdmin()) {
            return back()->withErrors(['email' => 'No admin account found for that email address.']);
        }

        // Resend cooldown: block re-sending too soon after the last code,
        // both so the admin isn't tempted to spam-click before the previous
        // email could even arrive, and so rapid resends don't burn through
        // Gmail's send rate limit or the route's per-minute throttle.
        $existingToken = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        if ($existingToken) {
            // Carbon 3's diff methods return a signed value by default (this
            // one comes back negative, since created_at is in the past) —
            // abs() is required or every comparison below is silently wrong.
            $secondsSinceLastSend = abs(now()->diffInSeconds($existingToken->created_at));
            if ($secondsSinceLastSend < self::OTP_RESEND_COOLDOWN_SECONDS) {
                $wait = (int) ceil(self::OTP_RESEND_COOLDOWN_SECONDS - $secondsSinceLastSend);
                return redirect()->route('admin.otp.form')
                    ->with('email', $user->email)
                    ->withErrors(['otp' => "Please wait {$wait} more second" . ($wait === 1 ? '' : 's') . " before requesting a new code."]);
            }
        }

        // random_int() is a CSPRNG — rand() is not, and an OTP feeding an
        // authentication decision should never rely on a predictable source.
        $otp = random_int(100000, 999999);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => (string) $otp, 'created_at' => now()]
        );
        RateLimiter::clear('admin-otp-verify:' . strtolower($user->email));

        // A broken mail transport (bad SMTP credentials, network issue) must
        // surface as a clear "try again" message rather than a raw 500 — the
        // OTP row above is already written, so a retry from the same form
        // will just resend the same code rather than losing the request.
        try {
            Mail::to($user->email)->send(new OtpMail($otp, self::OTP_EXPIRY_MINUTES));
        } catch (Throwable $e) {
            Log::error('Failed to send admin OTP email', [
                'email' => $user->email,
                'exception' => $e->getMessage(),
            ]);

            return back()->withErrors(['email' => 'We could not send the OTP email right now. Please try again in a moment or contact support.']);
        }

        // Mirrors this event into the Administrator Notification Center
        // (bell + database row) alongside the OTP email itself — a security
        // audit trail of "a reset was requested for your account", separate
        // from the OTP code delivery above. Never let this block the OTP
        // flow that already succeeded.
        try {
            $user->notify(new PasswordResetRequested($user, now()));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch PasswordResetRequested notification', [
                'email' => $user->email,
                'exception' => $e->getMessage(),
            ]);
        }

        return redirect()->route('admin.otp.form')->with('email', $user->email);
    }

    public function showOtpForm(Request $request)
    {
        $email = session('email') ?? $request->get('email');
        $token = $email ? DB::table('password_reset_tokens')->where('email', $email)->first() : null;

        return view('auth.admin.otp', [
            'email' => $email,
            // ISO 8601 so the client can compute an accurate countdown from
            // the actual send time, not just "45s from whenever this page
            // happened to load" — matters if the page is refreshed.
            'sentAt' => $token ? \Carbon\Carbon::parse($token->created_at)->toIso8601String() : null,
            'resendCooldownSeconds' => self::OTP_RESEND_COOLDOWN_SECONDS,
            'otpExpiryMinutes' => self::OTP_EXPIRY_MINUTES,
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate(['email' => ['required','email'], 'otp' => ['required']]);
        $throttleKey = 'admin-otp-verify:' . strtolower($data['email']);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_OTP_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            ActivityLog::record('auth.otp_locked', "OTP verification locked out for \"{$data['email']}\" after repeated failures, from {$request->ip()}");

            return back()->withErrors(['otp' => "Too many failed attempts. Please try again in {$seconds} second(s)."]);
        }

        $record = DB::table('password_reset_tokens')->where('email', $data['email'])->first();

        // abs() is required here too — see the matching note in sendOtp().
        $isExpired = $record && abs(now()->diffInMinutes($record->created_at)) > self::OTP_EXPIRY_MINUTES;

        if (! $record || $isExpired || ! hash_equals((string) $record->token, (string) $data['otp'])) {
            RateLimiter::hit($throttleKey, self::OTP_LOCKOUT_SECONDS);
            ActivityLog::record('auth.otp_failed', "Failed OTP verification attempt for \"{$data['email']}\" from {$request->ip()}");

            return back()->withErrors(['otp' => 'Invalid or expired OTP code.']);
        }

        RateLimiter::clear($throttleKey);

        // Single-use: delete the token the moment it's successfully verified,
        // rather than leaving it valid for repeated verification until
        // resetPassword() eventually consumes it.
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        // Deliberately NOT ->with(...) (flash data) — flash only survives one
        // request, but this needs to survive two: the GET that renders the
        // reset-password form, then the POST that actually submits it. Using
        // put() here keeps it in the session until resetPassword() clears it
        // explicitly on success (or the session itself expires).
        $request->session()->put('otp_verified', true);
        $request->session()->put('otp_verified_email', $data['email']);

        return redirect()->route('admin.password.reset.form', ['email' => $data['email']]);
    }

    public function showResetForm(Request $request)
    {
        $email = $request->get('email');
        $otpVerified = session('otp_verified');
        if (! $otpVerified) {
            return redirect()->route('admin.forgot')->withErrors(['email' => 'OTP verification required.']);
        }
        return view('auth.admin.reset', ['email' => $email]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required','confirmed', Password::defaults()],
        ]);

        // The password must only ever change after a successful OTP
        // verification for this exact email — session('otp_verified') alone
        // isn't enough, since without also pinning the email a verified OTP
        // for one admin account could be replayed against a different one
        // by editing the hidden email field on the reset form.
        if (! session('otp_verified') || session('otp_verified_email') !== $data['email']) {
            return redirect()->route('admin.forgot')->withErrors(['email' => 'OTP verification required.']);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->isAdmin()) {
            return back()->withErrors(['email' => 'No admin account found.']);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
        $request->session()->forget(['otp_verified', 'otp_verified_email']);

        ActivityLog::record('auth.password_reset', "Password reset via OTP for \"{$user->name}\" from {$request->ip()}", $user->id);

        return redirect()->route('welcome')->with('status', 'Password reset successful. You may login now.');
    }

    public function dashboard()
    {
        return view('admin.dashboard');
    }
}
