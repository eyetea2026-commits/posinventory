<?php

namespace App\Notifications;

use App\Models\SalesReturn;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

// ShouldQueue: outbound network calls (mail) triggered directly from a web
// request on this host are unreliable — proven via repeated side-by-side
// tests where the identical send succeeds every time from a CLI process but
// intermittently vanishes with no error from a real page load. A cron job
// runs `queue:work` so dispatch always happens from that reliable CLI path.
// The caller already wraps ->notify() in a try/catch so a queue-dispatch
// hiccup here can't break the approve/decline response.
class ReturnRequestDeclined extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

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
            ->subject("Return Request {$requestNumber} Declined")
            ->line("Your {$this->salesReturn->ReturnType} request {$requestNumber} has been declined.")
            ->line("Reason: {$this->salesReturn->DeclineReason}")
            ->line('Date & Time: ' . now()->format('F j, Y g:i A'))
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
