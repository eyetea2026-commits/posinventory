<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesItem;
use App\Models\SalesReturn;
use App\Models\SalesTransaction;
use App\Models\StockReceiving;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
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
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $reportType = $request->get('type', 'sales');

        // Base query for sales
        $salesQuery = Billing::query();
        if ($dateFrom) {
            $salesQuery->whereDate('BillingDate', '>=', $dateFrom);
        }
        if ($dateTo) {
            $salesQuery->whereDate('BillingDate', '<=', $dateTo);
        }

        $sales = $salesQuery->selectRaw('SUM(BillingAmount) as total_revenue, COUNT(*) as total_sales')->first();

        // Today's sales
        $todaySales = Billing::whereDate('BillingDate', today())
            ->selectRaw('SUM(BillingAmount) as total, COUNT(*) as count')
            ->first();

        // This week's sales
        $weekSales = Billing::whereBetween('BillingDate', [now()->startOfWeek(), now()->endOfWeek()])
            ->selectRaw('SUM(BillingAmount) as total, COUNT(*) as count')
            ->first();

        // This month's sales
        $monthSales = Billing::whereMonth('BillingDate', now()->month)
            ->whereYear('BillingDate', now()->year)
            ->selectRaw('SUM(BillingAmount) as total, COUNT(*) as count')
            ->first();

        // Inventory stats — computed live from Quantity vs. ReorderThreshold rather
        // than the stored Status string, since different write paths persist
        // different Status vocabularies (see dashboard for the same fix).
        $inventoryCount = Inventory::count();
        $lowStock = Inventory::where('Quantity', '>', 0)->whereColumn('Quantity', '<=', DB::raw('COALESCE(ReorderThreshold, 50)'))->count();
        $outOfStock = Inventory::where('Quantity', '<=', 0)->count();

        // Supplier count
        $suppliers = StockReceiving::selectRaw('COUNT(DISTINCT SupplierID) as total_suppliers')->first();

        // Purchase orders
        $purchaseOrders = PurchaseOrder::count();

        // Pending returns
        $returns = SalesReturn::where('Status', 'pending')->count();

        // Best selling products
        $bestSelling = SalesItem::select('ProductID', DB::raw('SUM(Quantity) as total_sold, SUM(Quantity * UnitPrice) as total_revenue'))
            ->with('product')
            ->groupBy('ProductID')
            ->orderByDesc('total_sold')
            ->take(10)
            ->get();

        // Recent transactions
        $recentSales = SalesTransaction::with(['staff', 'billing'])
            ->orderByDesc('SalesTransactionDate')
            ->take(10)
            ->get();

        return view('admin.reports.index', [
            'sales' => $sales,
            'todaySales' => $todaySales,
            'weekSales' => $weekSales,
            'monthSales' => $monthSales,
            'inventoryCount' => $inventoryCount,
            'lowStock' => $lowStock,
            'outOfStock' => $outOfStock,
            'totalSuppliers' => $suppliers->total_suppliers ?? 0,
            'purchaseOrders' => $purchaseOrders,
            'pendingReturns' => $returns,
            'bestSelling' => $bestSelling,
            'recentSales' => $recentSales,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'reportType' => $reportType,
        ]);
    }

    public function export(Request $request)
    {
        $type = $request->get('type', 'sales');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        // CSV is the only format actually implemented — always name the file
        // .csv regardless of what's requested, so the extension never lies
        // about the content inside it.
        $filename = 'report-' . $type . '-' . now()->format('Ymd') . '.csv';

        return $this->exportCSV($type, $dateFrom, $dateTo, $filename);
    }

    private function csvSafe($value)
    {
        if (is_string($value) && preg_match('/^[=+\-@]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }

    private function exportCSV($type, $dateFrom, $dateTo, $filename)
    {
        return new StreamedResponse(function () use ($type, $dateFrom, $dateTo) {
            $handle = fopen('php://output', 'w');

            if ($type === 'sales') {
                fputcsv($handle, ['ID', 'Date', 'Amount', 'Customer', 'Payment Method']);
                Billing::query()
                    ->when($dateFrom, fn ($q) => $q->whereDate('BillingDate', '>=', $dateFrom))
                    ->when($dateTo, fn ($q) => $q->whereDate('BillingDate', '<=', $dateTo))
                    ->with('payment')->orderByDesc('BillingDate')->chunk(100, function ($items) use ($handle) {
                        foreach ($items as $item) {
                            fputcsv($handle, [
                                $item->BillingID,
                                $item->BillingDate,
                                $item->BillingAmount,
                                $this->csvSafe($item->CustomerName ?? 'N/A'),
                                $this->csvSafe($item->payment?->PaymentMethod ?? 'N/A'),
                            ]);
                        }
                    });
            } elseif ($type === 'inventory') {
                // A live stock snapshot, not a dated historical record — the
                // date range filter doesn't apply here.
                fputcsv($handle, ['ID', 'Product', 'Quantity', 'Status']);
                Inventory::with('product')->orderBy('InventoryID')->chunk(100, function ($items) use ($handle) {
                    foreach ($items as $item) {
                        fputcsv($handle, [
                            $item->InventoryID,
                            $this->csvSafe($item->product?->ProductName ?? 'N/A'),
                            $item->Quantity,
                            $item->Status,
                        ]);
                    }
                });
            } elseif ($type === 'orders') {
                fputcsv($handle, ['ID', 'Date', 'Status', 'Supplier']);
                PurchaseOrder::query()
                    ->when($dateFrom, fn ($q) => $q->whereDate('PurchaseDate', '>=', $dateFrom))
                    ->when($dateTo, fn ($q) => $q->whereDate('PurchaseDate', '<=', $dateTo))
                    ->with('supplier')->orderByDesc('PurchaseDate')->chunk(100, function ($items) use ($handle) {
                        foreach ($items as $item) {
                            fputcsv($handle, [
                                $item->PurchaseOrderID,
                                $item->PurchaseDate,
                                $item->Status,
                                $this->csvSafe($item->supplier?->SupplierName ?? 'N/A'),
                            ]);
                        }
                    });
            } else {
                fputcsv($handle, ['ID', 'Transaction ID', 'Product', 'Quantity', 'Reason', 'Status', 'Date']);
                SalesReturn::query()
                    ->when($dateFrom, fn ($q) => $q->whereDate('ReturnDate', '>=', $dateFrom))
                    ->when($dateTo, fn ($q) => $q->whereDate('ReturnDate', '<=', $dateTo))
                    ->with('product')->orderByDesc('ReturnDate')->chunk(100, function ($items) use ($handle) {
                        foreach ($items as $item) {
                            fputcsv($handle, [
                                $item->SalesReturnID,
                                $item->SalesTransactionID,
                                $this->csvSafe($item->product?->ProductName ?? 'N/A'),
                                $item->Quantity,
                                $this->csvSafe($item->Reason),
                                $item->Status,
                                $item->ReturnDate,
                            ]);
                        }
                    });
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}