<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use App\Notifications\Concerns\FormatsMailBadge;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not ShouldQueue: this app has no reliable persistent queue worker
// (Hostinger shared hosting has no crontab access), so dispatch inline.
class PurchaseOrderApproved extends Notification
{
    use FormatsMailBadge;

    public function __construct(
        public PurchaseOrder $purchaseOrder,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Purchase Order {$this->purchaseOrder->PONumber} Approved")
            ->line($this->badgeHtml('APPROVED', '#16a34a'))
            ->line("Purchase order **{$this->purchaseOrder->PONumber}** has been approved and is ready to receive against.")
            ->line('**Date & Time:** ' . now()->format('F j, Y g:i A'))
            ->line('**Supplier:** ' . ($this->purchaseOrder->supplier?->SupplierName ?? 'Unknown supplier'))
            ->action('View Purchase Order', route('admin.purchase-orders.index'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Purchase Order Approved',
            'description' => "Purchase order {$this->purchaseOrder->PONumber} has been approved.",
            'url' => route('admin.purchase-orders.index'),
            'icon' => 'clipboard-check',
            'color' => 'success',
        ];
    }
}
