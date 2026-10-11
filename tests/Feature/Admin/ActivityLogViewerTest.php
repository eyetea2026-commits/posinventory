<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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

    public function test_admin_can_view_the_activity_log_with_readable_labels(): void
    {
        $this->log('purchase_order.approved', 'Approved PO-0001', $this->admin);

        $response = $this->actingAs($this->admin)->get(route('admin.activity-logs.index'));

        $response->assertOk();
        $response->assertSee('Approved PO-0001');
        $response->assertSee('Purchase Orders');
        $response->assertSee('Approved');
        $response->assertSee('Ana Admin');
    }

    public function test_cashier_cannot_view_the_activity_log(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('admin.activity-logs.index'));

        $this->assertContains($response->status(), [302, 403]);
        $response->assertDontSee('Activity Log');
    }

    public function test_guest_is_redirected_away(): void
    {
        $this->get(route('admin.activity-logs.index'))->assertRedirect();
    }

    public function test_search_filters_by_description(): void
    {
        $this->log('product.created', 'Added product "Dome Camera"', $this->admin);
        $this->log('product.created', 'Added product "Bullet Camera"', $this->admin);

        $response = $this->actingAs($this->admin)->get(route('admin.activity-logs.index', ['search' => 'Dome']));

        $response->assertSee('Dome Camera');
        $response->assertDontSee('Bullet Camera');
    }

    public function test_module_filter_matches_the_action_prefix_only(): void
    {
        $this->log('stock.adjusted', 'Adjusted stock row', $this->admin);
        $this->log('stock_receiving_batch.completed', 'Completed receiving batch', $this->admin);

        $response = $this->actingAs($this->admin)->get(route('admin.activity-logs.index', ['module' => 'stock']));

        $response->assertSee('Adjusted stock row');
        $response->assertDontSee('Completed receiving batch');
    }

    public function test_role_filter_isolates_admin_activity(): void
    {
        $this->log('user.updated', 'Admin edited a user', $this->admin);
        $this->log('return.requested', 'Cashier requested a refund', $this->cashier);

        $response = $this->actingAs($this->admin)->get(route('admin.activity-logs.index', ['role' => 'admin']));

        $response->assertSee('Admin edited a user');
        $response->assertDontSee('Cashier requested a refund');
    }

    public function test_user_filter_shows_only_that_users_actions(): void
    {
        $this->log('user.updated', 'Admin edited a user', $this->admin);
        $this->log('return.requested', 'Cashier requested a refund', $this->cashier);

        $response = $this->actingAs($this->admin)->get(route('admin.activity-logs.index', ['user_id' => $this->cashier->id]));

        $response->assertSee('Cashier requested a refund');
        $response->assertDontSee('Admin edited a user');
    }

    public function test_date_range_filter_is_inclusive(): void
    {
        $this->log('auth.login', 'Old login', $this->admin, '-10 days');
        $this->log('auth.login', 'Recent login', $this->admin, '-1 day');

        $response = $this->actingAs($this->admin)->get(route('admin.activity-logs.index', [
            'date_from' => now()->subDays(2)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertSee('Recent login');
        $response->assertDontSee('Old login');
    }

    // A bad range must not 302 back to itself (a redirect loop when the bad
    // query string IS the page) -- it renders, drops the bad bound, and says why.
    public function test_inverted_date_range_renders_with_an_error_instead_of_redirecting(): void
    {
        $this->log('auth.login', 'Some login', $this->admin);

        $response = $this->actingAs($this->admin)->get(route('admin.activity-logs.index', [
            'date_from' => now()->toDateString(),
            'date_to' => now()->subDays(5)->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('cannot be earlier than', false);
        $response->assertSee('Some login');
    }

    public function test_entries_without_a_user_show_as_system(): void
    {
        $this->log('auth.login_failed', 'Failed login for unknown username', null);

        $response = $this->actingAs($this->admin)->get(route('admin.activity-logs.index'));

        $response->assertSee('System / Unknown');
    }

    public function test_newest_entries_are_listed_first(): void
    {
        $this->log('auth.login', 'First entry', $this->admin, '-2 hours');
        $this->log('auth.login', 'Second entry', $this->admin, '-1 hour');

        $response = $this->actingAs($this->admin)->get(route('admin.activity-logs.index'));

        $response->assertSeeInOrder(['Second entry', 'First entry']);
    }

    public function test_sidebar_links_to_the_activity_log(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.activity-logs.index'));

        $response->assertSee(route('admin.activity-logs.index'), false);
        $response->assertSee('Activity Log');
    }
}
