<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

// The Activity Log lives as a card in the dashboard's Operational Activity
// section, beside Recent Transactions. The card loads its rows over AJAX from
// admin.activity-logs.index, with log_-prefixed filters.
class ActivityLogViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $cashierRole = Role::create(['role_name' => 'cashier']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id, 'first_name' => 'Ana', 'last_name' => 'Admin']);
        $this->cashier = User::factory()->create(['role_id' => $cashierRole->id, 'first_name' => 'Carl', 'last_name' => 'Cashier']);
    }

    private function log(string $action, string $description, ?User $user, string $when = 'now'): ActivityLog
    {
        return ActivityLog::create([
            'UserID' => $user?->id,
            'Action' => $action,
            'Description' => $description,
            'DateRecorded' => now()->parse($when),
        ]);
    }

    private function cardHtml(array $filters = []): string
    {
        return $this->actingAs($this->admin)->getJson(route('admin.activity-logs.index', $filters))->assertOk()->json('html');
    }

    public function test_card_shows_entries_with_readable_labels_and_filter_options(): void
    {
        $this->log('purchase_order.approved', 'Approved PO-0001', $this->admin);

        $response = $this->actingAs($this->admin)->getJson(route('admin.activity-logs.index'));

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('Approved PO-0001', $html);
        $this->assertStringContainsString('Purchase Orders', $html);
        $this->assertStringContainsString('Approved', $html);
        $this->assertStringContainsString('Ana Admin', $html);
        $response->assertJsonPath('modules.purchase_order', 'Purchase Orders');
        $response->assertJsonFragment(['id' => $this->admin->id, 'name' => 'Ana Admin']);
    }

    public function test_cashier_cannot_read_the_activity_log(): void
    {
        $response = $this->actingAs($this->cashier)->getJson(route('admin.activity-logs.index'));

        $this->assertContains($response->status(), [302, 403]);
        $this->assertStringNotContainsString('Activity Log', (string) $response->getContent());
    }

    public function test_guest_is_redirected_away(): void
    {
        $this->get(route('admin.activity-logs.index'))->assertRedirect();
    }

    public function test_the_old_page_address_now_opens_the_dashboard_card(): void
    {
        $this->actingAs($this->admin)->get(route('admin.activity-logs.index'))
            ->assertRedirect(route('admin.dashboard').'#activity-log');
    }

    public function test_search_filters_by_description(): void
    {
        $this->log('product.created', 'Added product "Dome Camera"', $this->admin);
        $this->log('product.created', 'Added product "Bullet Camera"', $this->admin);

        $html = $this->cardHtml(['log_search' => 'Dome']);

        $this->assertStringContainsString('Dome Camera', $html);
        $this->assertStringNotContainsString('Bullet Camera', $html);
    }

    public function test_module_filter_matches_the_action_prefix_only(): void
    {
        $this->log('stock.adjusted', 'Adjusted stock row', $this->admin);
        $this->log('stock_receiving_batch.completed', 'Completed receiving batch', $this->admin);

        $html = $this->cardHtml(['log_module' => 'stock']);

        $this->assertStringContainsString('Adjusted stock row', $html);
        $this->assertStringNotContainsString('Completed receiving batch', $html);
    }

    public function test_role_filter_isolates_admin_activity(): void
    {
        $this->log('user.updated', 'Admin edited a user', $this->admin);
        $this->log('return.requested', 'Cashier requested a refund', $this->cashier);

        $html = $this->cardHtml(['log_role' => 'admin']);

        $this->assertStringContainsString('Admin edited a user', $html);
        $this->assertStringNotContainsString('Cashier requested a refund', $html);
    }

    public function test_user_filter_shows_only_that_users_actions(): void
    {
        $this->log('user.updated', 'Admin edited a user', $this->admin);
        $this->log('return.requested', 'Cashier requested a refund', $this->cashier);

        $html = $this->cardHtml(['log_user' => $this->cashier->id]);

        $this->assertStringContainsString('Cashier requested a refund', $html);
        $this->assertStringNotContainsString('Admin edited a user', $html);
    }

    public function test_date_range_filter_is_inclusive(): void
    {
        $this->log('auth.login', 'Old login', $this->admin, '-10 days');
        $this->log('auth.login', 'Recent login', $this->admin, '-1 day');

        $html = $this->cardHtml(['log_from' => now()->subDays(2)->toDateString(), 'log_to' => now()->toDateString()]);

        $this->assertStringContainsString('Recent login', $html);
        $this->assertStringNotContainsString('Old login', $html);
    }

    public function test_inverted_date_range_drops_the_bad_bound_and_explains(): void
    {
        $this->log('auth.login', 'Some login', $this->admin);

        $response = $this->actingAs($this->admin)->getJson(route('admin.activity-logs.index', [
            'log_from' => now()->toDateString(),
            'log_to' => now()->subDays(5)->toDateString(),
        ]));

        $response->assertOk();
        $this->assertStringContainsString('cannot be earlier than', $response->json('filterError'));
        $this->assertStringContainsString('Some login', $response->json('html'));
    }

    public function test_entries_without_a_user_show_as_system(): void
    {
        $this->log('auth.login_failed', 'Failed login for unknown username', null);

        $this->assertStringContainsString('System / Unknown', $this->cardHtml());
    }

    public function test_newest_entries_are_listed_first_and_pages_use_log_page(): void
    {
        $this->log('auth.login', 'First entry', $this->admin, '-2 hours');
        $this->log('auth.login', 'Second entry', $this->admin, '-1 hour');
        foreach (range(1, 10) as $i) {
            $this->log('auth.login', "Filler {$i}", $this->admin, "-{$i} days");
        }

        $html = $this->cardHtml();

        $this->assertLessThan(strpos($html, 'First entry'), strpos($html, 'Second entry'));
        // Page links use their own parameter so they can't move the Recent
        // Transactions table's pages on the same dashboard, and always point
        // back at this endpoint.
        $this->assertStringContainsString(route('admin.activity-logs.index').'?log_page=2', $html);
        // Active filters are carried into the page links.
        $this->assertStringContainsString('log_module=auth', $this->cardHtml(['log_module' => 'auth']));
    }

    public function test_dashboard_has_the_card_beside_recent_transactions_and_no_sidebar_item(): void
    {
        $this->actingAs($this->admin);
        $html = view('admin.dashboard', [
            'salesToday' => 0, 'salesChangePct' => null, 'transactionsToday' => 0,
            'totalProducts' => 0, 'totalSuppliers' => 0,
            'inventoryStatusChart' => ['byCategory' => ['labels' => [], 'data' => []], 'byProduct' => ['labels' => [], 'data' => []]],
            'stockAlerts' => collect(),
            'salesTrend' => ['daily' => ['labels' => [], 'data' => []], 'weekly' => ['labels' => [], 'data' => []], 'monthly' => ['labels' => [], 'data' => []], 'yearly' => ['labels' => [], 'data' => []]],
            'categoryChart' => ['labels' => [], 'data' => []],
            'topSelling' => collect(), 'leastSelling' => collect(),
            'recentTransactions' => new LengthAwarePaginator(collect(), 0, 10),
            'txnSearch' => null, 'txnSort' => 'date_desc',
        ])->render();

        $recent = strpos($html, '<h2 class="card-title">Recent Transactions</h2>');
        $activity = strpos($html, '<h2 class="card-title">Activity Log</h2>');
        $this->assertNotFalse($activity);
        $this->assertGreaterThan($recent, $activity);
        // Both cards share one two-column grid row, so they sit side by side.
        $grid = strpos($html, '<div class="charts-grid operational-grid">');
        $this->assertNotFalse($grid);
        $this->assertLessThan($recent, $grid);
        $this->assertStringContainsString('id="activityLogContainer"', $html);
        $this->assertStringContainsString(route('admin.activity-logs.index'), $html);
        // No longer a separate sidebar page.
        $this->assertStringNotContainsString('<span>Activity Log</span>', $html);
    }
}
