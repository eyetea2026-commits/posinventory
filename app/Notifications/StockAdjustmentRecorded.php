<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockAdjustmentRecorded extends Notification implements ShouldQueue
{
    use Queueable;

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
