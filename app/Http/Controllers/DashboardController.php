<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Models\StockReceiving;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        if ($user && $user->isCashier()) {
            return redirect()->route('cashier.pos');
        }
        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        // Computed live from Quantity vs. ReorderThreshold rather than the stored
        // Status string, since different write paths (Product form, Stock
        // Receiving/Adjustment, POS sale) persist different Status vocabularies.
        $totalInventory = Inventory::count();
        $lowStockCount = Inventory::where('Quantity', '>', 0)->whereColumn('Quantity', '<=', DB::raw('COALESCE(ReorderThreshold, 50)'))->count();
        $outOfStockCount = Inventory::where('Quantity', '<=', 0)->count();

        $stockStatus = [
            'available' => $totalInventory - $lowStockCount - $outOfStockCount,
            'low_stock' => $lowStockCount,
            'out_of_stock' => $outOfStockCount,
        ];

        // Sales metrics
        $totalSales = Billing::sum('BillingAmount');

        // Today's sales
        $todaySales = Billing::whereDate('BillingDate', today())->sum('BillingAmount');
        $todayCount = Billing::whereDate('BillingDate', today())->count();

        // Weekly sales
        $weekStart = now()->startOfWeek();
        $weeklySales = Billing::where('BillingDate', '>=', $weekStart)->sum('BillingAmount');

        // Monthly sales
        $monthStart = now()->startOfMonth();
        $monthlySales = Billing::where('BillingDate', '>=', $monthStart)->sum('BillingAmount');

        // Annual sales
        $yearStart = now()->startOfYear();
        $annualSales = Billing::where('BillingDate', '>=', $yearStart)->sum('BillingAmount');

        $salesSummary = Billing::selectRaw('SUM(BillingAmount) as total_revenue, COUNT(*) as total_sales')->first();
        $vatSummary = Billing::selectRaw('SUM(BillingAmount * 0.12 / 1.12) as total_vat')->first();

        $bestSelling = SalesItem::select('ProductID', DB::raw('SUM(Quantity) as total_quantity'), DB::raw('SUM(Quantity * UnitPrice) as total_revenue'))
            ->groupBy('ProductID')
            ->orderByDesc('total_quantity')
            ->take(5)
            ->get();

        $bestSelling = $bestSelling->load('product');

        $stockActivity = StockReceiving::with(['product', 'supplier'])->latest('DateReceived')->take(5)->get();

        // Category and supplier counts
        $totalCategories = Category::count();
        $totalSuppliers = Supplier::count();

        // User counts
        $totalAdmins = User::whereHas('role', function($query) {
            $query->where('role_name', 'admin');
        })->count();

        $totalCashiers = User::whereHas('role', function($query) {
            $query->where('role_name', 'cashier');
        })->count();

        // Recent transactions
        $recentTransactions = SalesTransaction::with(['staff', 'billing'])
            ->orderBy('SalesTransactionDate', 'desc')
            ->take(10)
            ->get();

        return view('admin.dashboard', [
            'userCount' => User::count(),
            'roleCount' => Role::count(),
            'userRole' => auth()->user()->role?->role_name,
            'stockStatus' => $stockStatus,
            'salesSummary' => $salesSummary,
            'vatSummary' => $vatSummary,
            'bestSelling' => $bestSelling,
            'stockActivity' => $stockActivity,
            'totalSales' => $totalSales,
            'todaySales' => $todaySales,
            'todayCount' => $todayCount,
            'weeklySales' => $weeklySales,
            'monthlySales' => $monthlySales,
            'annualSales' => $annualSales,
            'totalCategories' => $totalCategories,
            'totalSuppliers' => $totalSuppliers,
            'totalAdmins' => $totalAdmins,
            'totalCashiers' => $totalCashiers,
            'recentTransactions' => $recentTransactions,
        ]);
    }
}
