<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\StockReceivingController;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\StockReceivingBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\DeliveryDue;
use App\Notifications\DeliveryReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DeliveryNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->supplier = Supplier::create([
            'SupplierName' => 'Acme Supplies', 'ContactNumber' => '0000', 'Email' => 'acme@example.com', 'Address' => 'N/A',
        ]);
        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $this->product = Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 5, 'ReorderThreshold' => 10, 'Status' => 'Low Stock']);
    }

    private static int $poCounter = 0;

    /**
     * A PO that has been printed and is waiting in Stock Receiving.
     */
    private function makePendingDelivery(?string $expectedDate, string $batchStatus = StockReceivingBatch::STATUS_PENDING): StockReceivingBatch
    {
        $po = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-'.str_pad((string) ++self::$poCounter, 6, '0', STR_PAD_LEFT),
            'PurchaseDate' => now()->subDays(7)->toDateString(),
            'ExpectedDeliveryDate' => $expectedDate,
            'Status' => PurchaseOrder::STATUS_PENDING,
            'SupplierID' => $this->supplier->SupplierID,
            'CreatedBy' => $this->admin->id,
        ]);
        PurchaseOrderItem::create([
            'PurchaseOrderID' => $po->PurchaseOrderID, 'ProductID' => $this->product->ProductID,
            'Quantity' => 10, 'CostPriceAtOrder' => 600,
        ]);

        return StockReceivingBatch::create(['PurchaseOrderID' => $po->PurchaseOrderID, 'Status' => $batchStatus]);
    }

    // ---- Stock received ----

    public function test_adding_a_delivery_to_inventory_notifies_admins_with_the_quantities(): void
    {
        Notification::fake();
        $batch = $this->makePendingDelivery(now()->toDateString());
        $item = PurchaseOrderItem::where('PurchaseOrderID', $batch->PurchaseOrderID)->first();

        $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'receipt_number' => 'DR-NOTIFY-1',
            'items' => [['purchase_order_item_id' => $item->PurchaseOrderItemID, 'quantity_received' => 6]],
        ])->assertRedirect(route('admin.stock-receivings.index'));

        Notification::assertSentTo($this->admin, DeliveryReceived::class, function (DeliveryReceived $notification) {
            $data = $notification->toDatabase($this->admin);

            return $notification->unitsReceived === 6
                && $notification->unitsOrdered === 10
                && str_contains($data['description'], 'received partially')
                && str_contains($data['description'], 'Acme Supplies');
        });
    }

    public function test_a_failed_delivery_completion_sends_no_received_notification(): void
    {
        Notification::fake();
        $batch = $this->makePendingDelivery(now()->toDateString());
        $item = PurchaseOrderItem::where('PurchaseOrderID', $batch->PurchaseOrderID)->first();

        // More than was ordered -- rejected, nothing written.
        $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'receipt_number' => 'DR-NOTIFY-2',
            'items' => [['purchase_order_item_id' => $item->PurchaseOrderItemID, 'quantity_received' => 99]],
        ]);

        Notification::assertNotSentTo($this->admin, DeliveryReceived::class);
    }

    // ---- Delivery due ----

    public function test_a_delivery_due_today_notifies_admins_exactly_once(): void
    {
        Notification::fake();
        $batch = $this->makePendingDelivery(now()->toDateString());

        StockReceivingController::notifyDueDeliveries();
        StockReceivingController::notifyDueDeliveries();

        Notification::assertSentToTimes($this->admin, DeliveryDue::class, 1);
        $this->assertNotNull($batch->fresh()->DueNotifiedAt);
    }

    public function test_an_overdue_delivery_is_labelled_overdue_with_the_day_count(): void
    {
        $batch = $this->makePendingDelivery(now()->subDays(3)->toDateString());

        $data = (new DeliveryDue($batch->purchaseOrder))->toDatabase($this->admin);

        $this->assertSame('Delivery Overdue', $data['title']);
        $this->assertStringContainsString('3 day(s) overdue', $data['description']);
    }

    public function test_a_delivery_due_today_is_labelled_due_today(): void
    {
        $batch = $this->makePendingDelivery(now()->toDateString());

        $data = (new DeliveryDue($batch->purchaseOrder))->toDatabase($this->admin);

        $this->assertSame('Delivery Due Today', $data['title']);
    }

    public function test_future_undated_and_completed_deliveries_are_not_flagged(): void
    {
        Notification::fake();
        $future = $this->makePendingDelivery(now()->addDays(2)->toDateString());
        $undated = $this->makePendingDelivery(null);
        $completed = $this->makePendingDelivery(now()->subDay()->toDateString(), StockReceivingBatch::STATUS_COMPLETED);

        StockReceivingController::notifyDueDeliveries();

        Notification::assertNothingSent();
        $this->assertNull($future->fresh()->DueNotifiedAt);
        $this->assertNull($undated->fresh()->DueNotifiedAt);
        $this->assertNull($completed->fresh()->DueNotifiedAt);
    }

    public function test_both_notification_emails_render(): void
    {
        $batch = $this->makePendingDelivery(now()->subDay()->toDateString());
        $po = $batch->purchaseOrder()->with('supplier')->first();

        $dueHtml = (string) (new DeliveryDue($po))->toMail($this->admin)->render();
        $receivedHtml = (string) (new DeliveryReceived($po, 10, 10))->toMail($this->admin)->render();

        $this->assertStringContainsString($po->PONumber, $dueHtml);
        $this->assertStringContainsString('DELIVERY OVERDUE', $dueHtml);
        $this->assertStringContainsString('received in full', $receivedHtml);
    }

    public function test_an_admin_page_load_triggers_the_due_check_and_the_bell_shows_it(): void
    {
        $this->makePendingDelivery(now()->subDay()->toDateString());

        $response = $this->actingAs($this->admin)->get(route('admin.products.index'));

        $response->assertOk();
        $this->assertSame(1, $this->admin->fresh()->unreadNotifications()->count());
        $response->assertSee('Delivery Overdue');
        $response->assertSee('fa-truck', false);
    }
}
