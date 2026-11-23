<?php

namespace App\Notifications;

use App\Models\LoginSecurityEvent;
use App\Notifications\Concerns\FormatsMailBadge;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class AdminLoginSecurityAlert extends Notification
{
    use FormatsMailBadge;

    public function __construct(public LoginSecurityEvent $event) {}

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->event;

        return (new MailMessage)
            ->subject('Security Alert: New Admin Login')
            ->markdown('emails.admin-login-security-alert', [
                'badge' => $this->badgeHtml('SECURITY ALERT', '#d97706'),
                'adminName' => $notifiable->full_name,
                'date' => $event->LoginAt->format('m/d/Y'),
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
            'description' => "Your Admin account was used to log in on {$event->LoginAt->format('m/d/Y g:i A')} from {$event->IPAddress} ({$event->DeviceSummary}).",
            'url' => route('admin.security.review', ['loginSecurityEvent' => $event->LoginSecurityEventID]),
            'icon' => 'shield-alert',
            'color' => 'warning',
            'event_id' => $event->LoginSecurityEventID,
        ];
    }
}
