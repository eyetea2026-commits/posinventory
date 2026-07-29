<?php

namespace App\Notifications;

use App\Models\Product;
use App\Notifications\Concerns\FormatsMailBadge;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not ShouldQueue: this app has no reliable persistent queue worker
// (Hostinger shared hosting has no crontab access), so dispatch inline.
// Covers both "Product Created" and "Product Updated" via $action.
class ProductUpdated extends Notification
{
    use FormatsMailBadge;

    public function __construct(
        public Product $product,
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
            ->subject("Product {$this->action}: {$this->product->ProductName}")
            ->line($this->badgeHtml(strtoupper("PRODUCT {$this->action}"), '#3b82f6'))
            ->line("Product **\"{$this->product->ProductName}\"** was {$this->action}.")
            ->line('**Date & Time:** ' . now()->format('F j, Y g:i A'))
            ->line('**Price:** ₱' . number_format((float) $this->product->Price, 2))
            ->action('View Products', route('admin.products.index'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => "Product {$this->action}",
            'description' => "Product \"{$this->product->ProductName}\" was {$this->action}.",
            'url' => route('admin.products.index'),
            'icon' => 'package',
            'color' => 'info',
        ];
    }
}
