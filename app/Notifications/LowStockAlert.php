<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

// ShouldQueue: outbound network calls (mail) triggered directly from a web
// request on this host are unreliable — proven via repeated side-by-side
// tests where the identical send succeeds every time from a CLI process but
// intermittently vanishes with no error from a real page load. A cron job
// runs `queue:work` so dispatch always happens from that reliable CLI path.
class LowStockAlert extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

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
            ->line('Date & Time: ' . now()->format('F j, Y g:i A'))
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
