<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use App\Notifications\Concerns\FormatsMailBadge;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not ShouldQueue: this app has no reliable persistent queue worker
// (Hostinger shared hosting has no crontab access), so dispatch inline.
//
// Covers "Purchase Order Rejection" from the notification spec — this app
// has no separate reject action distinct from cancel(); a PO cancelled
// before any stock is received against it is functionally a rejection.
class PurchaseOrderCancelled extends Notification
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
            ->subject("Purchase Order {$this->purchaseOrder->PONumber} Cancelled")
            ->line($this->badgeHtml('CANCELLED', '#dc2626'))
            ->line("Purchase order **{$this->purchaseOrder->PONumber}** has been cancelled.")
            ->line('**Date & Time:** ' . now()->format('F j, Y g:i A'))
            ->line('**Supplier:** ' . ($this->purchaseOrder->supplier?->SupplierName ?? 'Unknown supplier'))
            ->action('View Purchase Order', route('admin.purchase-orders.index'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Purchase Order Cancelled',
            'description' => "Purchase order {$this->purchaseOrder->PONumber} has been cancelled.",
            'url' => route('admin.purchase-orders.index'),
            'icon' => 'circle-x',
            'color' => 'danger',
        ];
    }
}
