<?php

namespace App\Notifications;

use App\Models\Discount;
use App\Notifications\Concerns\FormatsMailBadge;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not ShouldQueue: this app has no reliable persistent queue worker
// (Hostinger shared hosting has no crontab access), so dispatch inline.
// Covers both "Discount Created" and "Discount Updated" via $action.
class DiscountUpdated extends Notification
{
    use FormatsMailBadge;

    public function __construct(
        public Discount $discount,
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
            ->subject("Discount {$this->action}: {$this->discount->Name}")
            ->line($this->badgeHtml(strtoupper("DISCOUNT {$this->action}"), '#3b82f6'))
            ->line("Discount **\"{$this->discount->Name}\"** ({$this->discount->DiscountRate}%) was {$this->action}.")
            ->line('**Date & Time:** ' . now()->format('F j, Y g:i A'))
            ->action('View Discounts', route('admin.discounts.index'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => "Discount {$this->action}",
            'description' => "Discount \"{$this->discount->Name}\" ({$this->discount->DiscountRate}%) was {$this->action}.",
            'url' => route('admin.discounts.index'),
            'icon' => 'percent',
            'color' => 'info',
        ];
    }
}
