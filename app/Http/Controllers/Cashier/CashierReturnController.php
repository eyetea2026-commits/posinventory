<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Replacement;
use App\Models\SalesItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\SalesTransaction;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\NewRefundRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class CashierReturnController extends Controller
{
    public function __construct()
    {
        // Route-level role:cashier middleware already gates every route
        // here — this constructor-level check is defense-in-depth, matching
        // the same double-gate pattern every other controller in the app
        // uses (this was previously the one controller without it).
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (! auth()->user() || ! auth()->user()->isCashier()) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $staff = Staff::where('UserID', $user->id)->first();

        $search = $request->get('search');
        $status = $request->get('status');
        $returnType = $request->get('return_type');

        $refunds = SalesReturn::with(['staff', 'salesTransaction', 'product', 'items.product', 'replacement.product'])
            ->where('StaffID', $staff->StaffID ?? 0)
            ->when($search, function ($query) use ($search) {
                return $query->where('CustomerName', 'like', "%{$search}%")
                    ->orWhere('Reason', 'like', "%{$search}%");
            })
            ->when($status, function ($query) use ($status) {
                return $query->where('Status', $status);
            })
            ->when($returnType, function ($query) use ($returnType) {
                return $query->where('ReturnType', $returnType);
            })
            ->orderBy('SalesReturnID', 'desc')
            ->get();

        return view('cashier.refunds', compact('refunds', 'search', 'status', 'returnType'));
    }

    /**
     * Search for a transaction by receipt/invoice number, customer name, or barcode.
     */
    public function searchTransaction(Request $request)
    {
        $data = $request->validate([
            'mode' => 'required|in:receipt,invoice,customer,barcode',
            'q' => 'required|string|max:100',
        ]);

        $mode = $data['mode'];
        $query = trim($data['q']);

        if ($mode === 'receipt' || $mode === 'invoice') {
            $transactionId = (int) preg_replace('/[^0-9]/', '', $query);

            if ($transactionId <= 0) {
                return response()->json(['success' => false, 'message' => 'Enter a valid receipt/invoice number.'], 400);
            }

            $transaction = SalesTransaction::with(['items.product.category', 'billing.payment', 'staff.user'])
                ->find($transactionId);

            if (!$transaction) {
                return response()->json(['success' => false, 'message' => 'Transaction not found.'], 404);
            }

            return response()->json(['success' => true, 'multiple' => false, 'transaction' => $this->buildTransactionDetails($transaction)]);
        }

        if ($mode === 'customer') {
            $transactions = SalesTransaction::where('CustomerName', 'like', "%{$query}%")
                ->orderByDesc('SalesTransactionDate')
                ->take(20)
                ->get();

            if ($transactions->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No transactions found for that customer.'], 404);
            }

            if ($transactions->count() === 1) {
                $transaction = $transactions->first()->load(['items.product.category', 'billing.payment', 'staff.user']);
                return response()->json(['success' => true, 'multiple' => false, 'transaction' => $this->buildTransactionDetails($transaction)]);
            }

            return response()->json(['success' => true, 'multiple' => true, 'matches' => $transactions->map(function ($t) {
                return [
                    'SalesTransactionID' => $t->SalesTransactionID,
                    'ReceiptNumber' => 'RCT-' . str_pad($t->SalesTransactionID, 6, '0', STR_PAD_LEFT),
                    'CustomerName' => $t->CustomerName,
                    'TransactionDate' => optional($t->SalesTransactionDate)->format('Y-m-d H:i'),
                ];
            })]);
        }

        // barcode
        $product = Product::where('Barcode', $query)->first();
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'No product found with that barcode.'], 404);
        }

        $transactionIds = SalesItem::where('ProductID', $product->ProductID)
            ->orderByDesc('SalesItemID')
            ->take(20)
            ->pluck('SalesTransactionID');

        $transactions = SalesTransaction::whereIn('SalesTransactionID', $transactionIds)
            ->orderByDesc('SalesTransactionDate')
            ->get();

        if ($transactions->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No transactions found for that barcode.'], 404);
        }

        if ($transactions->count() === 1) {
            $transaction = $transactions->first()->load(['items.product.category', 'billing.payment', 'staff.user']);
            return response()->json(['success' => true, 'multiple' => false, 'transaction' => $this->buildTransactionDetails($transaction)]);
        }

        return response()->json(['success' => true, 'multiple' => true, 'matches' => $transactions->map(function ($t) {
            return [
                'SalesTransactionID' => $t->SalesTransactionID,
                'ReceiptNumber' => 'RCT-' . str_pad($t->SalesTransactionID, 6, '0', STR_PAD_LEFT),
                'CustomerName' => $t->CustomerName,
                'TransactionDate' => optional($t->SalesTransactionDate)->format('Y-m-d H:i'),
            ];
        })]);
    }

    /**
     * Get transaction details for the refund/replacement request form.
     */
    public function getTransactionDetails($transactionId)
    {
        $transaction = SalesTransaction::with(['items.product.category', 'billing.payment', 'staff.user'])
            ->where('SalesTransactionID', $transactionId)
            ->first();

        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found.'], 404);
        }

        return response()->json(['success' => true, 'multiple' => false, 'transaction' => $this->buildTransactionDetails($transaction)]);
    }

    private function buildTransactionDetails(SalesTransaction $transaction): array
    {
        $receiptNumber = 'RCT-' . str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT);

        $items = $transaction->items->map(function ($item) use ($transaction) {
            $alreadyRequested = SalesReturn::where('SalesTransactionID', $transaction->SalesTransactionID)
                ->where('ProductID', $item->ProductID)
                ->where('Status', '!=', SalesReturn::STATUS_DECLINED)
                ->sum('Quantity');

            return [
                'ProductID' => $item->ProductID,
                'ProductName' => $item->product?->ProductName ?? 'Unknown',
                'Barcode' => $item->product?->Barcode,
                'SKU' => $item->product?->SKU,
                'Category' => $item->product?->category?->CategoryName,
                'CategoryID' => $item->product?->CategoryID,
                'QuantityPurchased' => $item->Quantity,
                'RemainingReturnableQty' => max(0, $item->Quantity - $alreadyRequested),
                'UnitPrice' => $item->UnitPrice,
                'TotalPrice' => $item->Quantity * $item->UnitPrice,
            ];
        });

        return [
            'SalesTransactionID' => $transaction->SalesTransactionID,
            'OriginalTransactionID' => $transaction->SalesTransactionID,
            'ReceiptNumber' => $receiptNumber,
            'InvoiceNumber' => $receiptNumber,
            'TransactionDate' => optional($transaction->SalesTransactionDate)->format('Y-m-d H:i'),
            'CustomerName' => $transaction->CustomerName,
            'PaymentMethod' => $transaction->billing?->payment?->PaymentMethod,
            'OriginalCashier' => $transaction->staff?->user?->full_name,
            'items' => $items,
        ];
    }

    /**
     * Create a new return request (Refund) — pending admin approval.
     */
    public function createRefund(Request $request)
    {
        $data = $request->validate([
            'transaction_id' => 'required|integer|exists:SalesTransaction,SalesTransactionID',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:Product,ProductID',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.reason_code' => 'required|in:' . implode(',', array_keys(SalesReturn::REASON_CODES)),
            // Replacement retired as a request-time option (2026-07-19) — cashiers can
            // no longer originate one. processReplacement() below is left intact so
            // any pre-existing replacement-type SalesReturn rows can still be processed.
            'return_type' => 'required|in:refund',
            'remarks' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $staff = Staff::where('UserID', $user->id)->first();

        $transaction = SalesTransaction::find($data['transaction_id']);

        // The 7-day return policy window is measured from the original sale
        // date to today (the same math SalesReturn::days_since_purchase uses
        // once a request exists) — checked here before a request record is
        // even created, so a late request can't be submitted at all.
        $daysSincePurchase = \Carbon\Carbon::parse($transaction->SalesTransactionDate)->startOfDay()
            ->diffInDays(now()->startOfDay());

        if ($daysSincePurchase > SalesReturn::RETURN_WINDOW_DAYS) {
            return response()->json([
                'success' => false,
                'message' => "This purchase was made {$daysSincePurchase} day(s) ago, outside the " . SalesReturn::RETURN_WINDOW_DAYS . '-day return window. It can no longer be returned.',
            ], 400);
        }

        $lines = [];

        foreach ($data['items'] as $itemData) {
            $salesItem = SalesItem::where('SalesTransactionID', $data['transaction_id'])
                ->where('ProductID', $itemData['product_id'])
                ->first();

            if (!$salesItem) {
                return response()->json(['success' => false, 'message' => 'Product not found in transaction.'], 400);
            }

            // A transaction can only be returned up to the quantity actually sold —
            // without this, repeated return requests for the same line item could
            // each get approved and inflate inventory/payouts beyond what was sold.
            $alreadyRequested = SalesReturnItem::where('ProductID', $itemData['product_id'])
                ->whereHas('salesReturn', function ($q) use ($data) {
                    $q->where('SalesTransactionID', $data['transaction_id'])
                        ->where('Status', '!=', SalesReturn::STATUS_DECLINED);
                })
                ->sum('Quantity');

            if ($alreadyRequested + $itemData['quantity'] > $salesItem->Quantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Return quantity exceeds the quantity sold for \"{$salesItem->product?->ProductName}\".",
                ], 400);
            }

            $lines[] = [
                'salesItem' => $salesItem,
                'productId' => $itemData['product_id'],
                'quantity' => $itemData['quantity'],
                'reasonLabel' => SalesReturn::REASON_CODES[$itemData['reason_code']],
            ];
        }

        $salesReturn = SalesReturn::create([
            'SalesTransactionID' => $data['transaction_id'],
            'Remarks' => $data['remarks'] ?? null,
            'ReturnType' => $data['return_type'],
            'ReturnDate' => now()->format('Y-m-d'),
            'Status' => SalesReturn::STATUS_PENDING,
            'StaffID' => $staff->StaffID ?? null,
            'CustomerName' => $transaction->CustomerName,
        ]);

        $refundAmount = 0;
        $productNames = [];

        foreach ($lines as $line) {
            SalesReturnItem::create([
                'SalesReturnID' => $salesReturn->SalesReturnID,
                'ProductID' => $line['productId'],
                'Quantity' => $line['quantity'],
                'UnitPrice' => $line['salesItem']->UnitPrice,
                'Reason' => $line['reasonLabel'],
            ]);

            $refundAmount += $line['salesItem']->UnitPrice * $line['quantity'];
            $productNames[] = "{$line['quantity']} x \"{$line['salesItem']->product?->ProductName}\"";
        }

        ActivityLog::record('return.requested', "Requested {$data['return_type']} #{$salesReturn->SalesReturnID} for " . implode(', ', $productNames) . " (Txn #{$data['transaction_id']})");

        // The return request record already committed above — a
        // notification failure (broken mail transport, queue connection
        // down) must not turn a successful submission into a 500 response
        // that could prompt the cashier to retry and create a duplicate
        // request.
        try {
            Notification::send(User::admins(), new NewRefundRequest($salesReturn));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch NewRefundRequest notification', [
                'sales_return_id' => $salesReturn->SalesReturnID,
                'exception' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Return request submitted successfully. Awaiting admin approval.',
            'refund_id' => $salesReturn->SalesReturnID,
            'refund_amount' => $refundAmount,
        ]);
    }

    /**
     * Process a refund after admin approval.
     */
    public function processRefund(Request $request, $salesReturnId)
    {
        $data = $request->validate([
            'refund_method' => 'required|in:cash,gcash,bank,cheque',
            'account_number' => 'nullable|string|max:50',
        ]);

        $salesReturn = SalesReturn::find($salesReturnId);

        if (!$salesReturn) {
            return response()->json(['success' => false, 'message' => 'Refund not found.'], 404);
        }

        if ($salesReturn->Status !== SalesReturn::STATUS_APPROVED || $salesReturn->ReturnType !== SalesReturn::TYPE_REFUND) {
            return response()->json(['success' => false, 'message' => 'Refund must be an approved refund-type request before processing.'], 400);
        }

        try {
            $refundAmount = DB::transaction(function () use ($salesReturnId, $data) {
                // Re-fetch and lock the SalesReturn row itself (the earlier
                // check above is only a fast-fail before the lock exists).
                // Without this, two near-simultaneous requests for the same
                // return can both read Status=approved before either commits,
                // and both process the same refund — this lock plus the
                // re-check below serializes them so only the first succeeds.
                $salesReturn = SalesReturn::where('SalesReturnID', $salesReturnId)->lockForUpdate()->first();

                if (!$salesReturn || $salesReturn->Status !== SalesReturn::STATUS_APPROVED || $salesReturn->ReturnType !== SalesReturn::TYPE_REFUND) {
                    throw new \RuntimeException('This refund has already been processed or is no longer approved.');
                }

                // Recompute the payout from the original sale instead of
                // trusting the client-submitted amount — that field is only
                // pre-filled for display and is editable in the browser
                // before this request is sent. Looped per line item since a
                // request can cover multiple products.
                $refundAmount = 0;

                foreach ($salesReturn->items as $item) {
                    $salesItem = SalesItem::where('SalesTransactionID', $salesReturn->SalesTransactionID)
                        ->where('ProductID', $item->ProductID)
                        ->first();

                    // The customer is refunded in cash either way — Factory Defect /
                    // Damaged Product units just skip going back into Inventory
                    // (they were already diverted into the Damage module when the
                    // admin approved this return; see SalesReturnController::approve()).
                    // Every other reason also restores the unit to stock.
                    $refundAmount += $salesItem ? ($salesItem->UnitPrice * $item->Quantity) : 0;

                    if ($item->is_unsalable) {
                        continue;
                    }

                    $inventory = Inventory::where('ProductID', $item->ProductID)->lockForUpdate()->first();
                    if (!$inventory) {
                        $inventory = Inventory::firstOrCreate(
                            ['ProductID' => $item->ProductID],
                            ['Quantity' => 0, 'Status' => 'Out of Stock']
                        );
                    }

                    $inventory->Quantity += $item->Quantity;
                    $inventory->Status = $inventory->Quantity > 0 ? ($inventory->Quantity <= 10 ? 'Low Stock' : 'Available') : 'Out of Stock';
                    $inventory->save();
                }

                $salesReturn->update([
                    'RefundMethod' => $data['refund_method'],
                    'RefundAmount' => $refundAmount,
                    'RefundAccountNumber' => $data['account_number'] ?? null,
                    'RefundDate' => now()->format('Y-m-d'),
                    'Status' => SalesReturn::STATUS_PROCESSED,
                    'ProcessedBy' => auth()->id(),
                ]);

                return $refundAmount;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }

        ActivityLog::record('return.refund_processed', "Processed refund #{$salesReturnId} — ₱" . number_format((float) $refundAmount, 2) . " via {$data['refund_method']} (Txn #{$salesReturn->SalesTransactionID})");

        return response()->json([
            'success' => true,
            'message' => 'Refund processed successfully.',
            'receipt_number' => 'RFD-' . str_pad($salesReturn->SalesReturnID, 6, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Search inventory for a replacement product (optionally scoped to a category).
     */
    public function searchReplacementInventory(Request $request)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'category_id' => 'nullable|integer',
        ]);

        $products = Product::with('inventory')
            ->when($data['q'] ?? null, function ($query, $q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('ProductName', 'like', "%{$q}%")
                        ->orWhere('Barcode', $q)
                        ->orWhere('SKU', $q);
                });
            })
            ->when($data['category_id'] ?? null, function ($query, $categoryId) {
                $query->where('CategoryID', $categoryId);
            })
            ->orderBy('ProductName')
            ->take(30)
            ->get()
            ->map(function ($product) {
                return [
                    'ProductID' => $product->ProductID,
                    'ProductName' => $product->ProductName,
                    'Barcode' => $product->Barcode,
                    'SKU' => $product->SKU,
                    'Price' => $product->Price,
                    'Stock' => $product->inventory?->Quantity ?? 0,
                ];
            });

        return response()->json(['success' => true, 'products' => $products]);
    }

    /**
     * Process a replacement after admin approval.
     */
    public function processReplacement(Request $request, $salesReturnId)
    {
        $data = $request->validate([
            'replacement_product_id' => 'required|integer|exists:Product,ProductID',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:255',
        ]);

        $salesReturn = SalesReturn::find($salesReturnId);

        if (!$salesReturn) {
            return response()->json(['success' => false, 'message' => 'Return request not found.'], 404);
        }

        if ($salesReturn->Status !== SalesReturn::STATUS_APPROVED || $salesReturn->ReturnType !== SalesReturn::TYPE_REPLACEMENT) {
            return response()->json(['success' => false, 'message' => 'Return must be an approved replacement-type request before processing.'], 400);
        }

        if ($data['quantity'] > $salesReturn->Quantity) {
            return response()->json(['success' => false, 'message' => 'Replacement quantity cannot exceed the approved return quantity.'], 400);
        }

        try {
            $replacement = DB::transaction(function () use ($salesReturnId, $data) {
                // Re-fetch and lock the SalesReturn row itself (the earlier
                // check above is only a fast-fail before the lock exists).
                // Without this, two near-simultaneous requests for the same
                // return can both read Status=approved before either
                // commits, and both process the same replacement — this
                // lock plus the re-check below serializes them so only the
                // first succeeds.
                $salesReturn = SalesReturn::where('SalesReturnID', $salesReturnId)->lockForUpdate()->first();

                if (!$salesReturn || $salesReturn->Status !== SalesReturn::STATUS_APPROVED || $salesReturn->ReturnType !== SalesReturn::TYPE_REPLACEMENT) {
                    throw new \RuntimeException('This return has already been processed or is no longer approved.');
                }

                if ($data['quantity'] > $salesReturn->Quantity) {
                    throw new \RuntimeException('Replacement quantity cannot exceed the approved return quantity.');
                }

                $inventory = Inventory::where('ProductID', $data['replacement_product_id'])->lockForUpdate()->first();

                if (!$inventory || $inventory->Quantity < $data['quantity']) {
                    throw new \RuntimeException('Insufficient stock for the selected replacement item. Available: ' . ($inventory?->Quantity ?? 0));
                }

                $inventory->Quantity -= $data['quantity'];
                $inventory->Status = $inventory->Quantity > 0 ? ($inventory->Quantity <= 10 ? 'Low Stock' : 'Available') : 'Out of Stock';
                $inventory->save();

                $slipNumber = 'RPL-' . str_pad($salesReturn->SalesReturnID, 6, '0', STR_PAD_LEFT);

                $replacement = Replacement::create([
                    'SalesReturnID' => $salesReturn->SalesReturnID,
                    'ReplacementProductID' => $data['replacement_product_id'],
                    'Quantity' => $data['quantity'],
                    'ProcessedBy' => auth()->id(),
                    'ReplacementDate' => now()->format('Y-m-d'),
                    'SlipNumber' => $slipNumber,
                    'Notes' => $data['notes'] ?? null,
                ]);

                $salesReturn->update([
                    'Status' => SalesReturn::STATUS_PROCESSED,
                    'ProcessedBy' => auth()->id(),
                ]);

                return $replacement;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }

        $replacementProduct = Product::find($data['replacement_product_id']);
        ActivityLog::record('return.replacement_processed', "Processed replacement for return #{$salesReturn->SalesReturnID}: {$data['quantity']} x \"{$replacementProduct?->ProductName}\" (slip {$replacement->SlipNumber})");

        return response()->json([
            'success' => true,
            'message' => 'Replacement processed successfully.',
            'slip_number' => $replacement->SlipNumber,
        ]);
    }

    public function printRefundReceipt($salesReturnId)
    {
        $salesReturn = SalesReturn::with(['items.product', 'salesTransaction', 'processedByUser'])
            ->findOrFail($salesReturnId);

        if ($salesReturn->Status !== SalesReturn::STATUS_PROCESSED || $salesReturn->ReturnType !== SalesReturn::TYPE_REFUND) {
            abort(404, 'Refund receipt not found');
        }

        return view('cashier.refund-receipt', [
            'salesReturn' => $salesReturn,
            'receiptNumber' => 'RFD-' . str_pad($salesReturn->SalesReturnID, 6, '0', STR_PAD_LEFT),
        ]);
    }

    public function printReplacementSlip($salesReturnId)
    {
        $salesReturn = SalesReturn::with(['product', 'items.product', 'salesTransaction', 'replacement.product', 'replacement.processedByUser'])
            ->findOrFail($salesReturnId);

        if (!$salesReturn->replacement) {
            abort(404, 'Replacement slip not found');
        }

        return view('cashier.replacement-slip', ['salesReturn' => $salesReturn, 'replacement' => $salesReturn->replacement]);
    }

    /**
     * Get refund/replacement details.
     */
    public function getRefundDetails($refundId)
    {
        $refund = SalesReturn::with(['transaction', 'items.product', 'replacement.product'])
            ->where('SalesReturnID', $refundId)
            ->first();

        if (!$refund) {
            return response()->json(['success' => false, 'message' => 'Refund not found.'], 404);
        }

        $items = $refund->items->map(function (SalesReturnItem $item) {
            return [
                'product_name' => $item->product?->ProductName ?? 'Unknown',
                'quantity' => $item->Quantity,
                'reason' => $item->Reason,
                'unit_price' => $item->UnitPrice,
                'line_total' => $item->line_total,
            ];
        });

        return response()->json([
            'success' => true,
            'refund' => [
                'id' => $refund->SalesReturnID,
                'transaction_id' => $refund->SalesTransactionID,
                'items' => $items,
                'remarks' => $refund->Remarks,
                'return_type' => $refund->ReturnType,
                'status' => $refund->Status,
                'decline_reason' => $refund->DeclineReason,
                'return_date' => $refund->ReturnDate,
                'refund_amount' => $items->sum('line_total'),
                'refund_method' => $refund->RefundMethod,
                'refund_date' => $refund->RefundDate,
                'replacement' => $refund->replacement ? [
                    'product_name' => $refund->replacement->product?->ProductName,
                    'quantity' => $refund->replacement->Quantity,
                    'slip_number' => $refund->replacement->SlipNumber,
                ] : null,
            ],
        ]);
    }

    /**
     * Get cashier return/refund stats for the dashboard.
     */
    public function getCashierStats()
    {
        $user = Auth::user();
        $staff = Staff::where('UserID', $user->id)->first();

        if (!$staff) {
            return response()->json([
                'total_refunds' => 0,
                'pending_refunds' => 0,
                'approved_refunds' => 0,
                'declined_refunds' => 0,
                'processed_refunds' => 0,
                'awaiting_action' => 0,
            ]);
        }

        $base = SalesReturn::where('StaffID', $staff->StaffID);

        return response()->json([
            'total_refunds' => (clone $base)->count(),
            'pending_refunds' => (clone $base)->where('Status', SalesReturn::STATUS_PENDING)->count(),
            'approved_refunds' => (clone $base)->where('Status', SalesReturn::STATUS_APPROVED)->count(),
            'declined_refunds' => (clone $base)->where('Status', SalesReturn::STATUS_DECLINED)->count(),
            'processed_refunds' => (clone $base)->where('Status', SalesReturn::STATUS_PROCESSED)->count(),
            // Approved-but-not-yet-processed: surfaces newly-approved admin decisions the cashier still needs to act on.
            'awaiting_action' => (clone $base)->where('Status', SalesReturn::STATUS_APPROVED)->count(),
        ]);
    }
}
