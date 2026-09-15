<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\DamagedProduct;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesItem;
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
            // The "Total Revenue (Selected Range)" card lives outside the
            // swapped #reportBodyContainer, so it needs its own value in
            // this payload — otherwise it silently stays stuck at whatever
            // the very first page load showed no matter how the filter
            // changes afterward.
            'totalRevenue' => (float) ($data['sales']->total_revenue ?? 0),
        ]);
    }

    /**
     * AJAX: "View Details" modal for a single report row, shared by every
     * Report Type. Each type builds the same generic {title, sections,
     * audit} shape — a list of {heading, fields|table} sections — so the
     * front-end has exactly one renderer and adding a future report type
     * only means adding one more match arm here, not new modal markup or JS.
     */
    public function details(Request $request)
    {
        $type = $request->get('type');
        $id = $request->get('id');

        $payload = match ($type) {
            'sales' => $this->salesDetail($id),
            'inventory' => $this->inventoryDetail($id),
            'orders' => $this->orderDetail($id),
            'returns' => $this->returnDetail($id),
            'damage' => $this->damageDetail($id),
            'supplier' => $this->supplierDetail($id),
            default => null,
        };

        if (! $payload) {
            return response()->json(['error' => 'Report record not found.'], 404);
        }

        return response()->json($payload);
    }

    private function money($value): string
    {
        return '₱' . number_format((float) $value, 2);
    }

    private function fmtDateTime($value): string
    {
        return $value ? \Illuminate\Support\Carbon::parse($value)->format('F j, Y g:i A') : 'N/A';
    }

    private function fmtDate($value): string
    {
        return $value ? \Illuminate\Support\Carbon::parse($value)->format('F j, Y') : 'N/A';
    }

    private function salesDetail($id): ?array
    {
        $billing = Billing::with(['transaction.staff.user', 'transaction.items.product', 'discount', 'payment'])
            ->find($id);

        if (! $billing) {
            return null;
        }

        $transaction = $billing->transaction;
        $cashierName = $transaction?->staff?->user?->full_name ?? 'Unknown User';
        $reportNumber = 'SALE-' . str_pad((string) $billing->BillingID, 6, '0', STR_PAD_LEFT);

        // Per-item DiscountAmount is this line's own share of the sale's
        // promo discount (see SalesItem::getRefundableUnitPriceAttribute()
        // for the same source-of-truth) — reused here rather than
        // recomputed, so "Total Amount" always matches what the customer
        // was actually charged for that line.
        $productsTable = [
            'columns' => ['Product Numbering', 'Product Name', 'Price', 'Quantity', 'Discount', 'Total Amount'],
            'rows' => ($transaction?->items ?? collect())->values()->map(fn (SalesItem $item, int $index) => [
                $index + 1,
                $item->product?->ProductName ?? 'N/A',
                $this->money($item->UnitPrice),
                $item->Quantity,
                $this->money($item->DiscountAmount ?? 0),
                $this->money(($item->Quantity * $item->UnitPrice) - ($item->DiscountAmount ?? 0)),
            ])->all(),
        ];

        return [
            'title' => "Sales Report — {$reportNumber}",
            'sections' => [
                [
                    'heading' => 'Report Information',
                    'fields' => [
                        ['label' => 'Report ID', 'value' => $reportNumber],
                        ['label' => 'Cashier Name', 'value' => $cashierName],
                        ['label' => 'Transaction Date & Time', 'value' => $this->fmtDateTime($transaction?->SalesTransactionDate)],
                        ['label' => 'Customer Name', 'value' => $billing->CustomerName ?: 'Walk-in Customer'],
                        ['label' => 'Payment Method', 'value' => $billing->payment?->PaymentMethod ?? 'N/A'],
                    ],
                ],
                ['heading' => 'Products Sold', 'table' => $productsTable],
            ],
        ];
    }

    private function inventoryDetail($id): ?array
    {
        $product = Product::with(['category', 'brand', 'inventory'])->find($id);

        if (! $product) {
            return null;
        }

        $stockTable = [
            'columns' => ['Current Stock', 'Remaining Stock', 'Reorder Threshold'],
            'rows' => [[
                (string) ($product->inventory?->Quantity ?? 0),
                (string) ($product->inventory?->Quantity ?? 0),
                (string) ($product->inventory?->ReorderThreshold ?? 'N/A'),
            ]],
        ];

        return [
            'title' => "Inventory Report — {$product->ProductName}",
            'sections' => [
                [
                    'fields' => [
                        ['label' => 'Category', 'value' => $product->category?->CategoryName ?? 'N/A'],
                        ['label' => 'Product Name', 'value' => $product->ProductName],
                        ['label' => 'Brand', 'value' => $product->brand?->BrandName ?? 'N/A'],
                        ['label' => 'Barcode', 'value' => $product->Barcode ?? 'N/A'],
                    ],
                ],
                ['table' => $stockTable],
            ],
        ];
    }

    private function orderDetail($id): ?array
    {
        $order = PurchaseOrder::with(['supplier', 'items.product', 'createdByUser'])->find($id);

        if (! $order) {
            return null;
        }

        $approvalStatus = match ($order->Status) {
            PurchaseOrder::STATUS_CANCELLED => 'Cancelled',
            PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_PENDING => 'Pending Approval',
            default => 'Approved',
        };

        $receivedStatus = match ($order->Status) {
            PurchaseOrder::STATUS_FULLY_RECEIVED => 'Fully Received',
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'Partially Received',
            default => 'Not Received',
        };

        $totalCost = $order->items->sum(fn ($item) => $item->Quantity * $item->CostPriceAtOrder);

        $itemsTable = [
            'columns' => ['Product Numbering', 'Quantity Ordered', 'Quantity Received', 'Unit Cost', 'Total Cost'],
            'rows' => $order->items->values()->map(fn ($item, int $index) => [
                $index + 1,
                $item->Quantity,
                $item->ReceivedQuantity,
                $this->money($item->CostPriceAtOrder),
                $this->money($item->Quantity * $item->CostPriceAtOrder),
            ])->all(),
        ];

        return [
            'title' => "Purchase Order Report — {$order->PONumber}",
            'sections' => [
                [
                    'fields' => [
                        ['label' => 'PO Number', 'value' => $order->PONumber],
                        ['label' => 'Supplier Name', 'value' => $order->supplier?->SupplierName ?? 'N/A'],
                        ['label' => 'Total Cost', 'value' => $this->money($totalCost)],
                        ['label' => 'Date Created', 'value' => $this->fmtDate($order->PurchaseDate)],
                        ['label' => 'Expected Delivery Date', 'value' => $order->ExpectedDeliveryDate ? $this->fmtDate($order->ExpectedDeliveryDate) : 'N/A'],
                        ['label' => 'Approval Status', 'value' => $approvalStatus],
                        ['label' => 'Receive Status', 'value' => $receivedStatus],
                    ],
                ],
                ['heading' => 'Ordered Products', 'table' => $itemsTable],
                [
                    'fields' => [
                        ['label' => 'Notes', 'value' => $order->Notes ?: 'None'],
                    ],
                ],
            ],
        ];
    }

    private function returnDetail($id): ?array
    {
        $return = SalesReturn::with(['items.product', 'staff.user.role', 'transaction.billing.payment', 'approvedByUser'])
            ->find($id);

        if (! $return) {
            return null;
        }

        $receiptNumber = $return->transaction?->billing?->payment?->ReceiptNumber ?? 'N/A';
        $returnType = ucfirst($return->ReturnType);

        $itemsTable = [
            'columns' => ['Products Return', 'Quantity', 'Reason', 'Return Type'],
            'rows' => $return->items->map(fn (SalesReturnItem $item) => [
                $item->product?->ProductName ?? 'N/A',
                $item->Quantity,
                ucfirst(str_replace('_', ' ', $item->Reason)),
                $returnType,
            ])->all(),
        ];

        return [
            'title' => "Return Report — Return #{$return->SalesReturnID}",
            'sections' => [
                [
                    'heading' => 'Report Information',
                    'fields' => [
                        ['label' => 'Return ID', 'value' => "RTN-" . str_pad((string) $return->SalesReturnID, 6, '0', STR_PAD_LEFT)],
                        ['label' => 'Receipt Number', 'value' => $receiptNumber],
                        ['label' => 'Date Requested', 'value' => $this->fmtDateTime($return->created_at ?? $return->ReturnDate)],
                        ['label' => 'Approved By', 'value' => $return->approvedByUser?->full_name ?? 'N/A'],
                        ['label' => 'Date Approved', 'value' => in_array($return->Status, [SalesReturn::STATUS_APPROVED, SalesReturn::STATUS_PROCESSED], true) ? $this->fmtDateTime($return->updated_at) : 'N/A'],
                        ['label' => 'Return Status', 'value' => ucfirst($return->Status)],
                    ],
                ],
                ['table' => $itemsTable],
            ],
        ];
    }

    private function damageDetail($id): ?array
    {
        $damage = DamagedProduct::with(['product.category', 'supplier', 'purchaseOrder', 'salesReturn.staff.user.role', 'resolvedByUser'])
            ->find($id);

        if (! $damage) {
            return null;
        }

        $requestedBy = 'N/A';
        if ($damage->salesReturn) {
            $requestedBy = $damage->salesReturn->staff?->user?->full_name ?? 'Unknown User';
        }

        $damageTable = [
            'columns' => ['Damage ID', 'Category', 'Product Name', 'Quantity', 'Damage Type'],
            'rows' => [[
                'DMG-' . str_pad((string) $damage->DamageID, 6, '0', STR_PAD_LEFT),
                $damage->product?->category?->CategoryName ?? 'N/A',
                $damage->product?->ProductName ?? 'N/A',
                (string) $damage->Quantity,
                DamagedProduct::DAMAGE_TYPES[$damage->DamageType] ?? $damage->DamageType,
            ]],
        ];

        return [
            'title' => "Damage Report — Damage #{$damage->DamageID}",
            'sections' => [
                [
                    'heading' => 'Report Information',
                    'fields' => [
                        ['label' => 'Requested By', 'value' => $requestedBy],
                        ['label' => 'Date Requested', 'value' => $this->fmtDate($damage->DateRecorded)],
                        ['label' => 'Status', 'value' => DamagedProduct::STATUS_LABELS[$damage->Status] ?? $damage->Status],
                    ],
                ],
                ['table' => $damageTable],
                [
                    'fields' => [
                        ['label' => 'Description', 'value' => $damage->Description ?: 'N/A'],
                    ],
                ],
            ],
        ];
    }

    private function supplierDetail($id): ?array
    {
        $supplier = Supplier::with(['purchaseOrders.items'])->find($id);

        if (! $supplier) {
            return null;
        }

        $orders = $supplier->purchaseOrders;

        $ordersTable = [
            'columns' => ['PO Number', 'Date', 'Status', 'Total Cost'],
            'rows' => $orders->sortByDesc('PurchaseDate')->map(fn (PurchaseOrder $po) => [
                $po->PONumber,
                $this->fmtDate($po->PurchaseDate),
                PurchaseOrder::STATUS_LABELS[$po->Status] ?? $po->Status,
                $this->money($po->items->sum(fn ($item) => $item->Quantity * $item->CostPriceAtOrder)),
            ])->values()->all(),
        ];

        return [
            'title' => "Supplier Report — {$supplier->SupplierName}",
            'sections' => [
                [
                    'fields' => [
                        ['label' => 'Supplier Name', 'value' => $supplier->SupplierName],
                        ['label' => 'Contact Number', 'value' => $supplier->ContactNumber ?? 'N/A'],
                        ['label' => 'Email', 'value' => $supplier->Email ?? 'N/A'],
                        ['label' => 'Address', 'value' => $supplier->Address ?? 'N/A'],
                        ['label' => 'Total Orders', 'value' => (string) $orders->count()],
                    ],
                ],
                ['heading' => 'Purchase Order', 'table' => $ordersTable],
            ],
        ];
    }

    // In-browser Print Preview (window.print()) for the currently filtered
    // report — distinct from export()'s dompdf download, reviewed on-screen
    // first. Shares admin.reports.partials.print-table with the PDF export
    // so the two surfaces never drift out of sync.
    public function printPreview(Request $request)
    {
        $type = $request->get('type', 'sales');
        [, , $dateFrom, $dateTo] = $this->resolveDateRange($request);
        $rows = $this->rowsForType($type, $dateFrom, $dateTo);

        return view('admin.reports.print', [
            'type' => $type,
            'rows' => $rows,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'landscape' => $this->isLandscapeType($type),
        ]);
    }

    // Sales and Inventory carry the widest column sets (9-10 columns) —
    // landscape keeps every column readable instead of shrinking text to
    // fit portrait width. The other four types stay portrait.
    private function isLandscapeType(string $type): bool
    {
        return in_array($type, ['sales', 'inventory'], true);
    }

    // Every other type is one plain word ucfirst() handles fine on its
    // own — only the underscored keys need an actual label instead of a
    // literal "Stock_adjustment"/"stock_adjustment". Shared by the
    // Print Preview/PDF titles and the Excel export's title block so none
    // of them can drift from each other.
    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'stock_adjustment' => 'Stock Adjustment',
            'stock_receiving' => 'Stock Receiving',
            default => ucfirst($type),
        };
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
                'landscape' => $this->isLandscapeType($type),
            ])->setPaper('a4', $this->isLandscapeType($type) ? 'landscape' : 'portrait');

            return $pdf->download($filenameBase . '.pdf');
        }

        if ($format === 'excel') {
            $rows = $this->rowsForType($type, $dateFrom, $dateTo);

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\ReportExport($type, $rows, $dateFrom, $dateTo),
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

        // A hand-edited URL or stale bookmark can send something that isn't
        // a real Y-m-d date at all — whereDate() against that wouldn't
        // throw, it would just silently fail to filter anything, which
        // looks like "the date range doesn't work" from the outside. Reject
        // it up front instead, the same way the backwards-range case below
        // already surfaces a friendly error rather than degrading silently.
        if ($dateFrom && ! $this->isValidDate($dateFrom)) {
            $error = 'Start Date is not a valid date.';
            $dateFrom = null;
        }
        if ($dateTo && ! $this->isValidDate($dateTo)) {
            $error = $error ?? 'End Date is not a valid date.';
            $dateTo = null;
        }

        $effectiveFrom = $dateFrom;
        $effectiveTo = $dateTo;

        if (! $error && $dateFrom && $dateTo && $dateTo < $dateFrom) {
            $error = 'End Date cannot be earlier than Start Date.';
            $effectiveTo = $dateFrom;
        }

        return [$dateFrom, $dateTo, $effectiveFrom, $effectiveTo, $error];
    }

    private function isValidDate(string $value): bool
    {
        $parsed = \DateTime::createFromFormat('Y-m-d', $value);

        return $parsed && $parsed->format('Y-m-d') === $value;
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
            'stockAdjustmentRows' => $reportType === 'stock_adjustment' ? $this->stockAdjustmentRows($dateFrom, $dateTo) : collect(),
            'stockReceivingRows' => $reportType === 'stock_receiving' ? $this->stockReceivingRows($dateFrom, $dateTo) : collect(),
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
            'stock_adjustment' => $this->stockAdjustmentRows($dateFrom, $dateTo),
            'stock_receiving' => $this->stockReceivingRows($dateFrom, $dateTo),
            'orders' => $this->orderItemRows($dateFrom, $dateTo),
            'returns' => $this->returnRows($dateFrom, $dateTo),
            'damage' => $this->damageRows($dateFrom, $dateTo),
            'supplier' => $this->supplierRows($dateFrom, $dateTo),
            default => $this->salesItemRows($dateFrom, $dateTo),
        };
    }

    private function salesBillingRows(?string $dateFrom, ?string $dateTo)
    {
        return Billing::query()
            ->when($dateFrom, fn ($q) => $q->whereDate('BillingDate', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('BillingDate', '<=', $dateTo))
            ->with(['payment', 'transaction.items'])
            ->orderByDesc('BillingDate')
            ->get();
    }

    // Print/PDF/Excel Sales Report: one row per product per invoice, so the
    // documented columns (Product/Qty/Unit Price) mean something — unlike
    // the on-screen preview (which still uses salesBillingRows() above,
    // deliberately untouched), this is export-only. Discount/VAT/Total are
    // transaction-level facts in this system (never stored per item), so
    // they're only populated on each invoice's first item row — showing
    // them on every row would imply an even per-item split that never
    // happened. ReportSummaryBuilder::sales() relies on exactly this shape.
    private function salesItemRows(?string $dateFrom, ?string $dateTo)
    {
        $billings = $this->salesBillingRows($dateFrom, $dateTo)->load('transaction.items.product');

        $rows = collect();

        foreach ($billings as $billing) {
            $items = $billing->transaction?->items ?? collect();
            $receiptNumber = $billing->payment?->ReceiptNumber ?? ('BILL-' . str_pad((string) $billing->BillingID, 6, '0', STR_PAD_LEFT));

            if ($items->isEmpty()) {
                $rows->push($this->salesItemRow($billing, $receiptNumber, null, true));
                continue;
            }

            foreach ($items as $index => $item) {
                $rows->push($this->salesItemRow($billing, $receiptNumber, $item, $index === 0));
            }
        }

        return $rows;
    }

    private function salesItemRow(Billing $billing, string $receiptNumber, ?SalesItem $item, bool $isFirst): object
    {
        return (object) [
            'ReceiptNumber' => $receiptNumber,
            'BillingDate' => $billing->BillingDate,
            'CustomerName' => $billing->CustomerName ?? 'Walk-in Customer',
            'PaymentMethod' => $billing->payment?->PaymentMethod,
            'ProductName' => $item?->product?->ProductName ?? 'N/A',
            'Quantity' => $item?->Quantity ?? 0,
            'UnitPrice' => $item?->UnitPrice ?? 0,
            'ItemTotal' => round(($item?->Quantity ?? 0) * ($item?->UnitPrice ?? 0), 2),
            'Discount' => $isFirst ? (float) ($billing->DiscountAmount ?? 0) : null,
            'VatAmount' => $isFirst ? (float) ($billing->VatAmount ?? 0) : null,
            'BillingAmount' => $isFirst ? (float) $billing->BillingAmount : null,
            'is_first' => $isFirst,
        ];
    }

    // Inventory itself has no date column (it's a live quantity, not a
    // ledger), so "within the selected date range" is expressed as "this
    // product had stock movement — received or adjusted — in that range",
    // matching every other report's when()-filtered pattern. No range
    // selected still shows every tracked product, same as before.
    private function inventoryRows(?string $dateFrom, ?string $dateTo)
    {
        return Inventory::with(['product.category', 'product.suppliers.supplier'])
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

    // Its own report type — every adjustment (increase or decrease,
    // whatever the reason) that touched stock in the selected range, so
    // "reports reflect every stock adjustment" is verifiably true.
    private function stockAdjustmentRows(?string $dateFrom, ?string $dateTo)
    {
        return StockAdjustment::with('product')
            ->when($dateFrom, fn ($q) => $q->whereDate('Date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('Date', '<=', $dateTo))
            ->orderByDesc('Date')
            ->get();
    }

    // Every unit physically received into stock in the selected range,
    // whether ad-hoc or against a Purchase Order.
    private function stockReceivingRows(?string $dateFrom, ?string $dateTo)
    {
        return StockReceiving::with(['product', 'supplier'])
            ->when($dateFrom, fn ($q) => $q->whereDate('DateReceived', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('DateReceived', '<=', $dateTo))
            ->orderByDesc('DateReceived')
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
                'ContactPerson' => $supplier->ContactPerson,
                'ContactNumber' => $supplier->ContactNumber,
                'Email' => $supplier->Email,
                'Address' => $supplier->Address,
                'Status' => $supplier->Status,
                'TotalOrders' => $orders->count(),
                'TotalAmount' => $orders->flatMap->items->sum(fn ($item) => $item->ReceivedQuantity * $item->CostPriceAtOrder),
            ];
        });
    }

    private function orderRows(?string $dateFrom, ?string $dateTo)
    {
        return PurchaseOrder::with(['supplier', 'items.product'])
            ->when($dateFrom, fn ($q) => $q->whereDate('PurchaseDate', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('PurchaseDate', '<=', $dateTo))
            ->orderByDesc('PurchaseDate')
            ->get();
    }

    // Print/PDF/Excel "Purchase Report": one row per ordered product line
    // (PO Number/Date/Supplier/Product/Qty/Unit Price/Subtotal), not per PO
    // — unlike the on-screen preview (which still uses orderRows() above,
    // deliberately untouched). No VAT column: purchase orders have no VAT
    // concept in this system (VAT only applies at POS checkout).
    private function orderItemRows(?string $dateFrom, ?string $dateTo)
    {
        return $this->orderRows($dateFrom, $dateTo)->flatMap(function (PurchaseOrder $order) {
            if ($order->items->isEmpty()) {
                return collect([$this->orderItemRow($order, null)]);
            }

            return $order->items->map(fn ($item) => $this->orderItemRow($order, $item));
        });
    }

    private function orderItemRow(PurchaseOrder $order, ?\App\Models\PurchaseOrderItem $item): object
    {
        return (object) [
            'PONumber' => $order->PONumber,
            'PurchaseDate' => $order->PurchaseDate,
            'SupplierName' => $order->supplier?->SupplierName ?? 'N/A',
            'ProductName' => $item?->product?->ProductName ?? 'N/A',
            'Quantity' => $item?->Quantity ?? 0,
            'UnitPrice' => (float) ($item?->CostPriceAtOrder ?? 0),
            'Subtotal' => round(($item?->Quantity ?? 0) * ($item?->CostPriceAtOrder ?? 0), 2),
            'Status' => $order->Status,
        ];
    }

    // One row per returned product line, not per request — a single
    // multi-item return request now flattens to one CSV/report row per item.
    private function returnRows(?string $dateFrom, ?string $dateTo)
    {
        return SalesReturn::with(['items.product', 'staff.user', 'processedByUser', 'transaction.billing.payment'])
            ->when($dateFrom, fn ($q) => $q->whereDate('ReturnDate', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('ReturnDate', '<=', $dateTo))
            ->orderByDesc('ReturnDate')
            ->get()
            ->flatMap(function (SalesReturn $return) {
                $receiptNumber = $return->transaction?->billing?->payment?->ReceiptNumber ?? ('TXN-' . $return->SalesTransactionID);
                // Only set once an admin actually finalizes the refund/replacement —
                // falls back to whoever logged the request so pending rows aren't blank.
                $processedBy = $return->processedByUser?->full_name ?? $return->staff?->user?->full_name ?? 'N/A';

                return $return->items->map(fn (SalesReturnItem $item) => (object) [
                    'SalesReturnID' => $return->SalesReturnID,
                    'SalesTransactionID' => $return->SalesTransactionID,
                    'ReceiptNumber' => $receiptNumber,
                    'CustomerName' => $return->CustomerName ?? 'N/A',
                    'product' => $item->product,
                    'Quantity' => $item->Quantity,
                    'Reason' => $item->Reason,
                    'ReturnType' => $return->ReturnType,
                    'Status' => $return->Status,
                    'ReturnDate' => $return->ReturnDate,
                    'CashierName' => $return->staff?->user?->full_name ?? 'N/A',
                    'ProcessedByName' => $processedBy,
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
            } elseif ($type === 'stock_adjustment') {
                fputcsv($handle, ['ID', 'Date', 'Product', 'Adjustment', 'Reason']);
                foreach ($this->stockAdjustmentRows($dateFrom, $dateTo) as $item) {
                    fputcsv($handle, [
                        $item->AdjustmentID,
                        $item->Date,
                        $this->csvSafe($item->product?->ProductName ?? 'N/A'),
                        ($item->QuantityAdjust >= 0 ? '+' : '') . $item->QuantityAdjust,
                        $this->csvSafe($item->Reason),
                    ]);
                }
            } elseif ($type === 'stock_receiving') {
                fputcsv($handle, ['ID', 'Date Received', 'Product', 'Supplier', 'Quantity', 'Receipt Number']);
                foreach ($this->stockReceivingRows($dateFrom, $dateTo) as $item) {
                    fputcsv($handle, [
                        $item->ReceivingID,
                        $item->DateReceived,
                        $this->csvSafe($item->product?->ProductName ?? 'N/A'),
                        $this->csvSafe($item->supplier?->SupplierName ?? 'N/A'),
                        $item->Quantity,
                        $this->csvSafe($item->ReceiptNumber ?? 'N/A'),
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
