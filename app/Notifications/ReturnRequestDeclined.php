<?php

namespace App\Notifications;

use App\Models\SalesReturn;
use App\Notifications\Concerns\FormatsMailBadge;
use App\Notifications\Concerns\FormatsReturnItemsTable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not ShouldQueue: this app has no reliable persistent queue worker
// (Hostinger shared hosting has no crontab access), so dispatch inline. The
// caller already wraps ->notify() in a try/catch so a mail hiccup here
// can't break the approve/decline response.
class ReturnRequestDeclined extends Notification
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
        $items = $this->salesReturn->items()->with('product')->get();

        $message = (new MailMessage)
            ->subject("Return Request {$requestNumber} Declined")
            ->line($this->badgeHtml('DECLINED', '#dc2626'))
            ->line("Your {$this->salesReturn->ReturnType} request **{$requestNumber}** has been declined.")
            ->line('**Date & Time:** ' . now()->format('F j, Y g:i A'))
            ->line('**Customer:** ' . ($this->salesReturn->CustomerName ?: 'Walk-in Customer'))
            ->line('**Decline Reason:** ' . ($this->salesReturn->DeclineReason ?: 'Not specified'));

        if ($items->isNotEmpty()) {
            $message->line($this->buildItemsTableHtml($items));
        }

        return $message
            ->line('**Action Required:** no further action is needed — the items will not be refunded or replaced.')
            ->action('View Request', route('cashier.refunds'));
    }

    public function toDatabase(object $notifiable): array
    {
        $requestNumber = 'RR-' . str_pad($this->salesReturn->SalesReturnID, 5, '0', STR_PAD_LEFT);

        return [
            'title' => 'Return Request Declined',
            'description' => "Your Return Request {$requestNumber} has been Declined. Reason: {$this->salesReturn->DeclineReason}",
            'url' => route('cashier.refunds'),
            'icon' => 'triangle-alert',
            'color' => 'danger',
        ];
    }
}
