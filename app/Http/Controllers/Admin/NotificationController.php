<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (! auth()->user() || ! auth()->user()->isAdmin()) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $notifications = auth()->user()
            ->notifications()
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.notifications.index', [
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(string $notificationId)
    {
        $notification = auth()->user()->notifications()->findOrFail($notificationId);
        $notification->markAsRead();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        // Clicking a notification both acknowledges it and takes the admin to
        // the relevant page (its "View Details" destination).
        return redirect($notification->data['url'] ?? route('admin.notifications.index'));
    }

    public function markAllAsRead()
    {
        // A bulk UPDATE instead of unreadNotifications->markAsRead(), which
        // loads every unread row into memory and issues one UPDATE each.
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('status', 'All notifications marked as read.');
    }
}
