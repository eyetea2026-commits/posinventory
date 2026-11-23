<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\ActivityLog;
use App\Models\User;
use App\Notifications\PasswordResetRequested;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AdminAuthController extends Controller
{
    private const OTP_EXPIRY_MINUTES = 5;

    private const OTP_RESEND_COOLDOWN_SECONDS = 45;

    private const MAX_OTP_ATTEMPTS = 5;

    private const OTP_LOCKOUT_SECONDS = 60;

    public function logout(Request $request)
    {
        $user = Auth::user()?->fresh();
        $sessionId = $request->session()->getId();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user && $user->current_session_id === $sessionId) {
            $user->current_session_id = null;
            $user->save();
        }

        return redirect('/');
    }

    public function showForgot()
    {
        $eligibleAdmins = User::admins()->filter(fn ($user) => filled($user->email));
        $adminEmail = $eligibleAdmins->count() === 1 ? $eligibleAdmins->first()->email : null;

        return view('auth.admin.forgot', [
            'adminEmail' => $adminEmail,
            'maskedAdminEmail' => $adminEmail ? $this->maskEmail($adminEmail) : null,
        ]);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visibleLength = min(2, strlen($local));

        return substr($local, 0, $visibleLength).'***@'.$domain;
    }

    public function sendOtp(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->isAdmin()) {
            return back()->withErrors(['email' => 'No admin account found for that email address.']);
        }

        $existingToken = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        if ($existingToken) {
            $secondsSinceLastSend = abs(now()->diffInSeconds($existingToken->created_at));
            if ($secondsSinceLastSend < self::OTP_RESEND_COOLDOWN_SECONDS) {
                $wait = (int) ceil(self::OTP_RESEND_COOLDOWN_SECONDS - $secondsSinceLastSend);

                return redirect()->route('admin.otp.form')
                    ->with('email', $user->email)
                    ->withErrors(['otp' => "Please wait {$wait} more second".($wait === 1 ? '' : 's').' before requesting a new code.']);
            }
        }

        $otp = random_int(100000, 999999);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => (string) $otp, 'created_at' => now()]
        );
        RateLimiter::clear('admin-otp-verify:'.strtolower($user->email));

        try {
            Mail::to($user->email)->send(new OtpMail($otp, self::OTP_EXPIRY_MINUTES));
        } catch (Throwable $e) {
            Log::error('Failed to send admin OTP email', [
                'email' => $user->email,
                'exception' => $e->getMessage(),
            ]);

            return back()->withErrors(['email' => 'We could not send the OTP email right now. Please try again in a moment or contact support.']);
        }

        try {
            $user->notify(new PasswordResetRequested($user, now()));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch PasswordResetRequested notification', [
                'email' => $user->email,
                'exception' => $e->getMessage(),
            ]);
        }

        $request->session()->put('email', $user->email);

        return redirect()->route('admin.otp.form');
    }

    public function showOtpForm(Request $request)
    {
        $email = session('email') ?? $request->get('email');
        $token = $email ? DB::table('password_reset_tokens')->where('email', $email)->first() : null;

        return view('auth.admin.otp', [
            'email' => $email,
            'sentAt' => $token ? Carbon::parse($token->created_at)->toIso8601String() : null,
            'resendCooldownSeconds' => self::OTP_RESEND_COOLDOWN_SECONDS,
            'otpExpiryMinutes' => self::OTP_EXPIRY_MINUTES,
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'otp' => ['required']]);
        $throttleKey = 'admin-otp-verify:'.strtolower($data['email']);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_OTP_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            ActivityLog::record('auth.otp_locked', "OTP verification locked out for \"{$data['email']}\" after repeated failures, from {$request->ip()}");

            return back()->withErrors(['otp' => "Too many failed attempts. Please try again in {$seconds} second(s)."]);
        }

        $record = DB::table('password_reset_tokens')->where('email', $data['email'])->first();

        $isExpired = $record && abs(now()->diffInMinutes($record->created_at)) > self::OTP_EXPIRY_MINUTES;

        if (! $record || $isExpired || ! hash_equals((string) $record->token, (string) $data['otp'])) {
            RateLimiter::hit($throttleKey, self::OTP_LOCKOUT_SECONDS);
            ActivityLog::record('auth.otp_failed', "Failed OTP verification attempt for \"{$data['email']}\" from {$request->ip()}");

            return back()->withErrors(['otp' => 'Invalid or expired OTP code.']);
        }

        RateLimiter::clear($throttleKey);

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

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
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! session('otp_verified') || session('otp_verified_email') !== $data['email']) {
            return redirect()->route('admin.forgot')->withErrors(['email' => 'OTP verification required.']);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->isAdmin()) {
            return back()->withErrors(['email' => 'No admin account found.']);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        if ($user->current_session_id) {
            DB::table('sessions')->where('id', $user->current_session_id)->delete();
            $user->current_session_id = null;
            $user->save();
        }

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
        $request->session()->forget(['otp_verified', 'otp_verified_email', 'email']);

        ActivityLog::record('auth.password_reset', "Password reset via OTP for \"{$user->name}\" from {$request->ip()}", $user->id);

        return redirect()->route('welcome')->with('status', 'Password reset successful. You may login now.');
    }

    public function dashboard()
    {
        return view('admin.dashboard');
    }
}
