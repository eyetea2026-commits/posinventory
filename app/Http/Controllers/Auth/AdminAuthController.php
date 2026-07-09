<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Mail\OtpMail;

class AdminAuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required'],
            'password' => ['required'],
        ]);

        // Authenticate using username (name field) instead of email
        $loginCredentials = [
            'name' => $credentials['username'],
            'password' => $credentials['password']
        ];

        if (Auth::attempt($loginCredentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            // Check if user is active
            if (isset($user->is_active) && !$user->is_active) {
                Auth::logout();
                return back()->withErrors(['username' => 'Your account has been deactivated. Please contact the administrator.']);
            }

            if (! $user->isAdmin()) {
                Auth::logout();
                return back()->withErrors(['username' => 'Unauthorized for admin area.']);
            }

            ActivityLog::record('auth.login', "\"{$user->name}\" logged in (Admin)", $user->id);

            return redirect()->intended('/admin/dashboard');
        }

        return back()->withErrors(['username' => 'The provided credentials do not match our records.']);
    }

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

        $otp = rand(100000, 999999);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => (string) $otp, 'created_at' => now()]
        );

        Mail::to($user->email)->send(new OtpMail($otp));

        return redirect()->route('admin.otp.form')->with('email', $user->email);
    }

    public function showOtpForm(Request $request)
    {
        $email = session('email') ?? $request->get('email');
        return view('auth.admin.otp', ['email' => $email]);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate(['email' => ['required','email'], 'otp' => ['required']]);
        $record = DB::table('password_reset_tokens')->where('email', $data['email'])->first();

        $isExpired = $record && now()->diffInMinutes($record->created_at) > 10;

        if (! $record || $isExpired || ! hash_equals((string) $record->token, (string) $data['otp'])) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP code.']);
        }

        return redirect()->route('admin.password.reset.form', ['email' => $data['email']])->with('otp_verified', true);
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
            'password' => ['required','confirmed','min:8'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->isAdmin()) {
            return back()->withErrors(['email' => 'No admin account found.']);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        return redirect()->route('login')->with('status', 'Password reset successful. You may login now.');
    }

    public function dashboard()
    {
        return view('admin.dashboard');
    }
}
