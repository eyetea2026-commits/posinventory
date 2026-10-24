<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ActionNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends the notification card for actions that have no notification of
 * their own. A failure is logged, never shown: the action itself already
 * succeeded.
 */
class ActionNotifier
{
    /**
     * Every Administrator (including the one who did it).
     */
    public static function toAdmins(string $title, string $description, string $url, string $icon = '', string $color = 'info'): void
    {
        static::send(User::admins(), new ActionNotification($title, $description, $url, $icon, $color));
    }

    /**
     * One user, e.g. the Cashier confirming their own work.
     */
    public static function toUser(?User $user, string $title, string $description, string $url, string $icon = '', string $color = 'info'): void
    {
        if ($user === null) {
            return;
        }

        static::send($user, new ActionNotification($title, $description, $url, $icon, $color));
    }

    /**
     * @param  mixed  $notifiables
     */
    private static function send($notifiables, ActionNotification $notification): void
    {
        try {
            Notification::send($notifiables, $notification);
        } catch (Throwable $e) {
            Log::error('Failed to dispatch action notification', [
                'title' => $notification->title,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
