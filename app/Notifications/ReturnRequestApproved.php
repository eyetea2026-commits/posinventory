<?php

namespace App\Notifications;

use App\Models\SalesReturn;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not ShouldQueue: this app has no queue worker running (QUEUE_CONNECTION=database
// with nothing ever processing it), so a queued notification silently never
// delivers. Dispatch inline instead — the caller already wraps ->notify() in a
// try/catch so a mail hiccup here can't break the approve/decline response.
class ReturnRequestApproved extends Notification
{
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

        return (new MailMessage)
            ->subject("Return Request {$requestNumber} Approved")
            ->line("Your {$this->salesReturn->ReturnType} request {$requestNumber} has been approved.")
            ->action('View Request', route('cashier.refunds'));
    }

    public function toDatabase(object $notifiable): array
    {
        $requestNumber = 'RR-' . str_pad($this->salesReturn->SalesReturnID, 5, '0', STR_PAD_LEFT);

        return [
            'title' => 'Return Request Approved',
            'description' => "Your Return Request {$requestNumber} has been Approved.",
            'url' => route('cashier.refunds'),
            'icon' => 'clipboard-check',
            'color' => 'success',
        ];
    }
}
