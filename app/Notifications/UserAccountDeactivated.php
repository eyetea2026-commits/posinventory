<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\FormatsMailBadge;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not ShouldQueue: this app has no reliable persistent queue worker
// (Hostinger shared hosting has no crontab access), so dispatch inline.
class UserAccountDeactivated extends Notification
{
    use FormatsMailBadge;

    public function __construct(
        public User $account,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("User Account Deactivated: {$this->account->name}")
            ->line($this->badgeHtml('DEACTIVATED', '#dc2626'))
            ->line("The account **\"{$this->account->name}\"** has been deactivated and can no longer log in.")
            ->line('**Date & Time:** ' . now()->format('F j, Y g:i A'))
            ->action('View Users', route('admin.users.index'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'User Account Deactivated',
            'description' => "The account \"{$this->account->name}\" has been deactivated.",
            'url' => route('admin.users.index'),
            'icon' => 'user-x',
            'color' => 'danger',
        ];
    }
}
