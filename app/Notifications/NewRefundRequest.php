<?php

namespace App\Notifications;

use App\Models\SalesReturn;
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
class NewRefundRequest extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public SalesReturn $salesReturn,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $summary = $this->itemsSummary();

        return (new MailMessage)
            ->subject("New {$this->salesReturn->ReturnType} request #{$this->salesReturn->SalesReturnID}")
            ->line("A new {$this->salesReturn->ReturnType} request was submitted for {$summary}.")
            ->line("Customer: {$this->salesReturn->CustomerName}")
            ->line('Date & Time: ' . now()->format('F j, Y g:i A'))
            ->action('Review Request', route('admin.sales-returns.index'));
    }

    public function toDatabase(object $notifiable): array
    {
        $summary = $this->itemsSummary();

        return [
            'title' => 'New Refund Request',
            'description' => "{$summary} — {$this->salesReturn->ReturnType} request from {$this->salesReturn->CustomerName}.",
            'url' => route('admin.sales-returns.index'),
            'icon' => 'rotate-ccw',
            'color' => 'info',
        ];
    }

    private function itemsSummary(): string
    {
        $items = $this->salesReturn->relationLoaded('items') ? $this->salesReturn->items : $this->salesReturn->items()->with('product')->get();

        if ($items->isEmpty()) {
            return 'Unknown product';
        }

        return $items->map(function ($item) {
            $productName = $item->product?->ProductName ?? 'Unknown product';

            return "{$item->Quantity} x \"{$productName}\"";
        })->implode(', ');
    }
}
