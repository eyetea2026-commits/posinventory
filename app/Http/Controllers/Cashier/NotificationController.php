<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (! auth()->user() || ! auth()->user()->isCashier()) {
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

        return view('cashier.notifications.index', [
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

        return redirect($notification->data['url'] ?? route('cashier.notifications.index'));
    }

    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('status', 'All notifications marked as read.');
    }
}
