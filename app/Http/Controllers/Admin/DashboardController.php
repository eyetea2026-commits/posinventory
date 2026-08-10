<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (! auth()->user() || ! auth()->user()->isAdmin()) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $today = today();
        $yesterday = $today->copy()->subDay();

        // KPI cards
        $salesToday = (float) Billing::whereDate('BillingDate', $today)->sum('BillingAmount');
        $salesYesterday = (float) Billing::whereDate('BillingDate', $yesterday)->sum('BillingAmount');
        $salesChangePct = $salesYesterday > 0
            ? round((($salesToday - $salesYesterday) / $salesYesterday) * 100, 1)
            : null;

        $transactionsToday = Billing::whereDate('BillingDate', $today)->count();
        $totalSuppliers = Supplier::count();

        $inventorySnapshot = $this->buildInventorySnapshot();
        $totalProducts = $inventorySnapshot['totalProducts'];
        $inventoryValue = $inventorySnapshot['inventoryValue'];
        $inventoryStatusChart = $inventorySnapshot['inventoryStatusChart'];
        $stockAlerts = $inventorySnapshot['stockAlerts'];

        // Sales trend — all four granularities pre-computed, gap-filled, so
        // the client can switch datasets with no server round-trip.
        $salesTrend = [
            'daily' => $this->dailyTrend(14),
            'weekly' => $this->weeklyTrend(8),
            'monthly' => $this->monthlyTrend(12),
            'yearly' => $this->yearlyTrend(5),
        ];

        // Sales by category — top 6 by revenue, remainder folded into "Others"
        $categoryChart = $this->buildCategorySalesChart();

        // Top / least selling products — see buildProductRankings() for why
        // each side is an independent top/bottom N rather than a strict
        // non-overlapping half-split.
        ['topSelling' => $topSelling, 'leastSelling' => $leastSelling] = $this->buildProductRankings();

        // Recent transactions — searchable/sortable/paginated in place,
        // namespaced query params so it doesn't collide with any other
        // paginated widget on this page.
        $txnSearch = $request->query('txn_search');
        $txnSort = $request->query('txn_sort', 'date_desc');
        $recentTransactions = $this->buildRecentTransactions($request);

        return view('admin.dashboard', [
            'salesToday' => $salesToday,
            'salesChangePct' => $salesChangePct,
            'transactionsToday' => $transactionsToday,
            'totalProducts' => $totalProducts,
            'inventoryValue' => $inventoryValue,
            'totalSuppliers' => $totalSuppliers,
            'inventoryStatusChart' => $inventoryStatusChart,
            'stockAlerts' => $stockAlerts,
            'salesTrend' => $salesTrend,
            'categoryChart' => $categoryChart,
            'topSelling' => $topSelling,
            'leastSelling' => $leastSelling,
            'recentTransactions' => $recentTransactions,
            'txnSearch' => $txnSearch,
            'txnSort' => $txnSort,
        ]);
    }

    /**
     * Polled by the dashboard so inventory- and sales-derived widgets
     * (Products count, Inventory Value, Inventory Status chart, Stock
     * Alerts, Sales Today, Transactions, Recent Transactions, Product
     * Rankings) update without a page reload whenever a sale/refund/
     * receiving/adjustment/damage record changes elsewhere — including from
     * another cashier's tab. The Sales Trend chart is deliberately excluded
     * (historical/comparative view, not "right now", and would multiply
     * this poll's query cost by 4 for no visible benefit).
     */
    public function liveInventory(Request $request)
    {
        $snapshot = $this->buildInventorySnapshot();

        $today = today();
        $yesterday = $today->copy()->subDay();
        $salesToday = (float) Billing::whereDate('BillingDate', $today)->sum('BillingAmount');
        $salesYesterday = (float) Billing::whereDate('BillingDate', $yesterday)->sum('BillingAmount');
        $salesChangePct = $salesYesterday > 0
            ? round((($salesToday - $salesYesterday) / $salesYesterday) * 100, 1)
            : null;

        $recentTransactions = $this->buildRecentTransactions($request);
        ['topSelling' => $topSelling, 'leastSelling' => $leastSelling] = $this->buildProductRankings();

        return response()->json([
            'totalProducts' => $snapshot['totalProducts'],
            'inventoryValue' => $snapshot['inventoryValue'],
            'inventoryStatusChart' => $snapshot['inventoryStatusChart'],
            'stockAlertsHtml' => view('admin.dashboard.partials.stock-alerts', ['stockAlerts' => $snapshot['stockAlerts']])->render(),
            'categoryChart' => $this->buildCategorySalesChart(),
            'salesToday' => $salesToday,
            'salesChangePct' => $salesChangePct,
            'transactionsToday' => Billing::whereDate('BillingDate', $today)->count(),
            'recentTransactionsHtml' => view('admin.dashboard.partials.recent-transactions', [
                'recentTransactions' => $recentTransactions,
                'txnSort' => $request->query('txn_sort', 'date_desc'),
            ])->render(),
            'topSelling' => $topSelling->map(fn ($i) => ['label' => $i->product?->ProductName ?? 'Unknown', 'quantity' => (float) $i->total_quantity, 'revenue' => (float) $i->total_revenue]),
            'leastSelling' => $leastSelling->map(fn ($i) => ['label' => $i->product?->ProductName ?? 'Unknown', 'quantity' => (float) $i->total_quantity, 'revenue' => (float) $i->total_revenue]),
        ]);
    }

    /**
     * Shared by the initial page load and the live-polling endpoint so
     * Recent Transactions stays in sync with the same query/filter/sort
     * logic either way.
     */
    private function buildRecentTransactions(Request $request)
    {
        $txnSearch = $request->query('txn_search');
        $txnSort = $request->query('txn_sort', 'date_desc');

        return SalesTransaction::query()
            ->select('SalesTransaction.*')
            ->leftJoin('Billing', 'Billing.SalesTransactionID', '=', 'SalesTransaction.SalesTransactionID')
            ->with(['staff.user', 'billing.payment'])
            ->when($txnSearch, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('SalesTransaction.SalesTransactionID', 'like', "%{$search}%")
                        ->orWhere('SalesTransaction.CustomerName', 'like', "%{$search}%")
                        ->orWhereHas('staff', function ($staff) use ($search) {
                            $staff->where('FirstName', 'like', "%{$search}%")
                                ->orWhere('LastName', 'like', "%{$search}%");
                        });
                });
            })
            ->when($txnSort === 'amount_desc', fn ($q) => $q->orderByDesc('Billing.BillingAmount'))
            ->when($txnSort === 'amount_asc', fn ($q) => $q->orderBy('Billing.BillingAmount'))
            ->when($txnSort === 'date_asc', fn ($q) => $q->orderBy('SalesTransaction.SalesTransactionDate'))
            ->when(! in_array($txnSort, ['amount_desc', 'amount_asc', 'date_asc']), fn ($q) => $q->orderByDesc('SalesTransaction.SalesTransactionDate'))
            ->paginate(10, ['*'], 'txn_page')
            ->withQueryString();
    }

    /**
     * Top 6 categories by revenue, remainder folded into "Others" — shared
     * by the initial page load and the live-polling endpoint so the Sales
     * by Category ring chart stays in sync with the same aggregation logic
     * either way.
     */
    private function buildCategorySalesChart(): array
    {
        $categorySales = SalesItem::join('Product', 'SalesItem.ProductID', '=', 'Product.ProductID')
            ->join('Category', 'Product.CategoryID', '=', 'Category.CategoryID')
            ->selectRaw('Category.CategoryName as name, SUM(SalesItem.Quantity * SalesItem.UnitPrice) as revenue')
            ->groupBy('Category.CategoryName')
            ->orderByDesc('revenue')
            ->get();

        $categoryLabels = [];
        $categoryData = [];
        foreach ($categorySales->take(6) as $cat) {
            $categoryLabels[] = $cat->name;
            $categoryData[] = (float) $cat->revenue;
        }
        $othersRevenue = (float) $categorySales->slice(6)->sum('revenue');
        if ($othersRevenue > 0) {
            $categoryLabels[] = 'Others';
            $categoryData[] = $othersRevenue;
        }

        return ['labels' => $categoryLabels, 'data' => $categoryData];
    }

    /**
     * Top / least selling products — both expose quantity and revenue so
     * the Product Ranking widget can toggle between the two client-side.
     * Cap each side at half the number of distinct sold products (max 10)
     * so Top and Least Selling never share a product — explicitly required
     * over the alternative (independent top-N/bottom-N, which lets a thin
     * catalog show the same products in both tabs reversed): a product
     * must never appear in both lists at once, even at the cost of each
     * list being small when few distinct products have ever sold.
     */
    private function buildProductRankings(): array
    {
        $sellingBase = SalesItem::select(
            'ProductID',
            DB::raw('SUM(Quantity) as total_quantity'),
            DB::raw('SUM(Quantity * UnitPrice) as total_revenue')
        )->groupBy('ProductID');

        $soldProductCount = (clone $sellingBase)->get()->count();
        if ($soldProductCount <= 1) {
            // Only one (or zero) distinct product has ever sold, so there's
            // nothing "least selling" that isn't also the top seller —
            // populate Top Selling only and leave Least Selling empty
            // instead of showing the same single product in both tabs.
            $topTake = $soldProductCount;
            $leastTake = 0;
        } else {
            $topTake = $leastTake = min(10, intdiv($soldProductCount, 2));
        }

        $topSelling = (clone $sellingBase)->orderByDesc('total_quantity')->take($topTake)->get()->load('product');
        $leastSelling = $leastTake > 0
            ? (clone $sellingBase)->orderBy('total_quantity')->take($leastTake)->get()->load('product')
            : collect();

        return ['topSelling' => $topSelling, 'leastSelling' => $leastSelling];
    }

    /**
     * Inventory quantities grouped by Category and by Product (top 10),
     * feeding the Inventory Status bar chart's By Category / By Product
     * toggle. Both are computed unconditionally (not just whichever view is
     * currently active) so the client can switch views instantly with no
     * server round-trip, matching the Sales Trend chart's daily/weekly/
     * monthly/yearly toggle pattern elsewhere on this page.
     */
    private function buildInventoryQuantityChart(): array
    {
        $byCategoryRows = Inventory::join('Product', 'Inventory.ProductID', '=', 'Product.ProductID')
            ->join('Category', 'Product.CategoryID', '=', 'Category.CategoryID')
            ->selectRaw('Category.CategoryName as name, SUM(Inventory.Quantity) as qty')
            ->groupBy('Category.CategoryName')
            ->orderByDesc('qty')
            ->get();

        $categoryLabels = [];
        $categoryData = [];
        foreach ($byCategoryRows->take(7) as $row) {
            $categoryLabels[] = $row->name;
            $categoryData[] = (int) $row->qty;
        }
        $othersQty = (int) $byCategoryRows->slice(7)->sum('qty');
        if ($othersQty > 0) {
            $categoryLabels[] = 'Others';
            $categoryData[] = $othersQty;
        }

        $byProductRows = Inventory::join('Product', 'Inventory.ProductID', '=', 'Product.ProductID')
            ->selectRaw('Product.ProductName as name, Inventory.Quantity as qty')
            ->orderByDesc('Inventory.Quantity')
            ->take(10)
            ->get();

        return [
            'byCategory' => ['labels' => $categoryLabels, 'data' => $categoryData],
            'byProduct' => [
                'labels' => $byProductRows->pluck('name')->toArray(),
                'data' => $byProductRows->pluck('qty')->map(fn ($q) => (int) $q)->toArray(),
            ],
        ];
    }

    /**
     * Computed live from Quantity vs. ReorderThreshold rather than the
     * stored Inventory.Status string, since that string uses inconsistent
     * vocabulary across write paths.
     */
    private function buildInventorySnapshot(): array
    {
        $totalProducts = Product::count();
        $inventoryValue = (float) Inventory::join('Product', 'Inventory.ProductID', '=', 'Product.ProductID')
            ->sum(DB::raw('Inventory.Quantity * Product.CostPrice'));

        $inventoryStatusChart = $this->buildInventoryQuantityChart();

        $stockAlerts = Inventory::with('product')
            ->whereColumn('Quantity', '<=', DB::raw('COALESCE(ReorderThreshold, 50)'))
            ->orderBy('Quantity')
            ->take(10)
            ->get()
            ->map(function ($row) {
                return [
                    'product' => $row->product,
                    'quantity' => $row->Quantity,
                    'status' => ProductController::resolveStockStatus($row->Quantity, $row->ReorderThreshold),
                ];
            });

        return compact('totalProducts', 'inventoryValue', 'inventoryStatusChart', 'stockAlerts');
    }

    private function dailyTrend(int $days): array
    {
        $start = today()->subDays($days - 1);
        $rows = Billing::where('BillingDate', '>=', $start)
            ->selectRaw('BillingDate as d, SUM(BillingAmount) as total')
            ->groupBy('BillingDate')
            ->pluck('total', 'd');

        $labels = [];
        $data = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $labels[] = $date->format('M d');
            $data[] = (float) ($rows[$date->format('Y-m-d')] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    private function weeklyTrend(int $weeks): array
    {
        $start = today()->subWeeks($weeks - 1)->startOfWeek();
        $rows = Billing::where('BillingDate', '>=', $start)
            ->selectRaw('YEARWEEK(BillingDate, 3) as yw, SUM(BillingAmount) as total')
            ->groupBy('yw')
            ->pluck('total', 'yw');

        $labels = [];
        $data = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $weekStart = today()->subWeeks($i)->startOfWeek();
            $key = (int) $weekStart->format('oW');
            $labels[] = 'Wk of ' . $weekStart->format('M d');
            $data[] = (float) ($rows[$key] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    private function monthlyTrend(int $months): array
    {
        $start = today()->subMonthsNoOverflow($months - 1)->startOfMonth();
        $rows = Billing::where('BillingDate', '>=', $start)
            ->selectRaw("DATE_FORMAT(BillingDate, '%Y-%m') as ym, SUM(BillingAmount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $labels = [];
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = today()->subMonthsNoOverflow($i);
            $labels[] = $month->format('M Y');
            $data[] = (float) ($rows[$month->format('Y-m')] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    private function yearlyTrend(int $years): array
    {
        $start = today()->subYears($years - 1)->startOfYear();
        $rows = Billing::where('BillingDate', '>=', $start)
            ->selectRaw('YEAR(BillingDate) as y, SUM(BillingAmount) as total')
            ->groupBy('y')
            ->pluck('total', 'y');

        $labels = [];
        $data = [];
        for ($i = $years - 1; $i >= 0; $i--) {
            $year = today()->subYears($i)->year;
            $labels[] = (string) $year;
            $data[] = (float) ($rows[$year] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }
}
