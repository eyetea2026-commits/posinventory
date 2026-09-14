<?php

namespace App\Notifications;

use App\Models\LoginSecurityEvent;
use App\Notifications\Concerns\FormatsMailBadge;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

// Not ShouldQueue: this app has no reliable persistent queue worker
// (Hostinger shared hosting has no crontab access), so dispatch inline,
// matching every other notification in this app.
//
// Sent to the ONE admin who just logged in (via $user->notify(), same
// single-recipient pattern as PasswordResetRequested) -- a self-check on
// their own account, not a broadcast to every admin. Other admins are only
// looped in via UnauthorizedLoginReported if this login is flagged as not
// recognized.
class AdminLoginSecurityAlert extends Notification
{
    use FormatsMailBadge;

    public function __construct(public LoginSecurityEvent $event)
    {
    }

    // Database (in-app) notification fires regardless -- it doesn't need an
    // email address. Mail is skipped when the admin has none registered,
    // same as PasswordResetRequested's existing precedent.
    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->event;

        // Two distinct buttons (Yes/No) aren't supported by MailMessage's
        // fluent ->action() (one button only), so this renders a small
        // custom markdown mail view instead -- same ->markdown() +
        // resources/views/emails/ convention OtpMail already uses, still
        // Laravel's built-in mail component system, no bespoke HTML template.
        return (new MailMessage)
            ->subject('Security Alert: New Admin Login')
            ->markdown('emails.admin-login-security-alert', [
                'badge' => $this->badgeHtml('SECURITY ALERT', '#d97706'),
                'adminName' => $notifiable->full_name,
                'date' => $event->LoginAt->format('F j, Y'),
                'time' => $event->LoginAt->format('g:i A'),
                'ipAddress' => $event->IPAddress ?? 'Unavailable',
                'device' => $event->DeviceSummary ?? 'Unavailable',
                'confirmUrl' => URL::signedRoute('admin.security.review', ['loginSecurityEvent' => $event->LoginSecurityEventID, 'intent' => 'confirm']),
                'denyUrl' => URL::signedRoute('admin.security.review', ['loginSecurityEvent' => $event->LoginSecurityEventID, 'intent' => 'deny']),
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        $event = $this->event;

        return [
            'title' => 'Security Alert — Was this you?',
            'description' => "Your Admin account was used to log in on {$event->LoginAt->format('F j, Y g:i A')} from {$event->IPAddress} ({$event->DeviceSummary}).",
            'url' => route('admin.security.review', ['loginSecurityEvent' => $event->LoginSecurityEventID]),
            'icon' => 'shield-alert',
            'color' => 'warning',
            'event_id' => $event->LoginSecurityEventID,
        ];
    }
}
