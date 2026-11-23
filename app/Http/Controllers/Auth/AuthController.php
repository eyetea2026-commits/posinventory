<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LoginSecurityEvent;
use App\Models\User;
use App\Notifications\AdminLoginSecurityAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class AuthController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    private const LOGIN_LOCKOUT_SECONDS = 60;

    public function showLogin()
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->isAdmin()) {
                return redirect('/admin/dashboard')
                    ->withHeaders(['Cache-Control' => 'no-store, no-cache, must-revalidate, private']);
            }

            if ($user->isCashier()) {
                return redirect('/cashier/pos')
                    ->withHeaders(['Cache-Control' => 'no-store, no-cache, must-revalidate, private']);
            }
        }

        return response()
            ->view('welcome')
            ->withHeaders(['Cache-Control' => 'no-store, no-cache, must-revalidate, private']);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required'],
            'password' => ['required'],
        ]);

        $throttleKey = 'login:'.strtolower($credentials['username']);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            ActivityLog::record('auth.login_locked', "Login locked out for \"{$credentials['username']}\" after repeated failures, from {$request->ip()}");

            return back()
                ->withErrors(['username' => "Too many failed login attempts. Please try again in {$seconds} second(s)."])
                ->with('show_forgot_password', $this->isAdminUsername($credentials['username']));
        }

        $loginCredentials = [
            'name' => $credentials['username'],
            'password' => $credentials['password'],
        ];

        if (Auth::attempt($loginCredentials, false)) {
            $request->session()->regenerate();
            $user = Auth::user();

            if (isset($user->is_active) && ! $user->is_active) {
                Auth::logout();

                return back()->withErrors(['username' => 'Your account has been deactivated. Please contact the administrator.']);
            }

            RateLimiter::clear($throttleKey);

            $request->session()->save();
            $newSessionId = $request->session()->getId();

            $sessionDenied = DB::transaction(function () use ($user, $newSessionId) {
                $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();

                if ($lockedUser->current_session_id
                    && $lockedUser->current_session_id !== $newSessionId
                    && $lockedUser->hasActiveSession()) {
                    return true;
                }

                $lockedUser->current_session_id = $newSessionId;
                $lockedUser->save();
                User::markSessionAlive($newSessionId);

                return false;
            });

            if ($sessionDenied) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                ActivityLog::record('auth.login_denied_active_session', "Login denied for \"{$user->name}\" — account already has an active session, attempt from {$request->ip()}");

                return back()->withErrors(['username' => 'This account is already logged in on another device or browser.']);
            }

            if ($user->isAdmin()) {
                ActivityLog::record('auth.login', "\"{$user->name}\" logged in (Admin) from {$request->ip()}", $user->id);
                $this->recordLoginSecurityEvent($request, $user, $newSessionId);

                return redirect()->intended('/admin/dashboard');
            }

            if ($user->isCashier()) {
                ActivityLog::record('auth.login', "\"{$user->name}\" logged in (Cashier) from {$request->ip()}", $user->id);

                return redirect()->intended('/cashier/pos');
            }

            Auth::logout();

            return back()->withErrors(['username' => 'This account has no assigned role. Please contact an administrator.']);
        }

        RateLimiter::hit($throttleKey, self::LOGIN_LOCKOUT_SECONDS);
        ActivityLog::record('auth.login_failed', "Failed login attempt for username \"{$credentials['username']}\" from {$request->ip()}");

        return back()
            ->withErrors(['username' => 'The provided credentials do not match our records.'])
            ->with('show_forgot_password', $this->isAdminUsername($credentials['username']));
    }

    private function isAdminUsername(string $username): bool
    {
        return (bool) User::where('name', $username)->first()?->isAdmin();
    }

    private function recordLoginSecurityEvent(Request $request, User $user, string $sessionId): void
    {
        try {
            $event = LoginSecurityEvent::create([
                'UserID' => $user->id,
                'IPAddress' => $request->ip(),
                'UserAgent' => $request->userAgent(),
                'DeviceSummary' => LoginSecurityEvent::summarizeUserAgent($request->userAgent()),
                'SessionID' => $sessionId,
                'LoginAt' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to record LoginSecurityEvent', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);

            return;
        }

        try {
            $user->notify(new AdminLoginSecurityAlert($event));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch AdminLoginSecurityAlert notification', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
