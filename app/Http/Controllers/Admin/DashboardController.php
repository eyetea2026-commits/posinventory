<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Models\Supplier;
use App\Services\ReorderPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        try {
            ReorderPoint::refreshAllOncePerDay();
        } catch (Throwable $e) {
            Log::error('Re-Order Point refresh failed: '.$e->getMessage());
        }

        $today = today();
        $yesterday = $today->copy()->subDay();

        $salesToday = (float) SalesTransaction::whereDate('SalesTransactionDate', $today)->sum('TotalAmount');
        $salesYesterday = (float) SalesTransaction::whereDate('SalesTransactionDate', $yesterday)->sum('TotalAmount');
        $salesChangePct = $salesYesterday > 0
            ? round((($salesToday - $salesYesterday) / $salesYesterday) * 100, 1)
            : null;

        $transactionsToday = SalesTransaction::whereDate('SalesTransactionDate', $today)->count();
        $totalSuppliers = Supplier::count();

        $inventorySnapshot = $this->buildInventorySnapshot();
        $totalProducts = $inventorySnapshot['totalProducts'];
        $inventoryStatusChart = $inventorySnapshot['inventoryStatusChart'];
        $stockAlerts = $inventorySnapshot['stockAlerts'];

        $salesTrend = [
            'daily' => array_merge($this->dailyTrend(14), $this->periodStats('daily')),
            'weekly' => array_merge($this->weeklyTrend(8), $this->periodStats('weekly')),
            'monthly' => array_merge($this->monthlyTrend(12), $this->periodStats('monthly')),
            'yearly' => array_merge($this->yearlyTrend(5), $this->periodStats('yearly')),
        ];

        $categoryChart = $this->buildCategorySalesChart();

        ['topSelling' => $topSelling, 'leastSelling' => $leastSelling] = $this->buildProductRankings();

        $txnSearch = $request->query('txn_search');
        $txnSort = $request->query('txn_sort', 'date_desc');
        $recentTransactions = $this->buildRecentTransactions($request);

        return view('admin.dashboard', [
            'salesToday' => $salesToday,
            'salesChangePct' => $salesChangePct,
            'transactionsToday' => $transactionsToday,
            'totalProducts' => $totalProducts,
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

    public function liveInventory(Request $request)
    {
        $snapshot = $this->buildInventorySnapshot();

        $today = today();
        $yesterday = $today->copy()->subDay();
        $salesToday = (float) SalesTransaction::whereDate('SalesTransactionDate', $today)->sum('TotalAmount');
        $salesYesterday = (float) SalesTransaction::whereDate('SalesTransactionDate', $yesterday)->sum('TotalAmount');
        $salesChangePct = $salesYesterday > 0
            ? round((($salesToday - $salesYesterday) / $salesYesterday) * 100, 1)
            : null;

        $recentTransactions = $this->buildRecentTransactions($request);
        ['topSelling' => $topSelling, 'leastSelling' => $leastSelling] = $this->buildProductRankings();

        return response()->json([
            'totalProducts' => $snapshot['totalProducts'],
            'inventoryStatusChart' => $snapshot['inventoryStatusChart'],
            'stockAlertsHtml' => view('admin.dashboard.partials.stock-alerts', ['stockAlerts' => $snapshot['stockAlerts']])->render(),
            'categoryChart' => $this->buildCategorySalesChart(),
            'salesToday' => $salesToday,
            'salesChangePct' => $salesChangePct,
            'transactionsToday' => SalesTransaction::whereDate('SalesTransactionDate', $today)->count(),
            'recentTransactionsHtml' => view('admin.dashboard.partials.recent-transactions', [
                'recentTransactions' => $recentTransactions,
                'txnSort' => $request->query('txn_sort', 'date_desc'),
            ])->render(),
            'topSelling' => $topSelling->map(fn ($i) => ['label' => $i->product?->ProductName ?? 'Unknown', 'quantity' => (float) $i->total_quantity, 'revenue' => (float) $i->total_revenue]),
            'leastSelling' => $leastSelling->map(fn ($i) => ['label' => $i->product?->ProductName ?? 'Unknown', 'quantity' => (float) $i->total_quantity, 'revenue' => (float) $i->total_revenue]),
        ]);
    }

    private function buildRecentTransactions(Request $request)
    {
        $txnSearch = $request->query('txn_search');
        $txnSort = $request->query('txn_sort', 'date_desc');

        return SalesTransaction::query()
            ->select('SalesTransaction.*')
            ->with(['staff.user', 'payment'])
            ->when($txnSearch, function ($query, $search) {
                $search = trim($search);
                $receiptId = preg_match('/^(?:r(?:c(?:t)?)?)?-?\s*0*(\d+)$/i', $search, $m) ? (int) $m[1] : null;

                $query->where(function ($inner) use ($search, $receiptId) {
                    $inner->where('SalesTransaction.SalesTransactionID', 'like', "%{$search}%")
                        ->when($receiptId, fn ($q) => $q->orWhere('SalesTransaction.SalesTransactionID', $receiptId))
                        ->orWhereHas('payment', fn ($payment) => $payment->where('ReceiptNumber', 'like', "%{$search}%"))
                        ->orWhere('SalesTransaction.CustomerName', 'like', "%{$search}%")
                        ->orWhereHas('staff', function ($staff) use ($search) {
                            $staff->where('FirstName', 'like', "%{$search}%")
                                ->orWhere('LastName', 'like', "%{$search}%");
                        });
                });
            })
            ->when($txnSort === 'amount_desc', fn ($q) => $q->orderByDesc('SalesTransaction.TotalAmount'))
            ->when($txnSort === 'amount_asc', fn ($q) => $q->orderBy('SalesTransaction.TotalAmount'))
            ->when($txnSort === 'date_asc', fn ($q) => $q->orderBy('SalesTransaction.SalesTransactionDate'))
            ->when(! in_array($txnSort, ['amount_desc', 'amount_asc', 'date_asc']), fn ($q) => $q->orderByDesc('SalesTransaction.SalesTransactionDate'))
            ->paginate(10, ['*'], 'txn_page')
            ->withPath(route('admin.dashboard'))
            ->withQueryString();
    }

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

    private function buildProductRankings(): array
    {
        $sellingBase = SalesItem::select(
            'ProductID',
            DB::raw('SUM(BaseQuantity) as total_quantity'),
            DB::raw('SUM(Quantity * UnitPrice) as total_revenue')
        )->groupBy('ProductID');

        $soldProductCount = (clone $sellingBase)->get()->count();
        if ($soldProductCount <= 1) {
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
            $categoryData[] = round((float) $row->qty, 2);
        }
        $othersQty = round((float) $byCategoryRows->slice(7)->sum('qty'), 2);
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
                'data' => $byProductRows->pluck('qty')->map(fn ($q) => round((float) $q, 2))->toArray(),
            ],
        ];
    }

    private function buildInventorySnapshot(): array
    {
        $totalProducts = Product::count();
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

        return compact('totalProducts', 'inventoryStatusChart', 'stockAlerts');
    }

    private function dailyTrend(int $days): array
    {
        $end = today();
        $start = $end->copy()->subDays($days - 1);
        $compareEnd = $start->copy()->subDay();
        $compareStart = $compareEnd->copy()->subDays($days - 1);

        $rows = $this->trendWindow($start, $end, 'DATE(SalesTransactionDate)', 'd');
        $compareRows = $this->trendWindow($compareStart, $compareEnd, 'DATE(SalesTransactionDate)', 'd');

        $labels = [];
        $data = [];
        $compareLabels = [];
        $compareData = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $labels[] = $date->format('M d');
            $data[] = (float) ($rows[$date->format('Y-m-d')] ?? 0);

            $compareDate = $compareStart->copy()->addDays($i);
            $compareLabels[] = $compareDate->format('M d');
            $compareData[] = (float) ($compareRows[$compareDate->format('Y-m-d')] ?? 0);
        }

        return $this->assembleTrend($labels, $data, $compareLabels, $compareData, $start, $end, $compareStart, $compareEnd);
    }

    private function weeklyTrend(int $weeks): array
    {
        $start = today()->subWeeks($weeks - 1)->startOfWeek();
        $end = today();
        $compareEnd = $start->copy()->subDay();
        $compareStart = $compareEnd->copy()->subWeeks($weeks - 1)->startOfWeek();

        $rows = $this->trendWindow($start, $end, 'YEARWEEK(SalesTransactionDate, 3)', 'yw');
        $compareRows = $this->trendWindow($compareStart, $compareEnd, 'YEARWEEK(SalesTransactionDate, 3)', 'yw');

        $labels = [];
        $data = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $weekStart = today()->subWeeks($i)->startOfWeek();
            $key = (int) $weekStart->format('oW');
            $labels[] = 'Wk of '.$weekStart->format('M d');
            $data[] = (float) ($rows[$key] ?? 0);
        }

        $compareLabels = [];
        $compareData = [];
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $compareStart->copy()->addWeeks($i);
            $key = (int) $weekStart->format('oW');
            $compareLabels[] = 'Wk of '.$weekStart->format('M d');
            $compareData[] = (float) ($compareRows[$key] ?? 0);
        }

        return $this->assembleTrend($labels, $data, $compareLabels, $compareData, $start, $end, $compareStart, $compareEnd);
    }

    private function monthlyTrend(int $months): array
    {
        $start = today()->subMonthsNoOverflow($months - 1)->startOfMonth();
        $end = today();
        $compareEnd = $start->copy()->subDay();
        $compareStart = $compareEnd->copy()->subMonthsNoOverflow($months - 1)->startOfMonth();

        $rows = $this->trendWindow($start, $end, "DATE_FORMAT(SalesTransactionDate, '%Y-%m')", 'ym');
        $compareRows = $this->trendWindow($compareStart, $compareEnd, "DATE_FORMAT(SalesTransactionDate, '%Y-%m')", 'ym');

        $labels = [];
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = today()->subMonthsNoOverflow($i);
            $labels[] = $month->format('M Y');
            $data[] = (float) ($rows[$month->format('Y-m')] ?? 0);
        }

        $compareLabels = [];
        $compareData = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $compareStart->copy()->addMonthsNoOverflow($i);
            $compareLabels[] = $month->format('M Y');
            $compareData[] = (float) ($compareRows[$month->format('Y-m')] ?? 0);
        }

        return $this->assembleTrend($labels, $data, $compareLabels, $compareData, $start, $end, $compareStart, $compareEnd);
    }

    private function yearlyTrend(int $years): array
    {
        $start = today()->subYears($years - 1)->startOfYear();
        $end = today();
        $compareEnd = $start->copy()->subDay();
        $compareStart = $compareEnd->copy()->subYears($years - 1)->startOfYear();

        $rows = $this->trendWindow($start, $end, 'YEAR(SalesTransactionDate)', 'y');
        $compareRows = $this->trendWindow($compareStart, $compareEnd, 'YEAR(SalesTransactionDate)', 'y');

        $labels = [];
        $data = [];
        for ($i = $years - 1; $i >= 0; $i--) {
            $year = today()->subYears($i)->year;
            $labels[] = (string) $year;
            $data[] = (float) ($rows[$year] ?? 0);
        }

        $compareLabels = [];
        $compareData = [];
        for ($i = 0; $i < $years; $i++) {
            $year = $compareStart->copy()->addYears($i)->year;
            $compareLabels[] = (string) $year;
            $compareData[] = (float) ($compareRows[$year] ?? 0);
        }

        return $this->assembleTrend($labels, $data, $compareLabels, $compareData, $start, $end, $compareStart, $compareEnd);
    }

    private function trendWindow($start, $end, string $groupRaw, string $groupAlias)
    {
        return SalesTransaction::whereDate('SalesTransactionDate', '>=', $start->toDateString())
            ->whereDate('SalesTransactionDate', '<=', $end->toDateString())
            ->selectRaw("{$groupRaw} as {$groupAlias}, SUM(TotalAmount) as total")
            ->groupBy($groupAlias)
            ->pluck('total', $groupAlias);
    }

    private function assembleTrend(array $labels, array $data, array $compareLabels, array $compareData, $start, $end, $compareStart, $compareEnd): array
    {
        return [
            'labels' => $labels,
            'data' => $data,
            'compareLabels' => $compareLabels,
            'compareData' => $compareData,
            'currentRangeLabel' => $start->format('m/d/Y').' - '.$end->format('m/d/Y'),
            'compareRangeLabel' => $compareStart->format('m/d/Y').' - '.$compareEnd->format('m/d/Y'),
        ];
    }

    private function periodStats(string $granularity): array
    {
        $today = today();

        [$start, $previousStart, $previousEnd, $periodLabel, $compareLabel] = match ($granularity) {
            'weekly' => [
                $today->copy()->startOfWeek(),
                $today->copy()->subWeek()->startOfWeek(),
                $today->copy()->subWeek()->endOfWeek(),
                'This Week · '.$today->copy()->startOfWeek()->format('m/d/Y').' – '.$today->format('m/d/Y'),
                'vs last week',
            ],
            'monthly' => [
                $today->copy()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth(),
                'This Month · '.$today->format('F Y'),
                'vs last month',
            ],
            'yearly' => [
                $today->copy()->startOfYear(),
                $today->copy()->subYear()->startOfYear(),
                $today->copy()->subYear()->endOfYear(),
                'This Year · '.$today->format('Y'),
                'vs last year',
            ],
            default => [
                $today->copy(),
                $today->copy()->subDay(),
                $today->copy()->subDay(),
                'Today · '.$today->format('m/d/Y'),
                'vs yesterday',
            ],
        };

        $current = SalesTransaction::whereDate('SalesTransactionDate', '>=', $start->toDateString())
            ->whereDate('SalesTransactionDate', '<=', $today->toDateString());
        $invoiceCount = (clone $current)->count();
        $revenueTotal = round((float) $current->sum('TotalAmount'), 2);
        $previousRevenue = round((float) SalesTransaction::whereDate('SalesTransactionDate', '>=', $previousStart->toDateString())->whereDate('SalesTransactionDate', '<=', $previousEnd->toDateString())->sum('TotalAmount'), 2);

        return [
            'invoiceCount' => $invoiceCount,
            'revenueTotal' => $revenueTotal,
            'changePct' => $previousRevenue > 0 ? round((($revenueTotal - $previousRevenue) / $previousRevenue) * 100, 1) : null,
            'periodLabel' => $periodLabel,
            'compareLabel' => $compareLabel,
        ];
    }
}
