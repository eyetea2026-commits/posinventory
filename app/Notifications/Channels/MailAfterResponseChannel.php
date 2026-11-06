<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The mail channel for notifications, sending after the response.
 *
 * Every notification email goes through the mail server (Gmail SMTP), which
 * takes seconds per message from the live server; sent inline, each Save
 * waited for one email per Administrator and could run past the web
 * server's time limit (504). The email is now sent right after the page has
 * been answered (the server closes the response first), so a save returns as
 * soon as its data is written. The in-app (database) notification is still
 * saved immediately. A mail failure is logged and never reaches the user.
 */
class MailAfterResponseChannel extends MailChannel
{
    /**
     * @param  mixed  $notifiable
     */
    public function send($notifiable, Notification $notification)
    {
        defer(function () use ($notifiable, $notification) {
            try {
                parent::send($notifiable, $notification);
            } catch (Throwable $e) {
                Log::error('Failed to send notification email', [
                    'notification' => $notification::class,
                    'exception' => $e->getMessage(),
                ]);
            }
        }, always: true);
    }
}
