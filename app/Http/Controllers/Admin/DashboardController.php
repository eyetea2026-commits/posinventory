<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\DamagedProduct;
use App\Models\Inventory;
use App\Models\Payment;
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
        $categoryChart = ['labels' => $categoryLabels, 'data' => $categoryData];

        // Top / least selling products — both expose quantity and revenue so
        // the chart can toggle between the two client-side.
        $sellingBase = SalesItem::select(
            'ProductID',
            DB::raw('SUM(Quantity) as total_quantity'),
            DB::raw('SUM(Quantity * UnitPrice) as total_revenue')
        )->groupBy('ProductID');

        $topSelling = (clone $sellingBase)->orderByDesc('total_quantity')->take(10)->get()->load('product');
        $leastSelling = (clone $sellingBase)->orderBy('total_quantity')->take(10)->get()->load('product');

        // Payment methods — real accepted values only (cash/gcash/bank/cheque)
        $paymentMethods = Payment::selectRaw('PaymentMethod as method, COUNT(*) as cnt, SUM(PaymentAmount) as total')
            ->groupBy('PaymentMethod')
            ->orderByDesc('total')
            ->get();

        // Recent transactions — searchable/sortable/paginated in place,
        // namespaced query params so it doesn't collide with any other
        // paginated widget on this page.
        $txnSearch = $request->query('txn_search');
        $txnSort = $request->query('txn_sort', 'date_desc');

        $recentTransactions = SalesTransaction::query()
            ->select('SalesTransaction.*')
            ->leftJoin('Billing', 'Billing.SalesTransactionID', '=', 'SalesTransaction.SalesTransactionID')
            ->with(['staff', 'billing.payment'])
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
            'paymentMethods' => $paymentMethods,
            'recentTransactions' => $recentTransactions,
            'txnSearch' => $txnSearch,
            'txnSort' => $txnSort,
        ]);
    }

    /**
     * Polled by the dashboard so inventory-derived widgets (Products count,
     * Inventory Value, Inventory Status chart, Stock Alerts) update without
     * a page reload whenever a sale/refund/receiving/adjustment/damage
     * record changes stock elsewhere.
     */
    public function liveInventory()
    {
        $snapshot = $this->buildInventorySnapshot();

        return response()->json([
            'totalProducts' => $snapshot['totalProducts'],
            'inventoryValue' => $snapshot['inventoryValue'],
            'inventoryStatusChart' => $snapshot['inventoryStatusChart'],
            'stockAlertsHtml' => view('admin.dashboard.partials.stock-alerts', ['stockAlerts' => $snapshot['stockAlerts']])->render(),
        ]);
    }

    /**
     * Computed live from Quantity vs. ReorderThreshold rather than the
     * stored Inventory.Status string, since that string uses inconsistent
     * vocabulary across write paths. Damaged is supplementary info from
     * DamagedProduct, not a re-slice of the same Quantity total (damaged
     * units are already subtracted out of Inventory.Quantity by
     * DamageController).
     */
    private function buildInventorySnapshot(): array
    {
        $totalProducts = Product::count();
        $inventoryValue = (float) Inventory::join('Product', 'Inventory.ProductID', '=', 'Product.ProductID')
            ->sum(DB::raw('Inventory.Quantity * Product.CostPrice'));

        $totalInventoryRows = Inventory::count();
        $lowStockCount = Inventory::where('Quantity', '>', 0)
            ->whereColumn('Quantity', '<=', DB::raw('COALESCE(ReorderThreshold, 50)'))
            ->count();
        $outOfStockCount = Inventory::where('Quantity', '<=', 0)->count();
        $inStockCount = max(0, $totalInventoryRows - $lowStockCount - $outOfStockCount);
        $damagedCount30d = (int) DamagedProduct::where('DateRecorded', '>=', now()->subDays(30))->sum('Quantity');

        $inventoryStatusChart = [
            'labels' => ['In Stock', 'Low Stock', 'Out of Stock', 'Damaged (30d)'],
            'data' => [$inStockCount, $lowStockCount, $outOfStockCount, $damagedCount30d],
        ];

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
