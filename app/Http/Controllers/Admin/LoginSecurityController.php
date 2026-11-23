<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LoginSecurityEvent;
use App\Models\User;
use App\Notifications\UnauthorizedLoginReported;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Throwable;

class LoginSecurityController extends Controller
{
    private function authorizeAccess(Request $request, LoginSecurityEvent $loginSecurityEvent): void
    {
        if ($request->hasValidSignature()) {
            return;
        }

        if (auth()->check() && auth()->user()->isAdmin() && auth()->id() === $loginSecurityEvent->UserID) {
            return;
        }

        abort(403);
    }

    public function review(Request $request, LoginSecurityEvent $loginSecurityEvent)
    {
        $this->authorizeAccess($request, $loginSecurityEvent);

        $loginSecurityEvent->loadMissing('user');

        return view('admin.security.review', [
            'event' => $loginSecurityEvent,
            'intent' => $request->query('intent', 'confirm'),
            'confirmUrl' => URL::signedRoute('admin.security.confirm', ['loginSecurityEvent' => $loginSecurityEvent->LoginSecurityEventID]),
            'denyUrl' => URL::signedRoute('admin.security.deny', ['loginSecurityEvent' => $loginSecurityEvent->LoginSecurityEventID]),
        ]);
    }

    public function confirm(Request $request, LoginSecurityEvent $loginSecurityEvent)
    {
        $this->authorizeAccess($request, $loginSecurityEvent);

        if ($loginSecurityEvent->isPending()) {
            $loginSecurityEvent->update([
                'ConfirmationStatus' => LoginSecurityEvent::STATUS_CONFIRMED,
                'ConfirmedAt' => now(),
            ]);

            ActivityLog::record(
                'security.login_confirmed',
                "\"{$loginSecurityEvent->user?->name}\" confirmed a login from {$loginSecurityEvent->LoginAt->format('m/d/Y g:i A')} ({$loginSecurityEvent->IPAddress}) as recognized.",
                $loginSecurityEvent->UserID
            );
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'status' => 'confirmed']);
        }

        return view('admin.security.thanks', ['status' => 'confirmed']);
    }

    public function deny(Request $request, LoginSecurityEvent $loginSecurityEvent)
    {
        $this->authorizeAccess($request, $loginSecurityEvent);

        if ($loginSecurityEvent->isPending()) {
            $loginSecurityEvent->update([
                'ConfirmationStatus' => LoginSecurityEvent::STATUS_NOT_RECOGNIZED,
                'ConfirmedAt' => now(),
            ]);

            ActivityLog::record(
                'security.login_not_recognized',
                "\"{$loginSecurityEvent->user?->name}\" reported a login from {$loginSecurityEvent->LoginAt->format('m/d/Y g:i A')} ({$loginSecurityEvent->IPAddress}) as NOT recognized.",
                $loginSecurityEvent->UserID
            );

            $this->terminateAffectedSession($loginSecurityEvent);

            try {
                Notification::send(User::admins(), new UnauthorizedLoginReported($loginSecurityEvent));
            } catch (Throwable $e) {
                Log::error('Failed to dispatch UnauthorizedLoginReported notification', [
                    'login_security_event_id' => $loginSecurityEvent->LoginSecurityEventID,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'status' => 'not_recognized']);
        }

        return view('admin.security.thanks', ['status' => 'not_recognized']);
    }

    private function terminateAffectedSession(LoginSecurityEvent $event): void
    {
        if (! $event->SessionID) {
            return;
        }

        DB::table('sessions')->where('id', $event->SessionID)->delete();

        $user = $event->user ?? User::find($event->UserID);
        if ($user && $user->current_session_id === $event->SessionID) {
            $user->current_session_id = null;
            $user->save();
        }
    }
}
