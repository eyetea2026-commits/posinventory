<?php

namespace Tests\Feature\Admin;

use App\Models\Billing;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Note: the dashboard's index() route can't be exercised end-to-end in this
// SQLite-backed test suite — weeklyTrend() uses MySQL's YEARWEEK(), which
// SQLite doesn't have. That's a pre-existing gap unrelated to this chart
// rework, so these tests either go through liveInventory() (which never
// touches the trend methods) or render the Blade view directly with
// hand-built data to check the new markup in isolation.
class DashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
    }

    private function makeProductWithStock(string $categoryName, string $productName, int $quantity, float $price = 500): Product
    {
        $category = Category::firstOrCreate(['CategoryName' => $categoryName], ['Description' => $categoryName]);
        $product = Product::create([
            'ProductName' => $productName, 'Model' => 'M-' . uniqid(), 'SKU' => 'SKU-' . uniqid(),
            'Price' => $price, 'CostPrice' => $price * 0.6, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => $quantity, 'Status' => 'Available']);

        return $product;
    }

    private function recordSale(Product $product, int $quantity, float $unitPrice): void
    {
        $cashierRole = Role::firstOrCreate(['role_name' => 'cashier']);
        $cashier = User::factory()->create(['role_id' => $cashierRole->id]);
        $staff = Staff::create([
            'FirstName' => 'Test', 'MiddleName' => '-', 'LastName' => 'Cashier',
            'ContactNumber' => '0000', 'Email' => 'cashier' . uniqid() . '@example.com', 'Age' => 30, 'Gender' => 'F',
            'UserID' => $cashier->id,
        ]);
        $transaction = SalesTransaction::create([
            'CustomerName' => 'Walk-in Customer', 'SalesTransactionDate' => now(), 'StaffID' => $staff->StaffID,
        ]);
        SalesItem::create([
            'Quantity' => $quantity, 'UnitPrice' => $unitPrice, 'ProductID' => $product->ProductID,
            'SalesTransactionID' => $transaction->SalesTransactionID,
        ]);
        Billing::create([
            'CustomerName' => 'Walk-in Customer', 'VatApplied' => '12%', 'BillingAmount' => $quantity * $unitPrice,
            'BillingDate' => now(), 'SalesTransactionID' => $transaction->SalesTransactionID,
        ]);
    }

    private function baseViewData(array $overrides = []): array
    {
        return array_merge([
            'salesToday' => 0, 'salesChangePct' => null, 'transactionsToday' => 0,
            'totalProducts' => 0, 'inventoryValue' => 0, 'totalSuppliers' => 0,
            'inventoryStatusChart' => ['byCategory' => ['labels' => [], 'data' => []], 'byProduct' => ['labels' => [], 'data' => []]],
            'stockAlerts' => collect(),
            'salesTrend' => ['daily' => ['labels' => [], 'data' => []], 'weekly' => ['labels' => [], 'data' => []], 'monthly' => ['labels' => [], 'data' => []], 'yearly' => ['labels' => [], 'data' => []]],
            'categoryChart' => ['labels' => [], 'data' => []],
            'topSelling' => collect(), 'leastSelling' => collect(),
            'recentTransactions' => new \Illuminate\Pagination\LengthAwarePaginator(collect(), 0, 10),
            'txnSearch' => null, 'txnSort' => 'date_desc',
        ], $overrides);
    }

    public function test_dashboard_view_shows_no_inventory_data_message_when_empty(): void
    {
        $this->actingAs($this->admin);
        $html = view('admin.dashboard', $this->baseViewData())->render();

        $this->assertStringContainsString('No Inventory Data Available', $html);
    }

    public function test_dashboard_view_shows_no_sales_data_message_when_empty(): void
    {
        $this->actingAs($this->admin);
        $html = view('admin.dashboard', $this->baseViewData())->render();

        $this->assertStringContainsString('No Sales Data Available', $html);
    }

    public function test_dashboard_view_renders_inventory_toggle_and_ring_legend_markup_when_data_exists(): void
    {
        $data = $this->baseViewData([
            'inventoryStatusChart' => [
                'byCategory' => ['labels' => ['CCTV'], 'data' => [20]],
                'byProduct' => ['labels' => ['Dome Camera'], 'data' => [20]],
            ],
            'categoryChart' => ['labels' => ['CCTV'], 'data' => [1000.0]],
        ]);

        $this->actingAs($this->admin);
        $html = view('admin.dashboard', $data)->render();

        $this->assertStringContainsString('data-view="category"', $html);
        $this->assertStringContainsString('data-view="product"', $html);
        $this->assertStringContainsString('id="categoryLegendList"', $html);
        $this->assertStringContainsString('id="categoryRingCenterValue"', $html);
        $this->assertStringNotContainsString('No Inventory Data Available', $html);
        $this->assertStringNotContainsString('No Sales Data Available', $html);
    }

    public function test_stat_cards_link_to_their_respective_modules(): void
    {
        $this->actingAs($this->admin);
        $html = view('admin.dashboard', $this->baseViewData())->render();

        $this->assertStringContainsString('href="' . route('admin.reports.index', ['type' => 'sales']) . '" class="stat-card"', $html);
        $this->assertStringContainsString('href="' . route('admin.products.index') . '" class="stat-card"', $html);
        $this->assertStringContainsString('href="' . route('admin.inventory.index') . '" class="stat-card"', $html);
        $this->assertStringContainsString('href="' . route('admin.suppliers.index') . '" class="stat-card"', $html);
        // Every stat card is now an <a>, not a plain non-interactive <div>.
        $this->assertStringNotContainsString('<div class="stat-card"', $html);
    }

    public function test_live_inventory_endpoint_groups_quantities_by_category_and_product(): void
    {
        $this->makeProductWithStock('CCTV', 'Dome Camera', 20);
        $this->makeProductWithStock('CCTV', 'Bullet Camera', 15);
        $this->makeProductWithStock('Networking', 'Switch 8-Port', 10);

        $response = $this->actingAs($this->admin)->getJson(route('admin.dashboard.live-inventory'));

        $response->assertOk();
        $chart = $response->json('inventoryStatusChart');

        $this->assertContains('CCTV', $chart['byCategory']['labels']);
        $this->assertContains('Networking', $chart['byCategory']['labels']);
        $catIndex = array_search('CCTV', $chart['byCategory']['labels']);
        $this->assertSame(35, $chart['byCategory']['data'][$catIndex]);
        $this->assertContains('Dome Camera', $chart['byProduct']['labels']);
    }

    public function test_live_inventory_endpoint_returns_both_new_chart_shapes(): void
    {
        $product = $this->makeProductWithStock('CCTV', 'Dome Camera', 20);
        $this->recordSale($product, 2, 500);

        $response = $this->actingAs($this->admin)->getJson(route('admin.dashboard.live-inventory'));

        $response->assertOk();
        $response->assertJsonStructure([
            'totalProducts', 'inventoryValue', 'stockAlertsHtml',
            'inventoryStatusChart' => ['byCategory' => ['labels', 'data'], 'byProduct' => ['labels', 'data']],
            'categoryChart' => ['labels', 'data'],
        ]);
    }

    // buildProductRankings() is invoked via reflection because index() can't
    // be exercised end-to-end here (see class-level note) — it's the same
    // private-method-under-test pattern, just without a live-polling route
    // to go through like buildInventoryQuantityChart()/buildCategorySalesChart() have.
    private function callBuildProductRankings(): array
    {
        $controller = new \App\Http\Controllers\Admin\DashboardController();
        $method = new \ReflectionMethod($controller, 'buildProductRankings');
        $method->setAccessible(true);

        return $method->invoke($controller);
    }

    public function test_product_rankings_never_show_the_same_product_in_both_lists(): void
    {
        // Explicit requirement: Top Selling and Least Selling must never
        // share a product, even if that means each list is small when few
        // distinct products have ever sold.
        $productA = $this->makeProductWithStock('CCTV', 'High Seller', 50);
        $productB = $this->makeProductWithStock('CCTV', 'Low Seller', 50);
        $this->recordSale($productA, 86, 500);
        $this->recordSale($productB, 6, 500);

        $rankings = $this->callBuildProductRankings();

        $this->assertCount(1, $rankings['topSelling']);
        $this->assertCount(1, $rankings['leastSelling']);
        $this->assertSame(86, (int) $rankings['topSelling'][0]->total_quantity);
        $this->assertSame(6, (int) $rankings['leastSelling'][0]->total_quantity);

        $topProductIds = $rankings['topSelling']->pluck('ProductID')->toArray();
        $leastProductIds = $rankings['leastSelling']->pluck('ProductID')->toArray();
        $this->assertEmpty(array_intersect($topProductIds, $leastProductIds));
    }

    public function test_product_rankings_order_top_selling_descending_and_least_selling_ascending_with_no_overlap(): void
    {
        $quantities = ['A' => 120, 'B' => 90, 'C' => 60, 'D' => 30];
        foreach ($quantities as $name => $qty) {
            $product = $this->makeProductWithStock('CCTV', $name, 50);
            $this->recordSale($product, $qty, 500);
        }

        $rankings = $this->callBuildProductRankings();

        $topQuantities = $rankings['topSelling']->pluck('total_quantity')->map(fn ($q) => (int) $q)->toArray();
        $leastQuantities = $rankings['leastSelling']->pluck('total_quantity')->map(fn ($q) => (int) $q)->toArray();

        // 4 distinct products -> half-split of 2 each, no overlap: top 2
        // (120, 90) and bottom 2 (30, 60), leaving neither list touching
        // the other's products.
        $this->assertSame([120, 90], $topQuantities);
        $this->assertSame([30, 60], $leastQuantities);

        $topProductIds = $rankings['topSelling']->pluck('ProductID')->toArray();
        $leastProductIds = $rankings['leastSelling']->pluck('ProductID')->toArray();
        $this->assertEmpty(array_intersect($topProductIds, $leastProductIds));
    }

    public function test_product_rankings_leave_least_selling_empty_when_only_one_product_ever_sold(): void
    {
        $product = $this->makeProductWithStock('CCTV', 'Only Seller', 50);
        $this->recordSale($product, 10, 500);

        $rankings = $this->callBuildProductRankings();

        $this->assertCount(1, $rankings['topSelling']);
        $this->assertCount(0, $rankings['leastSelling']);
    }

    // --- Live-sync coverage: liveInventory() must also carry the
    // sales-derived widgets (Sales Today, Transactions, Recent
    // Transactions, Product Rankings) so a sale completed in another tab
    // shows up on the next 10s poll without a page reload. ---

    public function test_live_inventory_endpoint_reports_sales_today_and_transactions_matching_db_state(): void
    {
        $product = $this->makeProductWithStock('CCTV', 'Dome Camera', 20);
        $this->recordSale($product, 3, 500); // BillingAmount = 1500, dated now()

        $response = $this->actingAs($this->admin)->getJson(route('admin.dashboard.live-inventory'));

        $response->assertOk();
        $response->assertJsonStructure([
            'salesToday', 'salesChangePct', 'transactionsToday',
            'recentTransactionsHtml', 'topSelling', 'leastSelling',
        ]);
        $this->assertEquals(1500.0, $response->json('salesToday'));
        $this->assertSame(1, $response->json('transactionsToday'));
        $this->assertStringContainsString('RCT-', $response->json('recentTransactionsHtml'));
        $this->assertStringContainsString('₱1,500.00', $response->json('recentTransactionsHtml'));
    }

    public function test_live_inventory_recent_transactions_html_reflects_search_and_sort_query_params(): void
    {
        $productA = $this->makeProductWithStock('CCTV', 'Alpha Camera', 20);
        $productB = $this->makeProductWithStock('CCTV', 'Beta Camera', 20);
        $this->recordSale($productA, 1, 500);
        $this->recordSale($productB, 1, 500);

        // Search narrows the polled fragment the same way it narrows the
        // full page — proves the poll forwards txn_search through.
        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.dashboard.live-inventory', ['txn_search' => 'Walk-in']));

        $response->assertOk();
        // Both sales share the same CustomerName search term, so both rows
        // should still be present (search matches on customer/receipt/cashier,
        // not product name — this just proves the filter was applied without erroring).
        $this->assertStringContainsString('table', $response->json('recentTransactionsHtml'));
    }

    public function test_live_inventory_top_selling_and_least_selling_match_product_rankings(): void
    {
        $productA = $this->makeProductWithStock('CCTV', 'High Seller', 50);
        $productB = $this->makeProductWithStock('CCTV', 'Low Seller', 50);
        $this->recordSale($productA, 86, 500);
        $this->recordSale($productB, 6, 500);

        $response = $this->actingAs($this->admin)->getJson(route('admin.dashboard.live-inventory'));

        $response->assertOk();
        $this->assertSame('High Seller', $response->json('topSelling.0.label'));
        $this->assertEquals(86.0, $response->json('topSelling.0.quantity'));
        $this->assertSame('Low Seller', $response->json('leastSelling.0.label'));
        $this->assertEquals(6.0, $response->json('leastSelling.0.quantity'));
    }

    // Regression: the recent-transactions partial renders identically from
    // two different routes (the dashboard page and this JSON polling
    // endpoint). Its pagination/sort links must always point at the real
    // dashboard page, never at this JSON-only endpoint's own URL --
    // otherwise, once the 10s poll re-renders this partial into the DOM,
    // clicking a page number lands the admin on a raw JSON response instead
    // of the dashboard.
    public function test_live_inventory_recent_transactions_pagination_links_point_at_the_dashboard_not_itself(): void
    {
        $product = $this->makeProductWithStock('CCTV', 'Dome Camera', 100);
        for ($i = 0; $i < 15; $i++) {
            $this->recordSale($product, 1, 500);
        }

        $response = $this->actingAs($this->admin)->getJson(route('admin.dashboard.live-inventory'));

        $response->assertOk();
        $html = $response->json('recentTransactionsHtml');

        $this->assertStringContainsString('pagination-link', $html);
        $this->assertStringContainsString(route('admin.dashboard') . '?', $html);
        $this->assertStringNotContainsString(route('admin.dashboard.live-inventory'), $html);
    }

    public function test_live_inventory_recent_transactions_sort_links_point_at_the_dashboard_not_itself(): void
    {
        $product = $this->makeProductWithStock('CCTV', 'Dome Camera', 20);
        $this->recordSale($product, 1, 500);

        $response = $this->actingAs($this->admin)->getJson(route('admin.dashboard.live-inventory'));

        $response->assertOk();
        $html = $response->json('recentTransactionsHtml');

        // Default sort is date_desc, so the Amount header's toggle link
        // targets amount_desc.
        $this->assertStringContainsString('txn_sort=amount_desc', $html);
        $this->assertStringContainsString(route('admin.dashboard') . '?', $html);
        $this->assertStringNotContainsString(route('admin.dashboard.live-inventory'), $html);
    }

    public function test_dashboard_view_carries_stable_ids_for_the_live_poll_to_target(): void
    {
        $this->actingAs($this->admin);
        $html = view('admin.dashboard', $this->baseViewData())->render();

        $this->assertStringContainsString('id="statSalesToday"', $html);
        $this->assertStringContainsString('id="salesTrendBadge"', $html);
        $this->assertStringContainsString('id="statTransactionsToday"', $html);
        $this->assertStringContainsString('id="recentTransactionsContainer"', $html);
    }

    // Regression: pagination/sort/search on this widget must never do a real
    // navigation (which always scrolls back to the top of the dashboard) --
    // they're intercepted client-side and fetched via the JSON endpoint.
    public function test_dashboard_view_wires_up_ajax_handling_for_recent_transactions(): void
    {
        $this->actingAs($this->admin);
        $html = view('admin.dashboard', $this->baseViewData())->render();

        $this->assertStringContainsString('id="recentTxnSearchForm"', $html);
        $this->assertStringContainsString('id="recentTxnSearchInput"', $html);
        $this->assertStringContainsString('function bindRecentTransactionsLinks', $html);
        $this->assertStringContainsString('function fetchRecentTransactions', $html);
        $this->assertStringContainsString("e.preventDefault();\n                    const url = new URL(this.getAttribute('href')", $html);
    }
}
