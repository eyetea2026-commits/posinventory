<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification implements ShouldQueue
{
    use Queueable;

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
