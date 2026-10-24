<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DamagedProduct;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\ActionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

// Actions that had no notification of their own now send the "something
// just happened" card: every Administrator for admin and cashier work,
// plus the Cashier for their own sales, return requests, refunds and
// replacements. Bell + card only, never email.
class ActionNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $otherAdmin;

    private User $cashier;

    private Product $product;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->otherAdmin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);

        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $this->product = Product::create([
            'ProductName' => 'Dome Camera', 'Model' => 'CAM-01',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 50, 'Status' => 'Available']);
        $this->supplier = Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0912-345-6789', 'Email' => 'acme@example.com', 'Address' => 'N/A']);
    }

    private function assertAdminsGot(string $title): void
    {
        foreach ([$this->admin, $this->otherAdmin] as $admin) {
            Notification::assertSentTo($admin, ActionNotification::class, function (ActionNotification $notification, array $channels) use ($title) {
                return $notification->title === $title && $channels === ['database'];
            });
        }
    }

    public function test_the_card_is_saved_for_the_bell_and_never_emailed(): void
    {
        $this->actingAs($this->admin)->patch(route('admin.products.toggle-status', $this->product));

        $notification = $this->otherAdmin->notifications()->firstOrFail();
        $this->assertSame('Product Deactivated', $notification->data['title']);
        $this->assertSame('The product "Dome Camera" has been deactivated.', $notification->data['description']);
        $this->assertSame(route('admin.products.index'), $notification->data['url']);
    }

    public function test_admin_actions_notify_every_administrator(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)->patch(route('admin.products.toggle-status', $this->product));
        $this->assertAdminsGot('Product Deactivated');
        $this->actingAs($this->admin)->patch(route('admin.products.toggle-status', $this->product));
        $this->assertAdminsGot('Product Activated');

        $inactive = User::factory()->create(['role_id' => $this->cashier->role_id, 'is_active' => false]);
        $this->actingAs($this->admin)->post(route('admin.users.activate', $inactive));
        $this->assertAdminsGot('User Account Activated');

        $category = $this->product->category;
        $this->actingAs($this->admin)->postJson(route('admin.categories.brands.store', $category), ['BrandName' => 'Hikvision'])->assertOk();
        $this->assertAdminsGot('Brand Added');

        $this->actingAs($this->admin)->postJson(route('admin.products.suppliers.store', $this->product), ['SupplierID' => $this->supplier->SupplierID])->assertOk();
        $this->assertAdminsGot('Product Supplier Added');
        $link = ProductSupplier::firstOrFail();
        $this->actingAs($this->admin)->postJson(route('admin.product-suppliers.prefer', $link))->assertOk();
        $this->assertAdminsGot('Preferred Supplier Changed');
        $this->actingAs($this->admin)->deleteJson(route('admin.product-suppliers.destroy', $link))->assertOk();
        $this->assertAdminsGot('Product Supplier Removed');

        $promo = Discount::create([
            'DiscountRate' => 20, 'DiscountType' => Discount::TYPE_PERCENTAGE, 'Name' => 'Summer Sale',
            'PromoCode' => 'SUMMER20', 'Description' => 'Seasonal promo',
            'StartDate' => now()->format('Y-m-d'), 'EndDate' => now()->addDays(30)->format('Y-m-d'),
        ]);
        $this->actingAs($this->admin)->delete(route('admin.discounts.destroy', $promo));
        $this->assertAdminsGot('Promo Code Deleted');

        $damage = DamagedProduct::create([
            'ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID, 'Quantity' => 2,
            'Description' => 'Cracked casing', 'DateRecorded' => now()->format('Y-m-d'), 'DamageType' => 'broken',
            'Status' => DamagedProduct::STATUS_PENDING,
        ]);
        $this->actingAs($this->admin)->post(route('admin.damages.dispose', $damage));
        $this->assertAdminsGot('Damage Record Disposed');

        $order = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-1', 'SupplierID' => $this->supplier->SupplierID,
            'PurchaseDate' => now()->format('Y-m-d'), 'Status' => PurchaseOrder::STATUS_DRAFT, 'CreatedBy' => $this->admin->id,
        ]);
        $order->items()->create(['ProductID' => $this->product->ProductID, 'Quantity' => 5, 'CostPriceAtOrder' => 600]);
        $this->actingAs($this->admin)->post(route('admin.purchase-orders.submit', $order));
        $this->assertAdminsGot('Purchase Order Submitted');

        Notification::assertNotSentTo($this->cashier, ActionNotification::class);
    }

    public function test_a_cashiers_sale_shows_the_cashier_their_own_card(): void
    {
        Notification::fake();

        $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $this->product->ProductID, 'qty' => 2]],
            'payment_method' => 'cash',
            'payment_amount' => 2000,
        ])->assertOk();

        Notification::assertSentTo($this->cashier, ActionNotification::class, function (ActionNotification $notification) {
            return $notification->title === 'Sale Completed'
                && str_contains($notification->description, '₱2,000.00 has been completed.')
                && $notification->url === route('cashier.transactions');
        });
        // Administrators keep their existing "Sale Completed" notification instead.
        Notification::assertNotSentTo($this->admin, ActionNotification::class);
    }
}
