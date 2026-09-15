<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LoginSecurityEvent;
use App\Models\User;
use App\Notifications\UnauthorizedLoginReported;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

// Deliberately reachable WITHOUT an authenticated session (routes live
// outside the `auth` middleware group): the email's "Yes"/"No" buttons must
// work from a phone that isn't logged in. Each action instead accepts EITHER
// a valid Laravel signed URL (proof of possessing the emailed link) OR an
// authenticated admin session (for the in-app prompt's fetch calls) -- no
// new auth mechanism, just an either/or check on top of what already exists.
class LoginSecurityController extends Controller
{
    // A valid signature already proves possession of THIS event's own
    // emailed link (the signature is generated per-event), so that branch
    // needs no further scoping. The authenticated-admin fallback (the
    // in-app "Was this you?" prompt) must additionally be the specific
    // admin the event belongs to -- being *an* admin isn't enough, or one
    // admin could confirm/deny another admin's own login-security prompt.
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

    // GET, signed: a real page to land on (rather than mutating on the bare
    // GET link) so an email client's link-prescanner can't silently trigger
    // the confirm/deny action just by fetching the URL to preview it.
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
                "\"{$loginSecurityEvent->user?->name}\" confirmed a login from {$loginSecurityEvent->LoginAt->format('F j, Y g:i A')} ({$loginSecurityEvent->IPAddress}) as recognized.",
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
                "\"{$loginSecurityEvent->user?->name}\" reported a login from {$loginSecurityEvent->LoginAt->format('F j, Y g:i A')} ({$loginSecurityEvent->IPAddress}) as NOT recognized.",
                $loginSecurityEvent->UserID
            );

            $this->terminateAffectedSession($loginSecurityEvent);

            Notification::send(User::admins(), new UnauthorizedLoginReported($loginSecurityEvent));
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'status' => 'not_recognized']);
        }

        return view('admin.security.thanks', ['status' => 'not_recognized']);
    }

    // Ends only the ONE session this specific login created -- never
    // whatever session happens to be "current" now, which could belong to a
    // legitimate, newer login the admin has since made. Reuses the same
    // sessions table / current_session_id column the one-session-per-account
    // feature already maintains, rather than inventing a new mechanism.
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
