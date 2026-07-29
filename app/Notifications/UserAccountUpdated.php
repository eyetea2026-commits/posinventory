<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\FormatsMailBadge;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not ShouldQueue: this app has no reliable persistent queue worker
// (Hostinger shared hosting has no crontab access), so dispatch inline.
class UserAccountUpdated extends Notification
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
            ->subject("User Account Updated: {$this->account->name}")
            ->line($this->badgeHtml('ACCOUNT UPDATED', '#3b82f6'))
            ->line("The account **\"{$this->account->name}\"** was updated.")
            ->line('**Date & Time:** ' . now()->format('F j, Y g:i A'))
            ->action('View Users', route('admin.users.index'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'User Account Updated',
            'description' => "The account \"{$this->account->name}\" was updated.",
            'url' => route('admin.users.index'),
            'icon' => 'user-cog',
            'color' => 'info',
        ];
    }
}
