<?php

namespace App\Notifications;

use App\Models\Product;
use App\Notifications\Concerns\FormatsMailBadge;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not ShouldQueue: this app has no reliable persistent queue worker
// (Hostinger shared hosting has no crontab access), so dispatch inline.
class StockAdjustmentRecorded extends Notification
{
    use FormatsMailBadge;

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
        $isIncrease = $this->quantityAdjust >= 0;

        return (new MailMessage)
            ->subject("Stock Adjustment: {$this->product->ProductName}")
            ->line($this->badgeHtml($isIncrease ? 'STOCK INCREASED' : 'STOCK DECREASED', $isIncrease ? '#16a34a' : '#d97706'))
            ->line("**\"{$this->product->ProductName}\"** was adjusted by **{$sign}{$this->quantityAdjust}** (new total: **{$this->newQuantity}**).")
            ->line("**Reason:** {$this->reason}")
            ->line('**Date & Time:** ' . now()->format('F j, Y g:i A'))
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
