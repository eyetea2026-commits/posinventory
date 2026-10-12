<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use App\Notifications\Concerns\FormatsMailBadge;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Sent when a Purchase Order's delivery is added to Inventory from Stock
// Receiving. Not ShouldQueue: this app has no reliable persistent queue
// worker (Hostinger shared hosting has no crontab access), so dispatch inline.
class DeliveryReceived extends Notification
{
    use FormatsMailBadge;

    public function __construct(
        public PurchaseOrder $purchaseOrder,
        public int $unitsReceived,
        public int $unitsOrdered,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    private function summary(): string
    {
        $supplier = $this->purchaseOrder->supplier?->SupplierName ?? 'Unknown supplier';
        $completeness = $this->unitsReceived >= $this->unitsOrdered ? 'in full' : 'partially';

        return "PO #{$this->purchaseOrder->PONumber} from \"{$supplier}\" was received {$completeness}: {$this->unitsReceived} of {$this->unitsOrdered} unit(s) added to Inventory.";
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Delivery Received: PO #{$this->purchaseOrder->PONumber}")
            ->line($this->badgeHtml('DELIVERY RECEIVED', '#16a34a'))
            ->line($this->summary())
            ->line('**Date & Time:** '.now()->format('m/d/Y g:i A'))
            ->action('View Stock Receiving', route('admin.stock-receivings.index'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Delivery Received',
            'description' => $this->summary(),
            'url' => route('admin.stock-receivings.index'),
            'icon' => 'clipboard-check',
            'color' => 'success',
        ];
    }
}
