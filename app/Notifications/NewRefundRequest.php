<?php

namespace App\Notifications;

use App\Models\SalesReturn;
use App\Notifications\Concerns\FormatsMailBadge;
use App\Notifications\Concerns\FormatsReturnItemsTable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not ShouldQueue: this app has no reliable persistent queue worker
// (Hostinger shared hosting has no crontab access), so dispatch inline.
class NewRefundRequest extends Notification
{
    use FormatsMailBadge, FormatsReturnItemsTable;

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
        $requestNumber = 'RR-' . str_pad($this->salesReturn->SalesReturnID, 5, '0', STR_PAD_LEFT);
        $items = $this->salesReturn->relationLoaded('items')
            ? $this->salesReturn->items
            : $this->salesReturn->items()->with('product')->get();

        $message = (new MailMessage)
            ->subject("New {$this->salesReturn->ReturnType} Request {$requestNumber}")
            ->line($this->badgeHtml('PENDING REVIEW', '#d97706'))
            ->line("A new **{$this->salesReturn->ReturnType}** request **{$requestNumber}** was submitted.")
            ->line('**Date & Time:** ' . now()->format('F j, Y g:i A'))
            ->line('**Customer:** ' . ($this->salesReturn->CustomerName ?: 'Walk-in Customer'));

        if ($items->isNotEmpty()) {
            $message->line($this->buildItemsTableHtml($items));
        }

        return $message
            ->line('**Action Required:** review and approve or decline this request.')
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
