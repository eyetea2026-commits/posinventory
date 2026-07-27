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
class StockAdjustmentRecorded extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

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
            ->line('Date & Time: ' . now()->format('F j, Y g:i A'))
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
