<?php

namespace App\Http\Controllers;

use App\Models\SalesReturn;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Anything new?" check that every signed-in page sends every few seconds
 * (partials/live-updates), so notifications and return/refund requests
 * show up without a refresh. It only returns a short fingerprint of the
 * user's notifications and return requests; when that changes, the page
 * re-fetches itself and swaps in just its live parts (bell, request lists).
 *
 * Runs WITHOUT the session middleware (like /session/alive), so the polling
 * never counts as activity and an unattended screen still times out. The
 * user is found from the session cookie instead.
 */
class LiveUpdatesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->userFromSessionCookie($request);

        if (! $user) {
            return response()->json(['authenticated' => false], 401);
        }

        return response()->json(self::stateFor($user));
    }

    /**
     * @return array{signature: string, unread: int, latest_title: string|null}
     */
    public static function stateFor(User $user): array
    {
        $notifications = DB::table('notifications')
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey());

        $unread = (clone $notifications)->whereNull('read_at')->count();
        $notificationState = (clone $notifications)
            ->selectRaw('COUNT(*) AS total, MAX(created_at) AS newest, MAX(updated_at) AS changed, MAX(read_at) AS last_read')
            ->first();

        $returns = SalesReturn::query();
        if (! $user->isAdmin()) {
            $returns->where('StaffID', Staff::where('UserID', $user->id)->value('StaffID') ?? 0);
        }
        $returnState = $returns->selectRaw('COUNT(*) AS total, MAX(updated_at) AS changed')->first();

        $latestTitle = null;
        if ($unread > 0) {
            $latest = $user->unreadNotifications()->latest()->first();
            $latestTitle = $latest?->data['title'] ?? null;
        }

        return [
            'signature' => md5(implode('|', [
                $unread,
                $notificationState->total ?? 0,
                $notificationState->newest ?? '',
                $notificationState->changed ?? '',
                $notificationState->last_read ?? '',
                $returnState->total ?? 0,
                $returnState->changed ?? '',
            ])),
            'unread' => $unread,
            'latest_title' => $latestTitle,
        ];
    }

    /**
     * The signed-in user for this browser's session cookie, if that session
     * is still the user's current one and hasn't timed out.
     */
    private function userFromSessionCookie(Request $request): ?User
    {
        $sessionId = $request->cookie(config('session.cookie'));

        if (! is_string($sessionId) || ! preg_match('/^[A-Za-z0-9]{40}$/', $sessionId)) {
            return null;
        }

        $user = User::where('current_session_id', $sessionId)->first();

        if (! $user) {
            return null;
        }

        if (config('session.driver') === 'database') {
            $lastActivity = DB::table(config('session.table', 'sessions'))->where('id', $sessionId)->value('last_activity');

            if (! $lastActivity || $lastActivity < now()->timestamp - config('session.lifetime') * 60) {
                return null;
            }
        }

        return $user;
    }
}
