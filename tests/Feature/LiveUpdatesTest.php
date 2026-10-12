<?php

namespace Tests\Feature;

use App\Http\Controllers\LiveUpdatesController;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LiveUpdatesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);
    }

    /**
     * Gives the user a live session row and returns its id, as a real login would.
     */
    private function sessionFor(User $user, ?int $lastActivity = null): string
    {
        $sessionId = Str::random(40);
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => $lastActivity ?? now()->timestamp,
        ]);
        $user->forceFill(['current_session_id' => $sessionId])->save();

        return $sessionId;
    }

    private function poll(string $sessionId)
    {
        return $this->withCredentials()->withCookie(config('session.cookie'), $sessionId)->getJson(route('live.signature'));
    }

    private function notify(User $user, string $title): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Test',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => json_encode(['title' => $title, 'description' => 'x']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeReturnFor(User $cashier): SalesReturn
    {
        $category = Category::firstOrCreate(['CategoryName' => 'CCTV'], ['Description' => 'Cameras']);
        $product = Product::create([
            'ProductName' => 'Dome '.uniqid(), 'Model' => 'M-'.uniqid(),
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => 20, 'Status' => 'Available']);

        $receipt = $this->actingAs($cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => 1000,
        ])->assertOk()->json('receipt_number');
        $transactionId = (int) str_replace('RCT-', '', $receipt);

        $staffId = DB::table('SalesTransaction')->where('SalesTransactionID', $transactionId)->value('StaffID');

        return SalesReturn::create([
            'SalesTransactionID' => $transactionId,
            'ProductID' => $product->ProductID,
            'Quantity' => 1,
            'Reason' => 'Factory Defect',
            'ReturnType' => 'refund',
            'Status' => 'pending',
            'ReturnDate' => now(),
            'StaffID' => $staffId,
        ]);
    }

    public function test_signature_changes_when_a_notification_arrives_and_names_it(): void
    {
        $session = $this->sessionFor($this->admin);
        $before = $this->poll($session)->assertOk()->json();

        $this->notify($this->admin, 'New Return Request');
        $after = $this->poll($session)->assertOk()->json();

        $this->assertNotSame($before['signature'], $after['signature']);
        $this->assertSame($before['unread'] + 1, $after['unread']);
        $this->assertSame('New Return Request', $after['latest_title']);
        // Unchanged state: same signature, so pages don't re-fetch.
        $this->assertSame($after['signature'], $this->poll($session)->json('signature'));
    }

    public function test_signature_changes_when_a_notification_is_read_elsewhere(): void
    {
        $this->notify($this->cashier, 'Return Request Approved');
        $session = $this->sessionFor($this->cashier);
        $before = $this->poll($session)->json();

        $this->cashier->unreadNotifications()->update(['read_at' => now()]);

        $after = $this->poll($session)->json();
        $this->assertNotSame($before['signature'], $after['signature']);
        $this->assertSame(0, $after['unread']);
    }

    public function test_admin_sees_any_new_request_and_cashier_sees_status_changes_on_their_own(): void
    {
        $adminSession = $this->sessionFor($this->admin);
        $cashierSession = $this->sessionFor($this->cashier);
        $otherCashier = User::factory()->create(['role_id' => $this->cashier->role_id]);

        $adminBefore = $this->poll($adminSession)->json('signature');
        $cashierBefore = $this->poll($cashierSession)->json('signature');

        // Another cashier's request: the admin sees it, this cashier doesn't.
        $this->makeReturnFor($otherCashier);
        $adminAfterOther = $this->poll($adminSession)->json('signature');
        $this->assertNotSame($adminBefore, $adminAfterOther);
        $this->assertSame($cashierBefore, $this->poll($cashierSession)->json('signature'));

        // The admin approves this cashier's request: the cashier sees it.
        $return = $this->makeReturnFor($this->cashier);
        $cashierPending = $this->poll($cashierSession)->json('signature');
        $this->assertNotSame($cashierBefore, $cashierPending);

        $this->travel(1)->minutes();
        $return->update(['Status' => 'approved']);
        $this->assertNotSame($cashierPending, $this->poll($cashierSession)->json('signature'));
    }

    public function test_polling_does_not_count_as_activity(): void
    {
        $session = $this->sessionFor($this->admin, now()->subMinutes(10)->timestamp);

        $this->poll($session)->assertOk();

        $this->assertSame(now()->subMinutes(10)->timestamp, DB::table('sessions')->where('id', $session)->value('last_activity'));
    }

    public function test_it_refuses_unknown_expired_or_replaced_sessions(): void
    {
        $this->getJson(route('live.signature'))->assertUnauthorized();
        $this->withCredentials()->withCookie(config('session.cookie'), Str::random(40))->getJson(route('live.signature'))->assertUnauthorized();

        // (Tests use the array session driver; the live site stores sessions in the database.)
        config(['session.driver' => 'database']);
        $expired = $this->sessionFor($this->admin, now()->subMinutes(config('session.lifetime') + 5)->timestamp);
        $this->poll($expired)->assertUnauthorized();

        // Signed in again somewhere else: the old browser's cookie no longer counts.
        $old = $this->sessionFor($this->cashier);
        $this->sessionFor($this->cashier);
        $this->poll($old)->assertUnauthorized();
    }

    public function test_pages_mark_their_live_parts_and_start_from_the_current_state(): void
    {
        $this->notify($this->admin, 'Hello');
        $state = LiveUpdatesController::stateFor($this->admin);

        $this->actingAs($this->admin)->get(route('admin.sales-returns.index'))->assertOk()
            ->assertSee('data-live-region="returns-tabs"', false)
            ->assertSee('data-live-region="returns-table"', false)
            ->assertSee('data-live-region="returns-pagination"', false)
            ->assertSee('data-live-region="admin-notif-badge"', false)
            ->assertSee('data-live-region="admin-notif-items"', false)
            ->assertSee('"signature":"'.$state['signature'].'"', false)
            ->assertSee('const POLL_MS = 5000;', false);
        $this->actingAs($this->admin)->get(route('admin.notifications.index'))->assertOk()
            ->assertSee('data-live-region="notifications-list"', false);

        foreach (['cashier.pos', 'cashier.transactions', 'cashier.refunds', 'cashier.notifications.index'] as $page) {
            $this->actingAs($this->cashier)->get(route($page))->assertOk()
                ->assertSee('data-live-region="cashier-notif-bell"', false)
                ->assertSee('const signatureUrl = '.json_encode(route('live.signature')).';', false);
        }
        $this->actingAs($this->cashier)->get(route('cashier.refunds'))
            ->assertSee('data-live-region="refunds-rows"', false)
            ->assertSee("document.addEventListener('live-updates:applied'", false);
    }
}
