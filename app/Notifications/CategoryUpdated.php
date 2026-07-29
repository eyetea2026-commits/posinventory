<?php

namespace App\Notifications;

use App\Models\Category;
use App\Notifications\Concerns\FormatsMailBadge;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not ShouldQueue: this app has no reliable persistent queue worker
// (Hostinger shared hosting has no crontab access), so dispatch inline.
// Covers both "Category Created" and "Category Updated" via $action.
class CategoryUpdated extends Notification
{
    use FormatsMailBadge;

    public function __construct(
        public Category $category,
        public string $action = 'Updated',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Category {$this->action}: {$this->category->CategoryName}")
            ->line($this->badgeHtml(strtoupper("CATEGORY {$this->action}"), '#3b82f6'))
            ->line("Category **\"{$this->category->CategoryName}\"** was {$this->action}.")
            ->line('**Date & Time:** ' . now()->format('F j, Y g:i A'))
            ->action('View Categories', route('admin.categories.index'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => "Category {$this->action}",
            'description' => "Category \"{$this->category->CategoryName}\" was {$this->action}.",
            'url' => route('admin.categories.index'),
            'icon' => 'tag',
            'color' => 'info',
        ];
    }
}
