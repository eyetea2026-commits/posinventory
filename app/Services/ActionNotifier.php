<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ActionNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ActionNotifier
{
    public static function toAdmins(string $title, string $description, string $url, string $icon = '', string $color = 'info'): void
    {
        static::send(User::admins(), new ActionNotification($title, $description, $url, $icon, $color));
    }

    public static function toUser(?User $user, string $title, string $description, string $url, string $icon = '', string $color = 'info'): void
    {
        if ($user === null) {
            return;
        }

        static::send($user, new ActionNotification($title, $description, $url, $icon, $color));
    }

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
