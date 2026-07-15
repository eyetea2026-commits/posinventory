<?php

namespace App\Notifications;

use App\Models\SalesReturn;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewRefundRequest extends Notification implements ShouldQueue
{
    use Queueable;

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
        $productName = $this->salesReturn->product?->ProductName ?? 'Unknown product';

        return (new MailMessage)
            ->subject("New {$this->salesReturn->ReturnType} request #{$this->salesReturn->SalesReturnID}")
            ->line("A new {$this->salesReturn->ReturnType} request was submitted for {$this->salesReturn->Quantity} x \"{$productName}\".")
            ->line("Customer: {$this->salesReturn->CustomerName}")
            ->action('Review Request', route('admin.sales-returns.index'));
    }

    public function toDatabase(object $notifiable): array
    {
        $productName = $this->salesReturn->product?->ProductName ?? 'Unknown product';

        return [
            'title' => 'New Refund Request',
            'description' => "{$this->salesReturn->Quantity} x \"{$productName}\" — {$this->salesReturn->ReturnType} request from {$this->salesReturn->CustomerName}.",
            'url' => route('admin.sales-returns.index'),
            'icon' => 'rotate-ccw',
            'color' => 'info',
        ];
    }
}
