<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class MailAfterResponseChannel extends MailChannel
{
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
