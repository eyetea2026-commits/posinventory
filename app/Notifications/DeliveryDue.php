<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use App\Notifications\Concerns\FormatsMailBadge;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

// Sent once per pending delivery when its Expected Delivery Date is today
// or has already passed. Not ShouldQueue: no reliable queue worker on this
// hosting, so dispatch inline.
class DeliveryDue extends Notification
{
    use FormatsMailBadge;

    public function __construct(public PurchaseOrder $purchaseOrder) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    private function daysOverdue(): int
    {
        return (int) Carbon::parse($this->purchaseOrder->ExpectedDeliveryDate)->startOfDay()
            ->diffInDays(now()->startOfDay(), false);
    }

    private function title(): string
    {
        return $this->daysOverdue() > 0 ? 'Delivery Overdue' : 'Delivery Due Today';
    }

    private function summary(): string
    {
        $supplier = $this->purchaseOrder->supplier?->SupplierName ?? 'Unknown supplier';
        $expected = Carbon::parse($this->purchaseOrder->ExpectedDeliveryDate)->format('M d, Y');
        $days = $this->daysOverdue();
        $timing = $days > 0 ? "{$days} day(s) overdue (expected {$expected})" : "expected today ({$expected})";

        return "PO #{$this->purchaseOrder->PONumber} from \"{$supplier}\" is {$timing} and hasn't been received yet.";
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->title()}: PO #{$this->purchaseOrder->PONumber}")
            ->line($this->badgeHtml(strtoupper($this->title()), '#d97706'))
            ->line($this->summary())
            ->action('View Stock Receiving', route('admin.stock-receivings.index'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'description' => $this->summary(),
            'url' => route('admin.stock-receivings.index'),
            'icon' => 'truck',
            'color' => 'warning',
        ];
    }
}
