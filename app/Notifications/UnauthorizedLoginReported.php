<?php

namespace App\Notifications;

use App\Models\LoginSecurityEvent;
use App\Notifications\Concerns\FormatsMailBadge;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UnauthorizedLoginReported extends Notification
{
    use FormatsMailBadge;

    public function __construct(public LoginSecurityEvent $event) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->event->loadMissing('user');

        return (new MailMessage)
            ->subject('Security Alert: Potential Unauthorized Admin Access')
            ->line($this->badgeHtml('POTENTIAL UNAUTHORIZED ACCESS', '#dc2626'))
            ->line("The \"{$event->user?->name}\" administrator account reported a login it did not recognize.")
            ->line('**Affected Account:** '.($event->user?->full_name ?? $event->user?->name ?? 'Unknown'))
            ->line('**Date & Time:** '.$event->LoginAt->format('m/d/Y g:i A'))
            ->line('**IP Address:** '.($event->IPAddress ?? 'Unavailable'))
            ->line('**Device/Browser:** '.($event->DeviceSummary ?? 'Unavailable'))
            ->line('The affected session has been ended. Review this account\'s activity and consider resetting its password if you believe it was compromised.')
            ->action('Review User Management', route('admin.users.index'));
    }

    public function toDatabase(object $notifiable): array
    {
        $event = $this->event->loadMissing('user');

        return [
            'title' => 'Potential Unauthorized Access',
            'description' => "The \"{$event->user?->name}\" account reported a login from {$event->LoginAt->format('m/d/Y g:i A')} ({$event->IPAddress}) as not recognized. The affected session has been ended.",
            'url' => route('admin.users.index'),
            'icon' => 'triangle-alert',
            'color' => 'danger',
            'event_id' => $event->LoginSecurityEventID,
        ];
    }
}
