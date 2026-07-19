<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not ShouldQueue: this app has no queue worker running (QUEUE_CONNECTION=database
// with nothing ever processing it), so a queued notification silently never
// delivers. Dispatch inline instead.
class ProductReceived extends Notification
{
    public function __construct(
        public Product $product,
        public Supplier $supplier,
        public int $quantity,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Product Received: {$this->product->ProductName}")
            ->line("Received {$this->quantity} x \"{$this->product->ProductName}\" from \"{$this->supplier->SupplierName}\".")
            ->action('View Stock Receiving', route('admin.stock-receivings.index'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Product Received',
            'description' => "Received {$this->quantity} x \"{$this->product->ProductName}\" from \"{$this->supplier->SupplierName}\".",
            'url' => route('admin.stock-receivings.index'),
            'icon' => 'clipboard-check',
            'color' => 'success',
        ];
    }
}
