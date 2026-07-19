<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DamagedProduct;
use App\Models\PurchaseOrderItem;
use App\Models\SalesItem;
use App\Models\SalesReturn;
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
                'product.category',
                'staff.user',
                'approvedByUser',
                'processedByUser',
                'replacement.product',
            ])
            ->when($search, function ($query, $search) {
                $query->where('Status', 'like', "%{$search}%")
                    ->orWhere('Reason', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($product) use ($search) {
                        $product->where('ProductName', 'like', "%{$search}%");
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
            'product.category',
            'staff.user',
            'approvedByUser',
            'processedByUser',
            'replacement.product',
        ]);

        $transaction = $salesReturn->transaction;
        $receiptNumber = $transaction ? 'RCT-' . str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT) : null;

        // The sale-time unit price (not the product's current price) is what
        // "Original Selling Price" and the refund total must be based on.
        $salesItem = SalesItem::where('SalesTransactionID', $salesReturn->SalesTransactionID)
            ->where('ProductID', $salesReturn->ProductID)
            ->first();

        $originalSellingPrice = $salesItem?->UnitPrice ?? $salesReturn->product?->Price;
        $totalRefundAmount = $salesReturn->RefundAmount ?? ($originalSellingPrice * $salesReturn->Quantity);

        return response()->json([
            'transaction' => [
                'SalesTransactionID' => $transaction?->SalesTransactionID,
                'ReceiptNumber' => $receiptNumber,
                'InvoiceNumber' => $receiptNumber,
                'TransactionDate' => optional($transaction?->SalesTransactionDate)->format('Y-m-d H:i'),
                'CustomerName' => $salesReturn->CustomerName ?? $transaction?->CustomerName,
                'OriginalCashier' => $transaction?->staff?->user?->name,
                'PaymentMethod' => $transaction?->billing?->payment?->PaymentMethod,
            ],
            'product' => [
                'ProductName' => $salesReturn->product?->ProductName,
                'Barcode' => $salesReturn->product?->Barcode,
                'SKU' => $salesReturn->product?->SKU,
                'Category' => $salesReturn->product?->category?->CategoryName,
                'SellingPrice' => $originalSellingPrice,
                'QuantityPurchased' => $salesItem?->Quantity,
            ],
            'return' => [
                'SalesReturnID' => $salesReturn->SalesReturnID,
                'Quantity' => $salesReturn->Quantity,
                'ReturnType' => $salesReturn->ReturnType,
                'Reason' => $salesReturn->Reason,
                'Remarks' => $salesReturn->Remarks,
                'TotalRefundAmount' => $totalRefundAmount,
                'ReturnDate' => $salesReturn->ReturnDate ? \Carbon\Carbon::parse($salesReturn->ReturnDate)->format('Y-m-d') : null,
                'Status' => $salesReturn->Status,
                'DeclineReason' => $salesReturn->DeclineReason,
                'ApprovedBy' => $salesReturn->approvedByUser?->name,
                'ProcessedBy' => $salesReturn->processedByUser?->name,
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

        DB::transaction(function () use ($salesReturn) {
            $salesReturn->update([
                'Status' => SalesReturn::STATUS_APPROVED,
                'ApprovedBy' => auth()->id(),
            ]);

            // Factory Defect / Damaged Product units are unsalable — divert
            // them into the Damage module right now instead of ever letting
            // them flow back into Inventory. Inventory itself isn't touched
            // here: the unit was already decremented at original sale time,
            // and the only thing this prevents is CashierReturnController::
            // processRefund() incrementing it back later.
            if ($salesReturn->is_unsalable_return) {
                $this->createDamageRecordForReturn($salesReturn);
            }
        });

        ActivityLog::record(
            'return.approved',
            "Approved {$salesReturn->ReturnType} #{$salesReturn->SalesReturnID} for {$salesReturn->Quantity} x \"{$salesReturn->product?->ProductName}\" (Txn #{$salesReturn->SalesTransactionID})"
        );

        $this->notifyCashier($salesReturn, new ReturnRequestApproved($salesReturn));

        return back()->with('status', 'Return approved. The cashier can now process it.');
    }

    private function createDamageRecordForReturn(SalesReturn $salesReturn): void
    {
        // Best-effort: trace the product back to its most recent purchase
        // order to guess a supplier. A customer return isn't tied to any
        // specific PO, so this can legitimately come back null — SupplierID
        // is nullable for exactly this reason; admin can look it up manually
        // before returning the item to a supplier.
        $supplierId = PurchaseOrderItem::where('ProductID', $salesReturn->ProductID)
            ->join('PurchaseOrder', 'PurchaseOrder.PurchaseOrderID', '=', 'PurchaseOrderItem.PurchaseOrderID')
            ->orderByDesc('PurchaseOrder.PurchaseOrderID')
            ->value('PurchaseOrder.SupplierID');

        $receiptNumber = 'RCT-' . str_pad($salesReturn->SalesTransactionID, 6, '0', STR_PAD_LEFT);

        $damage = DamagedProduct::create([
            'ProductID' => $salesReturn->ProductID,
            'SalesReturnID' => $salesReturn->SalesReturnID,
            'SupplierID' => $supplierId,
            'Quantity' => $salesReturn->Quantity,
            'Description' => "Customer return — {$salesReturn->Reason} (Return #{$salesReturn->SalesReturnID}, Receipt {$receiptNumber})",
            'DateRecorded' => now(),
            'DamageType' => $salesReturn->Reason === 'Factory Defect' ? 'factory_defect' : 'damaged_product',
            'Remarks' => $salesReturn->Remarks,
            'Status' => DamagedProduct::STATUS_FOR_SUPPLIER_RETURN,
        ]);

        ActivityLog::record(
            'damage.created_from_return',
            "Created damage record #{$damage->DamageID} for {$salesReturn->Quantity} x \"{$salesReturn->product?->ProductName}\" from return #{$salesReturn->SalesReturnID} ({$salesReturn->Reason})"
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

        $salesReturn->update([
            'Status' => SalesReturn::STATUS_DECLINED,
            'ApprovedBy' => auth()->id(),
            'DeclineReason' => $data['DeclineReason'],
        ]);

        ActivityLog::record('return.declined', "Declined return #{$salesReturn->SalesReturnID}: {$data['DeclineReason']}");

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
