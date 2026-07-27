<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

// ShouldQueue: outbound network calls (mail) triggered directly from a web
// request on this host are unreliable — proven via repeated side-by-side
// tests where the identical send succeeds every time from a CLI process but
// intermittently vanishes with no error from a real page load. A cron job
// runs `queue:work` so dispatch always happens from that reliable CLI path.
// The caller still wraps ->notify() in a try/catch so a queue-dispatch
// hiccup here can't block the OTP request itself.
//
// Covers both "password reset requested" and "OTP verification requested"
// from the notification spec — in this app they're the same event (issuing
// the OTP IS the password-reset request), so one notification represents
// both rather than firing two near-identical alerts for one action.
class PasswordResetRequested extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

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
