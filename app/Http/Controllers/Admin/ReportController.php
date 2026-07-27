<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\DamagedProduct;
use App\Models\Inventory;
use App\Models\PurchaseOrder;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\StockAdjustment;
use App\Models\StockReceiving;
use App\Models\Supplier;
use Illuminate\Http\Request;
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
        $reportType = $request->get('type', 'sales');
        [$dateFrom, $dateTo, $effectiveFrom, $effectiveTo, $dateRangeError] = $this->resolveDateRange($request);

        $data = $this->buildReportData($reportType, $effectiveFrom, $effectiveTo);

        return view('admin.reports.index', array_merge($data, [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'reportType' => $reportType,
            'dateRangeError' => $dateRangeError,
        ]));
    }

    /**
     * AJAX: renders the same report-body partial the index page shows, for
     * the "Preview Report" modal — fetched fresh so the preview always
     * matches whatever Report Type / date range is currently selected, and
     * a successful preview is what unlocks the download buttons client-side.
     */
    public function preview(Request $request)
    {
        $reportType = $request->get('type', 'sales');
        [, , $effectiveFrom, $effectiveTo, $dateRangeError] = $this->resolveDateRange($request);

        $data = $this->buildReportData($reportType, $effectiveFrom, $effectiveTo);

        return response()->json([
            'html' => view('admin.reports.partials.report-body', array_merge($data, [
                'reportType' => $reportType,
                'dateFrom' => $effectiveFrom,
                'dateTo' => $effectiveTo,
            ]))->render(),
            'dateRangeError' => $dateRangeError,
        ]);
    }

    public function export(Request $request)
    {
        $type = $request->get('type', 'sales');
        $format = $request->get('format', 'csv');
        [, , $dateFrom, $dateTo] = $this->resolveDateRange($request);

        $filenameBase = 'report-' . $type . '-' . now()->format('Ymd');

        if ($format === 'pdf') {
            $rows = $this->rowsForType($type, $dateFrom, $dateTo);
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.reports.pdf', [
                'type' => $type,
                'rows' => $rows,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
            ]);

            return $pdf->download($filenameBase . '.pdf');
        }

        if ($format === 'excel') {
            $rows = $this->rowsForType($type, $dateFrom, $dateTo);

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\ReportExport($type, $rows),
                $filenameBase . '.xlsx'
            );
        }

        return $this->exportCSV($type, $dateFrom, $dateTo, $filenameBase . '.csv');
    }

    /**
     * Reads date_from/date_to off the request and validates the range.
     * Returns [rawFrom, rawTo, effectiveFrom, effectiveTo, error] — the raw
     * values repopulate the form fields even when invalid, while the
     * "effective" values are what actually get used to query/filter data.
     * An invalid (backwards) range is clamped to a single day (Start Date)
     * rather than silently falling back to unfiltered — the client already
     * prevents picking a backwards range via the End Date's "min" attribute,
     * this is just a server-side safety net.
     */
    private function resolveDateRange(Request $request): array
    {
        $dateFrom = $request->get('date_from') ?: null;
        $dateTo = $request->get('date_to') ?: null;

        $error = null;
        $effectiveFrom = $dateFrom;
        $effectiveTo = $dateTo;

        if ($dateFrom && $dateTo && $dateTo < $dateFrom) {
            $error = 'End Date cannot be earlier than Start Date.';
            $effectiveTo = $dateFrom;
        }

        return [$dateFrom, $dateTo, $effectiveFrom, $effectiveTo, $error];
    }

    /**
     * All data the report views need, computed once and shared by index(),
     * preview(), and (for the type-specific rows) export() — so the
     * on-screen report, its preview, and every download format all agree.
     */
    private function buildReportData(string $reportType, ?string $dateFrom, ?string $dateTo): array
    {
        $salesQuery = Billing::query();
        if ($dateFrom) {
            $salesQuery->whereDate('BillingDate', '>=', $dateFrom);
        }
        if ($dateTo) {
            $salesQuery->whereDate('BillingDate', '<=', $dateTo);
        }
        $sales = $salesQuery->selectRaw('SUM(BillingAmount) as total_revenue, COUNT(*) as total_sales')->first();

        $todaySales = Billing::whereDate('BillingDate', today())
            ->selectRaw('SUM(BillingAmount) as total, COUNT(*) as count')
            ->first();

        $weekSales = Billing::whereBetween('BillingDate', [now()->startOfWeek(), now()->endOfWeek()])
            ->selectRaw('SUM(BillingAmount) as total, COUNT(*) as count')
            ->first();

        $monthSales = Billing::whereMonth('BillingDate', now()->month)
            ->whereYear('BillingDate', now()->year)
            ->selectRaw('SUM(BillingAmount) as total, COUNT(*) as count')
            ->first();

        $suppliers = StockReceiving::selectRaw('COUNT(DISTINCT SupplierID) as total_suppliers')->first();
        $purchaseOrders = PurchaseOrder::count();
        $pendingReturns = SalesReturn::where('Status', 'pending')->count();

        return [
            'sales' => $sales,
            'todaySales' => $todaySales,
            'weekSales' => $weekSales,
            'monthSales' => $monthSales,
            'totalSuppliers' => $suppliers->total_suppliers ?? 0,
            'purchaseOrders' => $purchaseOrders,
            'pendingReturns' => $pendingReturns,
            'salesRows' => $reportType === 'sales' ? $this->salesBillingRows($dateFrom, $dateTo) : collect(),
            'inventoryRows' => $reportType === 'inventory' ? $this->inventoryRows($dateFrom, $dateTo) : collect(),
            'stockAdjustmentRows' => $reportType === 'inventory' ? $this->stockAdjustmentRows($dateFrom, $dateTo) : collect(),
            'orderRows' => $reportType === 'orders' ? $this->orderRows($dateFrom, $dateTo) : collect(),
            'returnRows' => $reportType === 'returns' ? $this->returnRows($dateFrom, $dateTo) : collect(),
            'damageRows' => $reportType === 'damage' ? $this->damageRows($dateFrom, $dateTo) : collect(),
            'supplierRows' => $reportType === 'supplier' ? $this->supplierRows($dateFrom, $dateTo) : collect(),
        ];
    }

    private function rowsForType(string $type, ?string $dateFrom, ?string $dateTo)
    {
        return match ($type) {
            'inventory' => $this->inventoryRows($dateFrom, $dateTo),
            'orders' => $this->orderRows($dateFrom, $dateTo),
            'returns' => $this->returnRows($dateFrom, $dateTo),
            'damage' => $this->damageRows($dateFrom, $dateTo),
            'supplier' => $this->supplierRows($dateFrom, $dateTo),
            default => $this->salesBillingRows($dateFrom, $dateTo),
        };
    }

    private function salesBillingRows(?string $dateFrom, ?string $dateTo)
    {
        return Billing::query()
            ->when($dateFrom, fn ($q) => $q->whereDate('BillingDate', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('BillingDate', '<=', $dateTo))
            ->with('payment')
            ->orderByDesc('BillingDate')
            ->get();
    }

    // Inventory itself has no date column (it's a live quantity, not a
    // ledger), so "within the selected date range" is expressed as "this
    // product had stock movement — received or adjusted — in that range",
    // matching every other report's when()-filtered pattern. No range
    // selected still shows every tracked product, same as before.
    private function inventoryRows(?string $dateFrom, ?string $dateTo)
    {
        return Inventory::with('product')
            ->when($dateFrom || $dateTo, function ($query) use ($dateFrom, $dateTo) {
                $query->where(function ($q) use ($dateFrom, $dateTo) {
                    $q->whereHas('product.stockReceivings', function ($sr) use ($dateFrom, $dateTo) {
                        $sr->when($dateFrom, fn ($x) => $x->whereDate('DateReceived', '>=', $dateFrom))
                            ->when($dateTo, fn ($x) => $x->whereDate('DateReceived', '<=', $dateTo));
                    })->orWhereHas('product.stockAdjustments', function ($sa) use ($dateFrom, $dateTo) {
                        $sa->when($dateFrom, fn ($x) => $x->whereDate('Date', '>=', $dateFrom))
                            ->when($dateTo, fn ($x) => $x->whereDate('Date', '<=', $dateTo));
                    });
                });
            })
            ->orderBy('InventoryID')
            ->get();
    }

    // Supplementary to the Inventory report — every adjustment (increase or
    // decrease, whatever the reason) that touched stock in the selected
    // range, so "reports reflect every stock adjustment" is verifiably true.
    private function stockAdjustmentRows(?string $dateFrom, ?string $dateTo)
    {
        return StockAdjustment::with('product')
            ->when($dateFrom, fn ($q) => $q->whereDate('Date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('Date', '<=', $dateTo))
            ->orderByDesc('Date')
            ->get();
    }

    private function damageRows(?string $dateFrom, ?string $dateTo)
    {
        return DamagedProduct::with(['product', 'supplier'])
            ->when($dateFrom, fn ($q) => $q->whereDate('DateRecorded', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('DateRecorded', '<=', $dateTo))
            ->orderByDesc('DateRecorded')
            ->get();
    }

    // One row per supplier: how many POs were placed against them and how
    // much was actually spent (received qty × cost) within the date range.
    // Eager-loads every supplier's filtered purchase orders (+ items) in one
    // pair of queries instead of running a separate query per supplier row.
    private function supplierRows(?string $dateFrom, ?string $dateTo)
    {
        $suppliers = Supplier::with(['purchaseOrders' => function ($query) use ($dateFrom, $dateTo) {
            $query->when($dateFrom, fn ($q) => $q->whereDate('PurchaseDate', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('PurchaseDate', '<=', $dateTo))
                ->with('items');
        }])
            ->orderBy('SupplierName')
            ->get();

        return $suppliers->map(function (Supplier $supplier) {
            $orders = $supplier->purchaseOrders;

            return (object) [
                'SupplierID' => $supplier->SupplierID,
                'SupplierName' => $supplier->SupplierName,
                'Status' => $supplier->Status,
                'TotalOrders' => $orders->count(),
                'TotalAmount' => $orders->flatMap->items->sum(fn ($item) => $item->ReceivedQuantity * $item->CostPriceAtOrder),
            ];
        });
    }

    private function orderRows(?string $dateFrom, ?string $dateTo)
    {
        return PurchaseOrder::with('supplier')
            ->when($dateFrom, fn ($q) => $q->whereDate('PurchaseDate', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('PurchaseDate', '<=', $dateTo))
            ->orderByDesc('PurchaseDate')
            ->get();
    }

    // One row per returned product line, not per request — a single
    // multi-item return request now flattens to one CSV/report row per item.
    private function returnRows(?string $dateFrom, ?string $dateTo)
    {
        return SalesReturn::with(['items.product', 'staff.user'])
            ->when($dateFrom, fn ($q) => $q->whereDate('ReturnDate', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('ReturnDate', '<=', $dateTo))
            ->orderByDesc('ReturnDate')
            ->get()
            ->flatMap(function (SalesReturn $return) {
                return $return->items->map(fn (SalesReturnItem $item) => (object) [
                    'SalesReturnID' => $return->SalesReturnID,
                    'SalesTransactionID' => $return->SalesTransactionID,
                    'product' => $item->product,
                    'Quantity' => $item->Quantity,
                    'Reason' => $item->Reason,
                    'Status' => $return->Status,
                    'ReturnDate' => $return->ReturnDate,
                    'CashierName' => $return->staff?->user?->full_name ?? 'N/A',
                ]);
            });
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
                foreach ($this->salesBillingRows($dateFrom, $dateTo) as $item) {
                    fputcsv($handle, [
                        $item->BillingID,
                        $item->BillingDate,
                        $item->BillingAmount,
                        $this->csvSafe($item->CustomerName ?? 'N/A'),
                        $this->csvSafe($item->payment?->PaymentMethod ?? 'N/A'),
                    ]);
                }
            } elseif ($type === 'inventory') {
                fputcsv($handle, ['ID', 'Product', 'Quantity', 'Status']);
                foreach ($this->inventoryRows($dateFrom, $dateTo) as $item) {
                    fputcsv($handle, [
                        $item->InventoryID,
                        $this->csvSafe($item->product?->ProductName ?? 'N/A'),
                        $item->Quantity,
                        $item->Status,
                    ]);
                }
            } elseif ($type === 'orders') {
                fputcsv($handle, ['ID', 'Date', 'Status', 'Supplier']);
                foreach ($this->orderRows($dateFrom, $dateTo) as $item) {
                    fputcsv($handle, [
                        $item->PurchaseOrderID,
                        $item->PurchaseDate,
                        $item->Status,
                        $this->csvSafe($item->supplier?->SupplierName ?? 'N/A'),
                    ]);
                }
            } elseif ($type === 'returns') {
                fputcsv($handle, ['ID', 'Transaction ID', 'Product', 'Quantity', 'Reason', 'Cashier', 'Status', 'Date']);
                foreach ($this->returnRows($dateFrom, $dateTo) as $item) {
                    fputcsv($handle, [
                        $item->SalesReturnID,
                        $item->SalesTransactionID,
                        $this->csvSafe($item->product?->ProductName ?? 'N/A'),
                        $item->Quantity,
                        $this->csvSafe($item->Reason),
                        $this->csvSafe($item->CashierName),
                        $item->Status,
                        $item->ReturnDate,
                    ]);
                }
            } elseif ($type === 'damage') {
                fputcsv($handle, ['ID', 'Date', 'Product', 'Supplier', 'Quantity', 'Damage Type', 'Status']);
                foreach ($this->damageRows($dateFrom, $dateTo) as $item) {
                    fputcsv($handle, [
                        $item->DamageID,
                        optional($item->DateRecorded)->format('Y-m-d'),
                        $this->csvSafe($item->product?->ProductName ?? 'N/A'),
                        $this->csvSafe($item->supplier?->SupplierName ?? 'N/A'),
                        $item->Quantity,
                        $this->csvSafe(\App\Models\DamagedProduct::DAMAGE_TYPES[$item->DamageType] ?? $item->DamageType),
                        $item->Status,
                    ]);
                }
            } elseif ($type === 'supplier') {
                fputcsv($handle, ['ID', 'Supplier', 'Status', 'Total Orders', 'Total Amount']);
                foreach ($this->supplierRows($dateFrom, $dateTo) as $item) {
                    fputcsv($handle, [
                        $item->SupplierID,
                        $this->csvSafe($item->SupplierName),
                        $item->Status,
                        $item->TotalOrders,
                        $item->TotalAmount,
                    ]);
                }
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
