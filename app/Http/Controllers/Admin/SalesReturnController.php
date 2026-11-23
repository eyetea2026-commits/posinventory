<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DamagedProduct;
use App\Models\ProductSerial;
use App\Models\PurchaseOrderItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Notifications\ReturnRequestApproved;
use App\Notifications\ReturnRequestDeclined;
use App\Services\ActionNotifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SalesReturnController extends Controller
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
        $search = $request->query('search');
        $status = $request->query('status');

        $statusCounts = [
            'pending' => SalesReturn::where('Status', SalesReturn::STATUS_PENDING)->count(),
            'approved' => SalesReturn::where('Status', SalesReturn::STATUS_APPROVED)->count(),
            'declined' => SalesReturn::where('Status', SalesReturn::STATUS_DECLINED)->count(),
            'processed' => SalesReturn::where('Status', SalesReturn::STATUS_PROCESSED)->count(),
        ];

        $returns = SalesReturn::with([
            'transaction.payment',
            'transaction.staff.user',
            'items.product.category',
            'staff.user',
            'approvedByUser',
            'processedByUser',
        ])
            ->when($search, function ($query, $search) {
                $query->where('Status', 'like', "%{$search}%")
                    ->orWhereHas('items', function ($item) use ($search) {
                        $item->where('Reason', 'like', "%{$search}%")
                            ->orWhereHas('product', function ($product) use ($search) {
                                $product->where('ProductName', 'like', "%{$search}%");
                            });
                    });
            })
            ->when($status, function ($query, $status) {
                $query->where('Status', $status);
            })
            ->orderByDesc('SalesReturnID')
            ->paginate(15)
            ->withQueryString();

        return view('admin.sales-returns.index', [
            'returns' => $returns,
            'search' => $search,
            'status' => $status,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function show(SalesReturn $salesReturn)
    {
        $salesReturn->load([
            'transaction.payment',
            'transaction.staff.user',
            'items.product.category',
            'staff.user.role',
            'approvedByUser',
            'processedByUser',
        ]);

        $requestStaff = $salesReturn->staff;
        $requestUser = $requestStaff?->user;
        $requestedBy = [
            'Name' => $requestUser?->full_name ?? 'Unknown User',
            'EmployeeID' => $requestStaff ? ('EMP-'.str_pad((string) $requestStaff->StaffID, 4, '0', STR_PAD_LEFT)) : 'N/A',
            'Role' => $requestUser?->role?->role_name ? ucfirst($requestUser->role->role_name) : 'Unknown',
            'RequestDate' => optional($salesReturn->created_at)->format('m/d/Y g:i A'),
        ];

        $transaction = $salesReturn->transaction;
        $receiptNumber = $transaction ? 'RCT-'.str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT) : null;

        $items = $salesReturn->items->map(function (SalesReturnItem $item) {
            $refundableLineTotal = $item->refundableTotal();

            return [
                'ProductName' => $item->product?->ProductName,
                'SKU' => $item->product?->SKU,
                'Barcode' => $item->product?->Barcode,
                'Category' => $item->product?->category?->CategoryName,
                'UnitPrice' => $item->UnitPrice,
                'Quantity' => $item->Quantity,
                'Unit' => $item->Unit,
                'QuantityLabel' => $item->quantityLabel(),
                'Reason' => $item->Reason,
                'LineTotal' => $refundableLineTotal,
            ];
        });

        $totalRefundAmount = $salesReturn->RefundAmount ?? $items->sum('LineTotal');

        return response()->json([
            'transaction' => [
                'SalesTransactionID' => $transaction?->SalesTransactionID,
                'ReceiptNumber' => $receiptNumber,
                'InvoiceNumber' => $receiptNumber,
                'TransactionDate' => optional($transaction?->SalesTransactionDate)->format('m/d/Y h:i A'),
                'CustomerName' => ReportController::customerLabel($salesReturn->CustomerName ?? $transaction?->CustomerName),
                'OriginalCashier' => $transaction?->staff?->user?->full_name,
                'PaymentMethod' => $transaction?->payment?->PaymentMethod,
            ],
            'items' => $items,
            'requestedBy' => $requestedBy,
            'return' => [
                'SalesReturnID' => $salesReturn->SalesReturnID,
                'ReturnType' => $salesReturn->ReturnType,
                'Remarks' => $salesReturn->Remarks,
                'TotalRefundAmount' => $totalRefundAmount,
                'ReturnDate' => $salesReturn->ReturnDate ? Carbon::parse($salesReturn->ReturnDate)->format('m/d/Y') : null,
                'Status' => $salesReturn->Status,
                'DeclineReason' => $salesReturn->DeclineReason,
                'ApprovedBy' => $salesReturn->approvedByUser?->full_name,
                'ProcessedBy' => $salesReturn->processedByUser?->full_name,
                'RefundMethod' => $salesReturn->RefundMethod,
                'RefundAmount' => $salesReturn->RefundAmount,
                'DaysSincePurchase' => $salesReturn->days_since_purchase,
                'ReturnWindowDays' => SalesReturn::RETURN_WINDOW_DAYS,
                'EligibleForReturn' => $salesReturn->is_within_return_window,
            ],
        ]);
    }

    public function approve(SalesReturn $salesReturn)
    {
        if ($salesReturn->Status !== SalesReturn::STATUS_PENDING) {
            return back()->with('status', 'Only pending returns can be approved.');
        }

        if ($salesReturn->is_within_return_window === false) {
            return back()->with('status', "This request was made {$salesReturn->days_since_purchase} day(s) after purchase, outside the ".SalesReturn::RETURN_WINDOW_DAYS.'-day return window, and can no longer be approved. Decline it instead.');
        }

        try {
            DB::transaction(function () use ($salesReturn) {
                $locked = SalesReturn::where('SalesReturnID', $salesReturn->SalesReturnID)
                    ->where('Status', SalesReturn::STATUS_PENDING)
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    throw new \RuntimeException('This return has already been processed.');
                }

                $locked->update([
                    'Status' => SalesReturn::STATUS_APPROVED,
                    'ApprovedBy' => auth()->id(),
                ]);

                foreach ($salesReturn->items as $item) {
                    if ($item->is_unsalable) {
                        ProductSerial::where('SalesReturnID', $salesReturn->SalesReturnID)
                            ->where('ProductID', $item->ProductID)
                            ->update(['Status' => ProductSerial::STATUS_DAMAGED, 'StatusDate' => now()]);
                        $this->createDamageRecordForItem($salesReturn, $item);
                    }
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }

        $productSummary = $salesReturn->items->map(fn ($item) => "{$item->quantityLabel()} x \"{$item->product?->ProductName}\"")->implode(', ');

        ActivityLog::record(
            'return.approved',
            "Approved {$salesReturn->ReturnType} #{$salesReturn->SalesReturnID} for {$productSummary} (Txn #{$salesReturn->SalesTransactionID})"
        );

        $this->notifyCashier($salesReturn, new ReturnRequestApproved($salesReturn));
        ActionNotifier::toAdmins('Return Request Approved', 'Return request #'.str_pad((string) $salesReturn->SalesReturnID, 6, '0', STR_PAD_LEFT).' has been approved.', route('admin.sales-returns.index'), 'circle-check', 'success');

        return back()->with('status', 'Return approved. The cashier can now process it.');
    }

    private function createDamageRecordForItem(SalesReturn $salesReturn, SalesReturnItem $item): void
    {
        $supplierId = PurchaseOrderItem::where('ProductID', $item->ProductID)
            ->join('PurchaseOrder', 'PurchaseOrder.PurchaseOrderID', '=', 'PurchaseOrderItem.PurchaseOrderID')
            ->orderByDesc('PurchaseOrder.PurchaseOrderID')
            ->value('PurchaseOrder.SupplierID');

        $receiptNumber = 'RCT-'.str_pad($salesReturn->SalesTransactionID, 6, '0', STR_PAD_LEFT);
        $serials = ProductSerial::where('SalesReturnID', $salesReturn->SalesReturnID)
            ->where('ProductID', $item->ProductID)
            ->orderBy('ProductSerialID')
            ->pluck('SerialNumber');
        $serialText = $serials->isNotEmpty() ? ', Serial '.$serials->implode(', ') : '';

        $damage = DamagedProduct::create([
            'ProductID' => $item->ProductID,
            'SalesReturnID' => $salesReturn->SalesReturnID,
            'SourceModule' => DamagedProduct::SOURCE_CUSTOMER_RETURN,
            'SupplierID' => $supplierId,
            'Quantity' => $item->BaseQuantity,
            'Description' => "Customer return — {$item->Reason} (Return #{$salesReturn->SalesReturnID}, Receipt {$receiptNumber}{$serialText})",
            'DateRecorded' => now(),
            'DamageType' => $item->Reason === 'Factory Defect' ? 'factory_defect' : 'damaged_product',
            'Remarks' => $salesReturn->Remarks,
            'Status' => DamagedProduct::STATUS_FOR_SUPPLIER_RETURN,
        ]);

        ActivityLog::record(
            'damage.created_from_return',
            "Created damage record #{$damage->DamageID} for {$item->quantityLabel()} x \"{$item->product?->ProductName}\" from return #{$salesReturn->SalesReturnID} ({$item->Reason})"
        );
    }

    public function decline(Request $request, SalesReturn $salesReturn)
    {
        if ($salesReturn->Status !== SalesReturn::STATUS_PENDING) {
            return back()->with('status', 'Only pending returns can be declined.');
        }

        $data = $request->validate([
            'DeclineReason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $salesReturn->update([
                'Status' => SalesReturn::STATUS_DECLINED,
                'ApprovedBy' => auth()->id(),
                'DeclineReason' => $data['DeclineReason'],
            ]);
            ProductSerial::where('SalesReturnID', $salesReturn->SalesReturnID)->update(['SalesReturnID' => null]);

            ActivityLog::record('return.declined', "Declined return #{$salesReturn->SalesReturnID}: {$data['DeclineReason']}");
        } catch (Throwable $e) {
            Log::error('Failed to decline sales return', ['sales_return_id' => $salesReturn->SalesReturnID, 'exception' => $e->getMessage()]);

            return back()->with('status', 'Failed to decline the return. Please try again.');
        }

        $this->notifyCashier($salesReturn, new ReturnRequestDeclined($salesReturn));
        ActionNotifier::toAdmins('Return Request Declined', 'Return request #'.str_pad((string) $salesReturn->SalesReturnID, 6, '0', STR_PAD_LEFT).' has been declined.', route('admin.sales-returns.index'), 'circle-x', 'danger');

        return back()->with('status', 'Return request declined.');
    }

    private function notifyCashier(SalesReturn $salesReturn, $notification): void
    {
        $cashierUser = $salesReturn->staff?->user;

        if (! $cashierUser) {
            return;
        }

        try {
            $cashierUser->notify($notification);
        } catch (Throwable $e) {
            Log::error('Failed to dispatch return status notification', [
                'sales_return_id' => $salesReturn->SalesReturnID,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
