<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not ShouldQueue: this app has no queue worker running (QUEUE_CONNECTION=database
// with nothing ever processing it), so a queued notification silently never
// delivers. Dispatch inline instead.
class StockAdjustmentRecorded extends Notification
{
    public function __construct(
        public Product $product,
        public int $quantityAdjust,
        public int $newQuantity,
        public string $reason,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sign = $this->quantityAdjust >= 0 ? '+' : '';

        return (new MailMessage)
            ->subject("Stock Adjustment: {$this->product->ProductName}")
            ->line("\"{$this->product->ProductName}\" was adjusted by {$sign}{$this->quantityAdjust} (new total: {$this->newQuantity}).")
            ->line("Reason: {$this->reason}")
            ->action('View Stock Adjustments', route('admin.stock-adjustments.index'));
    }

    public function toDatabase(object $notifiable): array
    {
        $sign = $this->quantityAdjust >= 0 ? '+' : '';

        return [
            'title' => 'Stock Adjustment',
            'description' => "\"{$this->product->ProductName}\" adjusted by {$sign}{$this->quantityAdjust} (new total: {$this->newQuantity}).",
            'url' => route('admin.stock-adjustments.index'),
            'icon' => 'sliders-horizontal',
            'color' => 'info',
        ];
    }
}
