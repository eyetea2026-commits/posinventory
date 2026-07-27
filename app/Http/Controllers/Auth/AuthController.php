<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

// The single sign-in entry point for the whole system — one username/password
// form with no upfront "are you an Admin or a Cashier?" choice. The account's
// role (looked up only *after* the credentials check succeeds) decides where
// to land: Admin accounts go to /admin/dashboard, Cashier accounts go to
// /cashier/pos. This replaced two separate, near-identical login forms
// (AdminAuthController/CashierAuthController each had their own showLogin()
// and login()) plus a portal-picker landing page — the admin/cashier
// distinction is something the system determines from who you are, not
// something you tell it up front.
class AuthController extends Controller
{
    // Same per-account lockout convention as the rest of the auth system —
    // keyed by username only now, since there's one login form for every
    // account regardless of role.
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCKOUT_SECONDS = 60;

    public function showLogin()
    {
        return view('welcome');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required'],
            'password' => ['required'],
        ]);

        $throttleKey = 'login:' . strtolower($credentials['username']);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            ActivityLog::record('auth.login_locked', "Login locked out for \"{$credentials['username']}\" after repeated failures, from {$request->ip()}");

            return back()->withErrors(['username' => "Too many failed login attempts. Please try again in {$seconds} second(s)."]);
        }

        // Authenticate using username (name field) instead of email
        $loginCredentials = [
            'name' => $credentials['username'],
            'password' => $credentials['password'],
        ];

        if (Auth::attempt($loginCredentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            if (isset($user->is_active) && !$user->is_active) {
                Auth::logout();
                return back()->withErrors(['username' => 'Your account has been deactivated. Please contact the administrator.']);
            }

            RateLimiter::clear($throttleKey);

            if ($user->isAdmin()) {
                ActivityLog::record('auth.login', "\"{$user->name}\" logged in (Admin) from {$request->ip()}", $user->id);
                return redirect()->intended('/admin/dashboard');
            }

            if ($user->isCashier()) {
                ActivityLog::record('auth.login', "\"{$user->name}\" logged in (Cashier) from {$request->ip()}", $user->id);
                return redirect()->intended('/cashier/pos');
            }

            // No recognized role assigned — nothing to route to.
            Auth::logout();
            return back()->withErrors(['username' => 'This account has no assigned role. Please contact an administrator.']);
        }

        RateLimiter::hit($throttleKey, self::LOGIN_LOCKOUT_SECONDS);
        ActivityLog::record('auth.login_failed', "Failed login attempt for username \"{$credentials['username']}\" from {$request->ip()}");

        return back()->withErrors(['username' => 'The provided credentials do not match our records.']);
    }

    // Purely a UI hint (the "Administrator"/"Cashier" badge on the login
    // form) — this is a deliberate, informed tradeoff: it re-opens a small
    // username-enumeration surface that the rest of the auth hardening
    // otherwise closes, but the badge only needs a role name back, never
    // password/active-status/anything else, and the route is rate-limited
    // (throttle:20,1) to keep bulk enumeration slow rather than free.
    public function lookupRole(Request $request)
    {
        $username = trim((string) $request->query('username', ''));

        if ($username === '') {
            return response()->json(['role' => null]);
        }

        $user = User::where('name', $username)->first();

        $role = null;
        if ($user?->isAdmin()) {
            $role = 'admin';
        } elseif ($user?->isCashier()) {
            $role = 'cashier';
        }

        return response()->json(['role' => $role]);
    }
}
