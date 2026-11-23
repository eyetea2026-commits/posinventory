<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\Channels\MailAfterResponseChannel;
use App\Notifications\DeliveryReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Tests\TestCase;

class NotificationMailAfterResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_email_is_sent_after_the_response_and_the_bell_notification_right_away(): void
    {
        $this->assertInstanceOf(MailAfterResponseChannel::class, app(ChannelManager::class)->driver('mail'));

        $admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id, 'email' => 'owner@example.com']);
        $supplier = Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0912-345-6789', 'Address' => 'N/A']);
        $order = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-000001', 'PurchaseDate' => now()->toDateString(),
            'Status' => PurchaseOrder::STATUS_PENDING, 'SupplierID' => $supplier->SupplierID, 'CreatedBy' => $admin->id,
        ]);
        $sentEmails = fn () => app('mailer')->getSymfonyTransport()->messages()->count();

        $admin->notify(new DeliveryReceived($order->load('supplier'), 1, 1));

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(0, $sentEmails());

        app(DeferredCallbackCollection::class)->invoke();

        $this->assertSame(1, $sentEmails());
        $this->assertSame('Delivery Received: PO #PO-TEST-000001', app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage()->getSubject());
    }
}
