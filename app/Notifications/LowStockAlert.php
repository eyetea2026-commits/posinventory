<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not ShouldQueue: this app has no queue worker running (QUEUE_CONNECTION=database
// with nothing ever processing it), so a queued notification silently never
// delivers. Dispatch inline instead.
class LowStockAlert extends Notification
{
    public function __construct(
        public Product $product,
        public int $quantity,
        public string $status,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->status}: {$this->product->ProductName}")
            ->line("\"{$this->product->ProductName}\" is now at {$this->quantity} unit(s) in stock ({$this->status}).")
            ->action('View Inventory', route('admin.inventory.index'))
            ->line('Consider reordering soon to avoid stockouts.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->status,
            'description' => "\"{$this->product->ProductName}\" has {$this->quantity} unit(s) left.",
            'url' => route('admin.inventory.index'),
            'icon' => 'triangle-alert',
            'color' => $this->status === 'Out of Stock' ? 'danger' : 'warning',
        ];
    }
}
