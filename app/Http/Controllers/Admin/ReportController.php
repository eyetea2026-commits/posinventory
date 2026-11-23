<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\DamagedProduct;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\SalesTransaction;
use App\Models\StockAdjustment;
use App\Models\StockReceiving;
use App\Models\StockReceivingDelivery;
use App\Services\UnitOfMeasure;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
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

    private const REPORT_TYPES = ['sales', 'inventory', 'damage', 'returns', 'stock_adjustment', 'receiving', 'orders'];

    private function reportType(Request $request): string
    {
        $type = (string) $request->query('type', 'sales');

        return in_array($type, self::REPORT_TYPES, true) ? $type : 'sales';
    }

    public function index(Request $request)
    {
        $reportType = $this->reportType($request);
        [$dateFrom, $dateTo, $effectiveFrom, $effectiveTo, $dateRangeError] = $this->resolveDateRange($request);

        $data = $this->buildReportData($reportType, $effectiveFrom, $effectiveTo);

        return view('admin.reports.index', array_merge($data, [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'reportType' => $reportType,
            'dateRangeError' => $dateRangeError,
        ]));
    }

    public function preview(Request $request)
    {
        $reportType = $this->reportType($request);
        [, , $effectiveFrom, $effectiveTo, $dateRangeError] = $this->resolveDateRange($request);

        $data = $this->buildReportData($reportType, $effectiveFrom, $effectiveTo);

        return response()->json([
            'html' => view('admin.reports.partials.report-body', array_merge($data, [
                'reportType' => $reportType,
                'dateFrom' => $effectiveFrom,
                'dateTo' => $effectiveTo,
            ]))->render(),
            'dateRangeError' => $dateRangeError,
            'totalRevenue' => (float) ($data['sales']->total_revenue ?? 0),
            'salesSummary' => $data['salesSummary'],
        ]);
    }

    public function details(Request $request)
    {
        $payload = $this->detailPayload($request->get('type'), $request->get('id'));

        if (! $payload) {
            return response()->json(['error' => 'Report record not found.'], 404);
        }

        return response()->json($payload);
    }

    public function detailsPrint(Request $request)
    {
        $payload = $this->detailPayload($request->get('type'), $request->get('id'));

        abort_unless($payload, 404, 'Report record not found.');

        $receiptRows = $request->get('type') === 'sales'
            ? $this->receiptItemRows(SalesTransaction::with(['payment', 'items.product'])->findOrFail($request->get('id')))
            : null;

        return view('admin.reports.details-print', [
            'title' => $payload['title'],
            'sections' => collect($payload['sections'] ?? [])
                ->reject(fn (array $section) => $receiptRows && ($section['heading'] ?? null) === 'Products Sold')
                ->values()->all(),
            'receiptRows' => $receiptRows,
        ]);
    }

    private function detailPayload(?string $type, mixed $id): ?array
    {
        return match ($type) {
            'sales' => $this->salesDetail($id),
            'inventory' => $this->inventoryDetail($id),
            'orders' => $this->orderDetail($id),
            'returns' => $this->returnDetail($id),
            'damage' => $this->damageDetail($id),
            default => null,
        };
    }

    private function money($value): string
    {
        return '₱'.number_format((float) $value, 2);
    }

    private function fmtDateTime($value): string
    {
        return $value ? Carbon::parse($value)->format('m/d/Y g:i A') : 'N/A';
    }

    private function fmtDate($value): string
    {
        return $value ? Carbon::parse($value)->format('m/d/Y') : 'N/A';
    }

    private function salesDetail($id): ?array
    {
        $transaction = SalesTransaction::with(['staff.user', 'items.product', 'discount', 'payment'])
            ->find($id);

        if (! $transaction) {
            return null;
        }

        $cashierName = $transaction->staff?->user?->full_name ?? 'Unknown User';
        $reportNumber = 'SALE-'.str_pad((string) $transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT);

        $productsTable = [
            'columns' => ['Product Numbering', 'Product Name', 'Price', 'Quantity', 'Discount', 'Total Amount'],
            'rows' => ($transaction?->items ?? collect())->values()->map(fn (SalesItem $item, int $index) => [
                $index + 1,
                $item->product?->ProductName ?? 'N/A',
                $this->money($item->UnitPrice).(($item->Unit ?: 'piece') === 'piece' ? '' : '/'.$item->Unit),
                ($item->Unit ?: 'piece') === 'piece' ? $item->Quantity : $item->quantityLabel(),
                $this->money($item->DiscountAmount ?? 0),
                $this->money(($item->Quantity * $item->UnitPrice) - ($item->DiscountAmount ?? 0)),
            ])->all(),
        ];

        $sections = [
            [
                'heading' => 'Report Information',
                'fields' => [
                    ['label' => 'Report ID', 'value' => $reportNumber],
                    ['label' => 'Cashier Name', 'value' => $cashierName],
                    ['label' => 'Transaction Date & Time', 'value' => $this->fmtDateTime($transaction?->SalesTransactionDate)],
                    ['label' => 'Customer Name', 'value' => self::customerLabel($transaction->CustomerName)],
                    ['label' => 'Payment Method', 'value' => $transaction->payment?->PaymentMethod ? ucfirst($transaction->payment->PaymentMethod) : 'N/A'],
                    ...($transaction->payment?->ProofImagePath
                        ? [['label' => 'Proof of Payment', 'value' => 'View photo', 'url' => route('payments.proof', $transaction->payment)]]
                        : []),
                ],
            ],
            ['heading' => 'Products Sold', 'table' => $productsTable],
        ];

        $wasRefunded = SalesReturn::where('SalesTransactionID', $transaction->SalesTransactionID)
            ->where('ReturnType', 'refund')
            ->where('Status', SalesReturn::STATUS_PROCESSED)
            ->exists();

        if ($wasRefunded) {
            $sections[] = ['warning' => 'This sale has been refunded.'];
        }

        return [
            'title' => "Sales Report — {$reportNumber}",
            'sections' => $sections,
        ];
    }

    private function inventoryDetail($id): ?array
    {
        $product = Product::with(['category', 'brand', 'inventory'])->find($id);

        if (! $product) {
            return null;
        }

        $stockTable = [
            'columns' => ['Current Stock', 'Remaining Stock', 'Re-Order Point'],
            'rows' => [[
                UnitOfMeasure::cell($product->inventory?->Quantity ?? 0, $product->baseUnit()),
                $product->hasPurchaseUnit() ? $product->stockLabel($product->inventory?->Quantity ?? 0) : UnitOfMeasure::cell($product->inventory?->Quantity ?? 0, $product->baseUnit()),
                $product->inventory?->ReorderThreshold !== null ? UnitOfMeasure::cell($product->inventory->ReorderThreshold, $product->baseUnit()) : 'N/A',
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
                        ['label' => 'SKU', 'value' => $product->SKU ?? 'N/A'],
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
            PurchaseOrder::STATUS_CLOSED => 'Closed (rest cancelled)',
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
        $return = SalesReturn::with(['items.product', 'staff.user.role', 'transaction.payment', 'approvedByUser'])
            ->find($id);

        if (! $return) {
            return null;
        }

        $receiptNumber = $return->transaction?->payment?->ReceiptNumber ?? 'N/A';
        $returnType = ucfirst($return->ReturnType);

        $itemsTable = [
            'columns' => ['Products Return', 'Quantity', 'Reason', 'Return Type'],
            'rows' => $return->items->map(fn (SalesReturnItem $item) => [
                $item->product?->ProductName ?? 'N/A',
                ($item->Unit ?: 'piece') === 'piece' ? $item->Quantity : $item->quantityLabel(),
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
                        ['label' => 'Return ID', 'value' => 'RTN-'.str_pad((string) $return->SalesReturnID, 6, '0', STR_PAD_LEFT)],
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
                'DMG-'.str_pad((string) $damage->DamageID, 6, '0', STR_PAD_LEFT),
                $damage->product?->category?->CategoryName ?? 'N/A',
                $damage->product?->ProductName ?? 'N/A',
                UnitOfMeasure::cell($damage->Quantity, $damage->product?->baseUnit()),
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

    public function printPreview(Request $request)
    {
        $type = $this->reportType($request);
        [, , $dateFrom, $dateTo] = $this->resolveDateRange($request);
        $rows = $this->rowsForType($type, $dateFrom, $dateTo);

        return view('admin.reports.print', [
            'type' => $type,
            'rows' => $rows,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public static function customerLabel(?string $name): string
    {
        $name = trim((string) $name);

        return ($name === '' || preg_match('/^walk-?in( customer)?$/i', $name)) ? 'N/A' : $name;
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'sales' => 'Sales and Revenue',
            'orders' => 'Purchase Order',
            'returns' => 'Refund',
            'stock_adjustment' => 'Stock Adjustment',
            'receiving' => 'Stock Receiving',
            default => ucfirst($type),
        };
    }

    public function export(Request $request)
    {
        $type = $this->reportType($request);
        $format = $request->get('format', 'csv');
        [, , $dateFrom, $dateTo] = $this->resolveDateRange($request);

        $filenameBase = 'report-'.$type.'-'.now()->format('Ymd');

        if ($format === 'pdf') {
            $rows = $this->rowsForType($type, $dateFrom, $dateTo);
            $pdf = Pdf::setOption('isFontSubsettingEnabled', true)->loadView('admin.reports.pdf', [
                'type' => $type,
                'rows' => $rows,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
            ])->setPaper('a4', 'landscape');

            $dompdf = $pdf->getDomPDF();
            $dompdf->render();
            $canvas = $dompdf->getCanvas();
            $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
            $sample = $dompdf->getFontMetrics()->getTextWidth('Page 00 of 00', $font, 7.5);
            $canvas->page_text(($canvas->get_width() - $sample) / 2, $canvas->get_height() - 28, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7.5, [0.47, 0.47, 0.47]);

            return $pdf->download($filenameBase.'.pdf');
        }

        if ($format === 'excel') {
            $rows = $this->rowsForType($type, $dateFrom, $dateTo);

            return Excel::download(
                new ReportExport($type, $rows, $dateFrom, $dateTo),
                $filenameBase.'.xlsx'
            );
        }

        return $this->exportCSV($type, $dateFrom, $dateTo, $filenameBase.'.csv');
    }

    private function resolveDateRange(Request $request): array
    {
        $dateFrom = $request->get('date_from') ?: null;
        $dateTo = $request->get('date_to') ?: null;

        $error = null;

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

    private function buildReportData(string $reportType, ?string $dateFrom, ?string $dateTo): array
    {
        $salesQuery = SalesTransaction::query();
        if ($dateFrom) {
            $salesQuery->whereDate('SalesTransactionDate', '>=', $dateFrom);
        }
        if ($dateTo) {
            $salesQuery->whereDate('SalesTransactionDate', '<=', $dateTo);
        }
        $sales = $salesQuery->selectRaw('SUM(TotalAmount) as total_revenue, COUNT(*) as total_sales')->first();

        $todaySales = SalesTransaction::whereDate('SalesTransactionDate', today())
            ->selectRaw('SUM(TotalAmount) as total, COUNT(*) as count')
            ->first();

        $weekSales = SalesTransaction::whereBetween('SalesTransactionDate', [now()->startOfWeek(), now()->endOfWeek()])
            ->selectRaw('SUM(TotalAmount) as total, COUNT(*) as count')
            ->first();

        $monthSales = SalesTransaction::whereMonth('SalesTransactionDate', now()->month)
            ->whereYear('SalesTransactionDate', now()->year)
            ->selectRaw('SUM(TotalAmount) as total, COUNT(*) as count')
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
            'salesRows' => $reportType === 'sales' ? $this->salesRows($dateFrom, $dateTo) : collect(),
            'salesSummary' => $reportType === 'sales' ? $this->salesSummary((float) ($sales->total_revenue ?? 0), $dateFrom, $dateTo) : null,
            'inventoryRows' => $reportType === 'inventory' ? $this->inventoryRows($dateFrom, $dateTo) : collect(),
            'receivingRows' => $reportType === 'receiving' ? $this->receivingRows($dateFrom, $dateTo) : collect(),
            'stockAdjustmentRows' => $reportType === 'stock_adjustment' ? $this->stockAdjustmentRows($dateFrom, $dateTo) : collect(),
            'orderRows' => $reportType === 'orders' ? $this->orderRows($dateFrom, $dateTo) : collect(),
            'returnRows' => $reportType === 'returns' ? $this->returnRows($dateFrom, $dateTo) : collect(),
            'damageRows' => $reportType === 'damage' ? $this->damageRows($dateFrom, $dateTo) : collect(),
        ];
    }

    private function salesSummary(float $salesTotal, ?string $dateFrom, ?string $dateTo): array
    {
        $refundTotal = (float) SalesReturn::query()
            ->where('ReturnType', 'refund')
            ->where('Status', SalesReturn::STATUS_PROCESSED)
            ->when($dateFrom, fn ($q) => $q->whereRaw('DATE(COALESCE(RefundDate, ReturnDate)) >= ?', [$dateFrom]))
            ->when($dateTo, fn ($q) => $q->whereRaw('DATE(COALESCE(RefundDate, ReturnDate)) <= ?', [$dateTo]))
            ->sum('RefundAmount');

        return [
            'sales_total' => round($salesTotal, 2),
            'refund_total' => round($refundTotal, 2),
            'net_total' => round($salesTotal - $refundTotal, 2),
        ];
    }

    private function rowsForType(string $type, ?string $dateFrom, ?string $dateTo)
    {
        return match ($type) {
            'inventory' => $this->inventoryRows($dateFrom, $dateTo),
            'receiving' => $this->receivingRows($dateFrom, $dateTo),
            'stock_adjustment' => $this->stockAdjustmentRows($dateFrom, $dateTo),
            'orders' => $this->orderItemRows($dateFrom, $dateTo),
            'returns' => $this->returnRows($dateFrom, $dateTo),
            'damage' => $this->damageRows($dateFrom, $dateTo),
            default => $this->salesItemRows($dateFrom, $dateTo),
        };
    }

    private function salesRows(?string $dateFrom, ?string $dateTo)
    {
        return SalesTransaction::query()
            ->when($dateFrom, fn ($q) => $q->whereDate('SalesTransactionDate', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('SalesTransactionDate', '<=', $dateTo))
            ->with(['payment', 'items'])
            ->orderByDesc('SalesTransactionDate')
            ->orderByDesc('SalesTransactionID')
            ->get();
    }

    private function salesItemRows(?string $dateFrom, ?string $dateTo)
    {
        $sales = $this->salesRows($dateFrom, $dateTo)->load('items.product');

        return $sales->flatMap(fn (SalesTransaction $sale) => $this->receiptItemRows($sale))->values();
    }

    private function receiptItemRows(SalesTransaction $sale): Collection
    {
        $items = $sale->items;
        $receiptNumber = $sale->payment?->ReceiptNumber ?? ('RCT-'.str_pad((string) $sale->SalesTransactionID, 6, '0', STR_PAD_LEFT));

        if ($items->isEmpty()) {
            return collect([$this->salesItemRow($sale, $receiptNumber, null, true)]);
        }

        $lastIndex = $items->count() - 1;

        return $items->values()->map(fn (SalesItem $item, int $index) => $this->salesItemRow($sale, $receiptNumber, $item, $index === $lastIndex));
    }

    private function salesItemRow(SalesTransaction $sale, string $receiptNumber, ?SalesItem $item, bool $isLast): object
    {
        return (object) [
            'ReceiptNumber' => $receiptNumber,
            'SaleDate' => $sale->SalesTransactionDate,
            'CustomerName' => self::customerLabel($sale->CustomerName),
            'PaymentMethod' => $sale->payment?->PaymentMethod,
            'ProductName' => $item?->product?->ProductName ?? 'N/A',
            'Quantity' => $item?->Quantity ?? 0,
            'Unit' => $item?->Unit,
            'UnitPrice' => $item?->UnitPrice ?? 0,
            'ItemTotal' => round(($item?->Quantity ?? 0) * ($item?->UnitPrice ?? 0), 2),
            'CostAmount' => (float) ($item?->CostAmount ?? 0),
            'Discount' => $isLast ? (float) ($sale->DiscountAmount ?? 0) : null,
            'VatAmount' => $isLast ? (float) ($sale->VatAmount ?? 0) : null,
            'TotalAmount' => $isLast ? (float) $sale->TotalAmount : null,
            'is_last' => $isLast,
        ];
    }

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
            ->orderByDesc('InventoryID')
            ->get();
    }

    private function stockAdjustmentRows(?string $dateFrom, ?string $dateTo)
    {
        return StockAdjustment::with('product')
            ->when($dateFrom, fn ($q) => $q->whereDate('Date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('Date', '<=', $dateTo))
            ->orderByDesc('Date')
            ->orderByDesc('AdjustmentID')
            ->get();
    }

    private function receivingRows(?string $dateFrom, ?string $dateTo)
    {
        return StockReceivingDelivery::with(['items.product', 'items.purchaseOrderItem', 'purchaseOrder.supplier', 'receivedByUser'])
            ->when($dateFrom, fn ($q) => $q->whereDate('ReceivedAt', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('ReceivedAt', '<=', $dateTo))
            ->orderByDesc('ReceivedAt')
            ->orderByDesc('StockReceivingDeliveryID')
            ->get()
            ->flatMap(fn (StockReceivingDelivery $delivery) => $delivery->items->map(fn ($item) => (object) [
                'DeliveryID' => $delivery->StockReceivingDeliveryID,
                'ReceivedAt' => $delivery->ReceivedAt,
                'ReceiptNumber' => $delivery->ReceiptNumber ?: 'N/A',
                'PONumber' => $delivery->purchaseOrder?->PONumber ?? 'N/A',
                'SupplierName' => $delivery->purchaseOrder?->supplier?->SupplierName ?? 'N/A',
                'ProductName' => $item->product?->ProductName ?? 'N/A',
                'Quantity' => $item->Quantity,
                'Unit' => $item->purchaseOrderItem?->Unit,
                'BaseQuantity' => $item->BaseQuantity ?? $item->Quantity,
                'BaseUnit' => $item->product?->baseUnit(),
                'ReceivedByName' => $delivery->receivedByUser?->full_name ?? 'N/A',
            ]));
    }

    private function damageRows(?string $dateFrom, ?string $dateTo)
    {
        return DamagedProduct::with(['product', 'supplier'])
            ->when($dateFrom, fn ($q) => $q->whereDate('DateRecorded', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('DateRecorded', '<=', $dateTo))
            ->orderByDesc('DateRecorded')
            ->orderByDesc('DamageID')
            ->get();
    }

    private function orderRows(?string $dateFrom, ?string $dateTo)
    {
        return PurchaseOrder::with(['supplier', 'items.product'])
            ->when($dateFrom, fn ($q) => $q->whereDate('PurchaseDate', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('PurchaseDate', '<=', $dateTo))
            ->orderByDesc('PurchaseDate')
            ->orderByDesc('PurchaseOrderID')
            ->get();
    }

    private function orderItemRows(?string $dateFrom, ?string $dateTo)
    {
        return $this->orderRows($dateFrom, $dateTo)->flatMap(function (PurchaseOrder $order) {
            if ($order->items->isEmpty()) {
                return collect([$this->orderItemRow($order, null)]);
            }

            return $order->items->map(fn ($item) => $this->orderItemRow($order, $item));
        });
    }

    private function orderItemRow(PurchaseOrder $order, ?PurchaseOrderItem $item): object
    {
        return (object) [
            'PONumber' => $order->PONumber,
            'PurchaseDate' => $order->PurchaseDate,
            'SupplierName' => $order->supplier?->SupplierName ?? 'N/A',
            'ProductName' => $item?->product?->ProductName ?? 'N/A',
            'Quantity' => $item?->Quantity ?? 0,
            'Unit' => $item?->Unit,
            'UnitPrice' => (float) ($item?->CostPriceAtOrder ?? 0),
            'Subtotal' => round(($item?->Quantity ?? 0) * ($item?->CostPriceAtOrder ?? 0), 2),
            'Status' => $order->Status,
        ];
    }

    private function returnRows(?string $dateFrom, ?string $dateTo)
    {
        return SalesReturn::with(['items.product', 'staff.user', 'processedByUser', 'transaction.payment'])
            ->when($dateFrom, fn ($q) => $q->whereDate('ReturnDate', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('ReturnDate', '<=', $dateTo))
            ->orderByDesc('ReturnDate')
            ->orderByDesc('SalesReturnID')
            ->get()
            ->flatMap(function (SalesReturn $return) {
                $receiptNumber = $return->transaction?->payment?->ReceiptNumber ?? ('TXN-'.$return->SalesTransactionID);
                $processedBy = $return->processedByUser?->full_name ?? $return->staff?->user?->full_name ?? 'N/A';

                return $return->items->map(fn (SalesReturnItem $item) => (object) [
                    'SalesReturnID' => $return->SalesReturnID,
                    'SalesTransactionID' => $return->SalesTransactionID,
                    'ReceiptNumber' => $receiptNumber,
                    'CustomerName' => self::customerLabel($return->CustomerName),
                    'product' => $item->product,
                    'Quantity' => $item->Quantity,
                    'Unit' => $item->Unit,
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
            return "'".$value;
        }

        return $value;
    }

    private function exportCSV($type, $dateFrom, $dateTo, $filename)
    {
        return new StreamedResponse(function () use ($type, $dateFrom, $dateTo) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            if ($type === 'sales') {
                fputcsv($handle, ['ID', 'Date', 'Amount', 'Customer', 'Payment Method']);
                foreach ($this->salesRows($dateFrom, $dateTo) as $item) {
                    fputcsv($handle, [
                        $item->SalesTransactionID,
                        $this->fmtDate($item->SalesTransactionDate),
                        $this->money($item->TotalAmount),
                        $this->csvSafe(self::customerLabel($item->CustomerName)),
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
                        $this->fmtDate($item->Date),
                        $this->csvSafe($item->product?->ProductName ?? 'N/A'),
                        ($item->QuantityAdjust >= 0 ? '+' : '').$item->QuantityAdjust,
                        $this->csvSafe($item->Reason),
                    ]);
                }
            } elseif ($type === 'receiving') {
                fputcsv($handle, ['Delivery', 'Date Received', 'Receipt Number', 'PO Number', 'Supplier', 'Product', 'Quantity', 'Added to Stock', 'Received By']);
                foreach ($this->receivingRows($dateFrom, $dateTo) as $item) {
                    fputcsv($handle, [
                        $item->DeliveryID,
                        $this->fmtDate($item->ReceivedAt),
                        $this->csvSafe($item->ReceiptNumber),
                        $this->csvSafe($item->PONumber),
                        $this->csvSafe($item->SupplierName),
                        $this->csvSafe($item->ProductName),
                        UnitOfMeasure::cell($item->Quantity, $item->Unit),
                        UnitOfMeasure::cell($item->BaseQuantity, $item->BaseUnit),
                        $this->csvSafe($item->ReceivedByName),
                    ]);
                }
            } elseif ($type === 'orders') {
                fputcsv($handle, ['ID', 'Date', 'Status', 'Supplier']);
                foreach ($this->orderRows($dateFrom, $dateTo) as $item) {
                    fputcsv($handle, [
                        $item->PurchaseOrderID,
                        $this->fmtDate($item->PurchaseDate),
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
                        $this->fmtDate($item->ReturnDate),
                    ]);
                }
            } elseif ($type === 'damage') {
                fputcsv($handle, ['ID', 'Date', 'Product', 'Supplier', 'Quantity', 'Damage Type', 'Status']);
                foreach ($this->damageRows($dateFrom, $dateTo) as $item) {
                    fputcsv($handle, [
                        $item->DamageID,
                        $this->fmtDate($item->DateRecorded),
                        $this->csvSafe($item->product?->ProductName ?? 'N/A'),
                        $this->csvSafe($item->supplier?->SupplierName ?? 'N/A'),
                        $item->Quantity,
                        $this->csvSafe(DamagedProduct::DAMAGE_TYPES[$item->DamageType] ?? $item->DamageType),
                        $item->Status,
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
