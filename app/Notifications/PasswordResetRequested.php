<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

// Not ShouldQueue: this app has no queue worker running (QUEUE_CONNECTION=database
// with nothing ever processing it), so a queued notification silently never
// delivers. Dispatch inline instead — the caller wraps ->notify() in a
// try/catch so a mail hiccup here can't block the OTP request itself.
//
// Covers both "password reset requested" and "OTP verification requested"
// from the notification spec — in this app they're the same event (issuing
// the OTP IS the password-reset request), so one notification represents
// both rather than firing two near-identical alerts for one action.
class PasswordResetRequested extends Notification
{
    public function __construct(
        public User $user,
        public Carbon $requestedAt,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Password Reset Requested for Your Admin Account')
            ->line("A password reset was requested for the \"{$this->user->name}\" administrator account, and a one-time verification code (OTP) was sent to this email address.")
            ->line('Date & Time: ' . $this->requestedAt->format('F j, Y g:i A'))
            ->line('If you did not request this, you can safely ignore this email — your password will not change unless the correct OTP is entered.')
            ->action('Go to Login', route('welcome'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Password Reset Requested',
            'description' => "A password reset OTP was requested for the \"{$this->user->name}\" account at {$this->requestedAt->format('F j, Y g:i A')}.",
            'url' => route('admin.dashboard'),
            'icon' => 'shield-alert',
            'color' => 'warning',
        ];
    }
}
