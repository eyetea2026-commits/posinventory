<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DamagedProduct;
use App\Models\PurchaseOrderItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Notifications\ReturnRequestApproved;
use App\Notifications\ReturnRequestDeclined;
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
        $returnType = $request->query('return_type');

        $statusCounts = [
            'pending' => SalesReturn::where('Status', SalesReturn::STATUS_PENDING)->count(),
            'approved' => SalesReturn::where('Status', SalesReturn::STATUS_APPROVED)->count(),
            'declined' => SalesReturn::where('Status', SalesReturn::STATUS_DECLINED)->count(),
            'processed' => SalesReturn::where('Status', SalesReturn::STATUS_PROCESSED)->count(),
        ];

        $returns = SalesReturn::with([
                'transaction.billing.payment',
                'transaction.staff.user',
                'items.product.category',
                'staff.user',
                'approvedByUser',
                'processedByUser',
                'replacement.product',
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
            ->when($returnType, function ($query, $returnType) {
                $query->where('ReturnType', $returnType);
            })
            ->orderByDesc('ReturnDate')
            ->paginate(15)
            ->withQueryString();

        return view('admin.sales-returns.index', [
            'returns' => $returns,
            'search' => $search,
            'status' => $status,
            'returnType' => $returnType,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function show(SalesReturn $salesReturn)
    {
        $salesReturn->load([
            'transaction.billing.payment',
            'transaction.staff.user',
            'items.product.category',
            'staff.user.role',
            'approvedByUser',
            'processedByUser',
            'replacement.product',
        ]);

        // Who actually submitted THIS return request (SalesReturn.StaffID) —
        // deliberately separate from "OriginalCashier" below, which is
        // whoever processed the original sale via SalesTransaction.StaffID.
        // They're very often the same person but not guaranteed to be (a
        // return can be filed by a different cashier than the one who rang
        // up the original sale), so this must be resolved from the return
        // record itself, never assumed to match the transaction.
        $requestStaff = $salesReturn->staff;
        $requestUser = $requestStaff?->user;
        $requestedBy = [
            'Name' => $requestUser?->full_name ?? 'Unknown User',
            'EmployeeID' => $requestStaff ? ('EMP-' . str_pad((string) $requestStaff->StaffID, 4, '0', STR_PAD_LEFT)) : 'N/A',
            'Role' => $requestUser?->role?->role_name ? ucfirst($requestUser->role->role_name) : 'Unknown',
            'RequestDate' => optional($salesReturn->created_at)->format('F j, Y g:i A'),
        ];

        $transaction = $salesReturn->transaction;
        $receiptNumber = $transaction ? 'RCT-' . str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT) : null;

        $items = $salesReturn->items->map(function (SalesReturnItem $item) {
            return [
                'ProductName' => $item->product?->ProductName,
                'Barcode' => $item->product?->Barcode,
                'SKU' => $item->product?->SKU,
                'Category' => $item->product?->category?->CategoryName,
                'UnitPrice' => $item->UnitPrice,
                'Quantity' => $item->Quantity,
                'Reason' => $item->Reason,
                'LineTotal' => $item->line_total,
            ];
        });

        $totalRefundAmount = $salesReturn->RefundAmount ?? $items->sum('LineTotal');

        return response()->json([
            'transaction' => [
                'SalesTransactionID' => $transaction?->SalesTransactionID,
                'ReceiptNumber' => $receiptNumber,
                'InvoiceNumber' => $receiptNumber,
                'TransactionDate' => optional($transaction?->SalesTransactionDate)->format('Y-m-d H:i'),
                'CustomerName' => $salesReturn->CustomerName ?? $transaction?->CustomerName,
                'OriginalCashier' => $transaction?->staff?->user?->full_name,
                'PaymentMethod' => $transaction?->billing?->payment?->PaymentMethod,
            ],
            'items' => $items,
            'requestedBy' => $requestedBy,
            'return' => [
                'SalesReturnID' => $salesReturn->SalesReturnID,
                'ReturnType' => $salesReturn->ReturnType,
                'Remarks' => $salesReturn->Remarks,
                'TotalRefundAmount' => $totalRefundAmount,
                'ReturnDate' => $salesReturn->ReturnDate ? \Carbon\Carbon::parse($salesReturn->ReturnDate)->format('Y-m-d') : null,
                'Status' => $salesReturn->Status,
                'DeclineReason' => $salesReturn->DeclineReason,
                'ApprovedBy' => $salesReturn->approvedByUser?->full_name,
                'ProcessedBy' => $salesReturn->processedByUser?->full_name,
                'RefundMethod' => $salesReturn->RefundMethod,
                'RefundAmount' => $salesReturn->RefundAmount,
                'DaysSincePurchase' => $salesReturn->days_since_purchase,
                'ReturnWindowDays' => SalesReturn::RETURN_WINDOW_DAYS,
                'EligibleForReturn' => $salesReturn->is_within_return_window,
                'Replacement' => $salesReturn->replacement ? [
                    'ProductName' => $salesReturn->replacement->product?->ProductName,
                    'Quantity' => $salesReturn->replacement->Quantity,
                    'SlipNumber' => $salesReturn->replacement->SlipNumber,
                ] : null,
            ],
        ]);
    }

    public function approve(SalesReturn $salesReturn)
    {
        if ($salesReturn->Status !== SalesReturn::STATUS_PENDING) {
            return back()->with('status', 'Only pending returns can be approved.');
        }

        // Backstop for requests that predate the cashier-side window check
        // (or that theoretically slipped through some other path) — a
        // request outside the 7-day window can only be declined, not
        // approved.
        if ($salesReturn->is_within_return_window === false) {
            return back()->with('status', "This request was made {$salesReturn->days_since_purchase} day(s) after purchase, outside the " . SalesReturn::RETURN_WINDOW_DAYS . '-day return window, and can no longer be approved. Decline it instead.');
        }

        try {
            DB::transaction(function () use ($salesReturn) {
                // Re-fetch and lock the return, re-verifying it's still
                // pending under the lock — the check above ran before this
                // transaction opened, so a double-click or replayed request
                // could otherwise also pass it and create a second Damage
                // record for the same unsalable item (the unique constraint
                // on DamagedProduct(SalesReturnID, ProductID) is the backstop
                // if this check is ever bypassed some other way).
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

                // Factory Defect / Damaged Product units are unsalable — divert
                // them into the Damage module right now instead of ever letting
                // them flow back into Inventory. Inventory itself isn't touched
                // here: the unit was already decremented at original sale time,
                // and the only thing this prevents is CashierReturnController::
                // processRefund() incrementing it back later. Checked per item —
                // a single request can mix salable and unsalable products.
                foreach ($salesReturn->items as $item) {
                    if ($item->is_unsalable) {
                        $this->createDamageRecordForItem($salesReturn, $item);
                    }
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }

        $productSummary = $salesReturn->items->map(fn ($item) => "{$item->Quantity} x \"{$item->product?->ProductName}\"")->implode(', ');

        ActivityLog::record(
            'return.approved',
            "Approved {$salesReturn->ReturnType} #{$salesReturn->SalesReturnID} for {$productSummary} (Txn #{$salesReturn->SalesTransactionID})"
        );

        $this->notifyCashier($salesReturn, new ReturnRequestApproved($salesReturn));

        return back()->with('status', 'Return approved. The cashier can now process it.');
    }

    private function createDamageRecordForItem(SalesReturn $salesReturn, SalesReturnItem $item): void
    {
        // Best-effort: trace the product back to its most recent purchase
        // order to guess a supplier. A customer return isn't tied to any
        // specific PO, so this can legitimately come back null — SupplierID
        // is nullable for exactly this reason; admin can look it up manually
        // before returning the item to a supplier.
        $supplierId = PurchaseOrderItem::where('ProductID', $item->ProductID)
            ->join('PurchaseOrder', 'PurchaseOrder.PurchaseOrderID', '=', 'PurchaseOrderItem.PurchaseOrderID')
            ->orderByDesc('PurchaseOrder.PurchaseOrderID')
            ->value('PurchaseOrder.SupplierID');

        $receiptNumber = 'RCT-' . str_pad($salesReturn->SalesTransactionID, 6, '0', STR_PAD_LEFT);

        $damage = DamagedProduct::create([
            'ProductID' => $item->ProductID,
            'SalesReturnID' => $salesReturn->SalesReturnID,
            'SourceModule' => DamagedProduct::SOURCE_CUSTOMER_RETURN,
            'SupplierID' => $supplierId,
            'Quantity' => $item->Quantity,
            'Description' => "Customer return — {$item->Reason} (Return #{$salesReturn->SalesReturnID}, Receipt {$receiptNumber})",
            'DateRecorded' => now(),
            'DamageType' => $item->Reason === 'Factory Defect' ? 'factory_defect' : 'damaged_product',
            'Remarks' => $salesReturn->Remarks,
            'Status' => DamagedProduct::STATUS_FOR_SUPPLIER_RETURN,
        ]);

        ActivityLog::record(
            'damage.created_from_return',
            "Created damage record #{$damage->DamageID} for {$item->Quantity} x \"{$item->product?->ProductName}\" from return #{$salesReturn->SalesReturnID} ({$item->Reason})"
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

            ActivityLog::record('return.declined', "Declined return #{$salesReturn->SalesReturnID}: {$data['DeclineReason']}");
        } catch (Throwable $e) {
            Log::error('Failed to decline sales return', ['sales_return_id' => $salesReturn->SalesReturnID, 'exception' => $e->getMessage()]);

            return back()->with('status', 'Failed to decline the return. Please try again.');
        }

        $this->notifyCashier($salesReturn, new ReturnRequestDeclined($salesReturn));

        return back()->with('status', 'Return request declined.');
    }

    // A notification failure (broken mail transport, queue connection down)
    // must not turn a successful approve/decline into a 500 response.
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
