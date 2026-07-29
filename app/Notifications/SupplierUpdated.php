<?php

namespace App\Notifications;

use App\Models\Supplier;
use App\Notifications\Concerns\FormatsMailBadge;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not ShouldQueue: this app has no reliable persistent queue worker
// (Hostinger shared hosting has no crontab access), so dispatch inline.
// Covers both "Supplier Created" and "Supplier Updated" from the
// notification spec via the $action flag, rather than two near-identical
// classes for one entity.
class SupplierUpdated extends Notification
{
    use FormatsMailBadge;

    public function __construct(
        public Supplier $supplier,
        public string $action = 'Updated',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Supplier {$this->action}: {$this->supplier->SupplierName}")
            ->line($this->badgeHtml(strtoupper("SUPPLIER {$this->action}"), '#3b82f6'))
            ->line("Supplier **\"{$this->supplier->SupplierName}\"** was {$this->action}.")
            ->line('**Date & Time:** ' . now()->format('F j, Y g:i A'))
            ->line('**Contact:** ' . ($this->supplier->ContactNumber ?: '—') . ' / ' . ($this->supplier->Email ?: '—'))
            ->action('View Suppliers', route('admin.suppliers.index'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => "Supplier {$this->action}",
            'description' => "Supplier \"{$this->supplier->SupplierName}\" was {$this->action}.",
            'url' => route('admin.suppliers.index'),
            'icon' => 'truck',
            'color' => 'info',
        ];
    }
}
