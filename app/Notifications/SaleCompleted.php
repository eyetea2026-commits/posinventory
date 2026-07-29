<?php

namespace App\Notifications;

use App\Models\SalesTransaction;
use App\Notifications\Concerns\FormatsMailBadge;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not ShouldQueue: this app has no reliable persistent queue worker
// (Hostinger shared hosting has no crontab access), so dispatch inline.
class SaleCompleted extends Notification
{
    use FormatsMailBadge;

    public function __construct(
        public SalesTransaction $transaction,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $billing = $this->transaction->billing;
        $total = $billing ? number_format((float) $billing->BillingAmount, 2) : '0.00';
        $cashierName = $this->transaction->staff?->user?->full_name ?? $this->transaction->staff?->FirstName ?? 'Unknown cashier';

        return (new MailMessage)
            ->subject("Sale Completed — ₱{$total}")
            ->line($this->badgeHtml('SALE COMPLETED', '#16a34a'))
            ->line("A sale was completed for **₱{$total}**.")
            ->line('**Date & Time:** ' . now()->format('F j, Y g:i A'))
            ->line('**Customer:** ' . ($this->transaction->CustomerName ?: 'Walk-in Customer'))
            ->line('**Cashier:** ' . $cashierName)
            ->line('**Items:** ' . $this->transaction->items()->count())
            ->action('View Transaction', route('admin.reports.index'));
    }

    public function toDatabase(object $notifiable): array
    {
        $billing = $this->transaction->billing;
        $total = $billing ? number_format((float) $billing->BillingAmount, 2) : '0.00';

        return [
            'title' => 'Sale Completed',
            'description' => "Sale completed for ₱{$total} ({$this->transaction->CustomerName}).",
            'url' => route('admin.reports.index'),
            'icon' => 'circle-check',
            'color' => 'success',
        ];
    }
}
