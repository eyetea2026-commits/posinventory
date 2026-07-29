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
class ReturnRequestApproved extends Notification
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
            ->subject("Return Request {$requestNumber} Approved")
            ->line($this->badgeHtml('APPROVED', '#16a34a'))
            ->line("Your {$this->salesReturn->ReturnType} request **{$requestNumber}** has been approved.")
            ->line('**Date & Time:** ' . now()->format('F j, Y g:i A'))
            ->line('**Customer:** ' . ($this->salesReturn->CustomerName ?: 'Walk-in Customer'));

        if ($items->isNotEmpty()) {
            $message->line($this->buildItemsTableHtml($items));
        }

        return $message
            ->line('**Action Required:** you may now process the refund or replacement for this request.')
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
