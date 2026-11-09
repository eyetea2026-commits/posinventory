<?php

namespace App\Http\Controllers\Cashier;

use App\Casts\Quantity;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\SalesItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\SalesTransaction;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\NewRefundRequest;
use App\Services\ActionNotifier;
use App\Services\SerialNumbers;
use App\Services\StockBatches;
use App\Services\ThermalReceiptPrinter;
use App\Services\UnitOfMeasure;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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

    // Resolves the logged-in cashier's own Staff row -- 0 (never a real
    // StaffID) when none exists yet, so an ownership-scoped query naturally
    // returns nothing instead of matching every SalesReturn with a null
    // StaffID would.
    private function currentStaffId(): int
    {
        return Staff::where('UserID', Auth::id())->value('StaffID') ?? 0;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $staff = Staff::where('UserID', $user->id)->first();

        $search = $request->get('search');
        $status = $request->get('status');
        $returnType = $request->get('return_type');

        $refunds = SalesReturn::with(['staff', 'salesTransaction', 'product', 'items.product'])
            ->where('StaffID', $staff->StaffID ?? 0)
            ->when($search, function ($query) use ($search) {
                // Grouped in its own closure -- an ungrouped orWhere() here
                // would break out of the StaffID scope above and leak every
                // cashier's returns whose Reason happens to match $search.
                return $query->where(function ($inner) use ($search) {
                    $inner->where('CustomerName', 'like', "%{$search}%")
                        ->orWhere('Reason', 'like', "%{$search}%");
                });
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

            $transaction = SalesTransaction::with(['items.product.category', 'payment', 'staff.user'])
                ->find($transactionId);

            if (! $transaction) {
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
                $transaction = $transactions->first()->load(['items.product.category', 'payment', 'staff.user']);

                return response()->json(['success' => true, 'multiple' => false, 'transaction' => $this->buildTransactionDetails($transaction)]);
            }

            return response()->json(['success' => true, 'multiple' => true, 'matches' => $transactions->map(function ($t) {
                return [
                    'SalesTransactionID' => $t->SalesTransactionID,
                    'ReceiptNumber' => 'RCT-'.str_pad($t->SalesTransactionID, 6, '0', STR_PAD_LEFT),
                    'CustomerName' => $t->CustomerName,
                    'TransactionDate' => optional($t->SalesTransactionDate)->format('m/d/Y h:i A'),
                ];
            })]);
        }

        // SKU or barcode
        $product = Product::where('Barcode', $query)->orWhere('SKU', $query)->first();

        // The QR code at the bottom of every receipt holds its receipt
        // number (e.g. RCT-000066): scanning it here opens that receipt.
        if (! $product && preg_match('/^RCT-?\d+$/i', $query)) {
            $transaction = SalesTransaction::with(['items.product.category', 'payment', 'staff.user'])
                ->find((int) preg_replace('/[^0-9]/', '', $query));

            if (! $transaction) {
                return response()->json(['success' => false, 'message' => 'Transaction not found.'], 404);
            }

            return response()->json(['success' => true, 'multiple' => false, 'transaction' => $this->buildTransactionDetails($transaction)]);
        }

        if (! $product) {
            return response()->json(['success' => false, 'message' => 'No product found with that SKU or barcode.'], 404);
        }

        $transactionIds = SalesItem::where('ProductID', $product->ProductID)
            ->orderByDesc('SalesItemID')
            ->take(20)
            ->pluck('SalesTransactionID');

        $transactions = SalesTransaction::whereIn('SalesTransactionID', $transactionIds)
            ->orderByDesc('SalesTransactionDate')
            ->get();

        if ($transactions->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No transactions found for that SKU or barcode.'], 404);
        }

        if ($transactions->count() === 1) {
            $transaction = $transactions->first()->load(['items.product.category', 'payment', 'staff.user']);

            return response()->json(['success' => true, 'multiple' => false, 'transaction' => $this->buildTransactionDetails($transaction)]);
        }

        return response()->json(['success' => true, 'multiple' => true, 'matches' => $transactions->map(function ($t) {
            return [
                'SalesTransactionID' => $t->SalesTransactionID,
                'ReceiptNumber' => 'RCT-'.str_pad($t->SalesTransactionID, 6, '0', STR_PAD_LEFT),
                'CustomerName' => $t->CustomerName,
                'TransactionDate' => optional($t->SalesTransactionDate)->format('m/d/Y h:i A'),
            ];
        })]);
    }

    /**
     * Get transaction details for the refund request form.
     */
    public function getTransactionDetails($transactionId)
    {
        $transaction = SalesTransaction::with(['items.product.category', 'payment', 'staff.user'])
            ->where('SalesTransactionID', $transactionId)
            ->first();

        if (! $transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found.'], 404);
        }

        return response()->json(['success' => true, 'multiple' => false, 'transaction' => $this->buildTransactionDetails($transaction)]);
    }

    private function buildTransactionDetails(SalesTransaction $transaction): array
    {
        $receiptNumber = 'RCT-'.str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT);

        // One row per product: a product sold from stock batches at
        // different prices has one sale line per price.
        $items = $transaction->items->groupBy('ProductID')->map(function ($lines) use ($transaction) {
            $item = $lines->first();
            $quantityPurchased = Quantity::normalize($lines->sum(fn ($line) => (float) $line->Quantity));
            $totalPrice = round($lines->sum(fn ($line) => (float) $line->Quantity * (float) $line->UnitPrice), 2);
            // Same count createRefund() enforces: every not-declined return
            // request line for this product on this sale.
            $alreadyRequested = (float) SalesReturnItem::where('ProductID', $item->ProductID)
                ->whereHas('salesReturn', function ($q) use ($transaction) {
                    $q->where('SalesTransactionID', $transaction->SalesTransactionID)
                        ->where('Status', '!=', SalesReturn::STATUS_DECLINED);
                })
                ->sum('Quantity');
            $unit = $item->Unit ?: UnitOfMeasure::DEFAULT;

            return [
                'ProductID' => $item->ProductID,
                'ProductName' => $item->product?->ProductName ?? 'Unknown',
                'SKU' => $item->product?->SKU,
                'Barcode' => $item->product?->Barcode,
                'Category' => $item->product?->category?->CategoryName,
                'CategoryID' => $item->product?->CategoryID,
                // In the unit it was sold in ("1.5 meters", "1 box").
                'QuantityPurchased' => $quantityPurchased,
                'RemainingReturnableQty' => Quantity::normalize(max(0, $quantityPurchased - $alreadyRequested)),
                'Unit' => $unit,
                'UnitPlural' => UnitOfMeasure::plural($unit),
                'AllowsDecimals' => $this->returnAllowsDecimals($item),
                // Tagged "Not returnable / refundable" (consumables, wires, fiber connectors).
                'IsReturnable' => (bool) ($item->product?->IsReturnable ?? true),
                // The average when its pieces sold at different batch prices.
                'UnitPrice' => (float) $quantityPurchased > 0 ? round($totalPrice / (float) $quantityPurchased, 2) : (float) $item->UnitPrice,
                'TotalPrice' => $totalPrice,
                // Serial-numbered: the sticker of each piece coming back is
                // picked, so a damaged one is never sold again.
                'TracksSerials' => (bool) $item->product?->tracksSerialNumbers(),
                'SerialNumbers' => $item->product?->tracksSerialNumbers()
                    ? ProductSerial::returnableFor($transaction->SalesTransactionID, $item->ProductID)->pluck('SerialNumber')->values()->all()
                    : [],
            ];
        })->values();

        return [
            'SalesTransactionID' => $transaction->SalesTransactionID,
            'OriginalTransactionID' => $transaction->SalesTransactionID,
            'ReceiptNumber' => $receiptNumber,
            'InvoiceNumber' => $receiptNumber,
            'TransactionDate' => optional($transaction->SalesTransactionDate)->format('m/d/Y h:i A'),
            'CustomerName' => $transaction->CustomerName,
            'PaymentMethod' => $transaction->payment?->PaymentMethod,
            'OriginalCashier' => $transaction->staff?->user?->full_name,
            'items' => $items,
        ];
    }

    /**
     * Part of a unit (0.5 of the 1.5 meters bought) can only come back when
     * it was sold in a unit that allows decimals.
     */
    private function returnAllowsDecimals(SalesItem $salesItem): bool
    {
        $unit = $salesItem->Unit ?: UnitOfMeasure::DEFAULT;

        return (bool) ($salesItem->product?->sellingUnits()[$unit]['fractional'] ?? false)
            || floor((float) $salesItem->Quantity) != (float) $salesItem->Quantity;
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
            // In the unit the product was sold in (meters, boxes...).
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            // Always Factory Defect: set automatically when it isn't sent.
            'items.*.reason_code' => 'nullable|in:'.implode(',', array_keys(SalesReturn::REASON_CODES)),
            // Serial-numbered products: the sticker of each piece returned.
            'items.*.serials' => 'nullable|array',
            'items.*.serials.*' => 'string|max:20',
            // Every return is settled by a refund (replacements were retired
            // on 2026-07-19 and their table removed on 2026-10-29).
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
        $daysSincePurchase = Carbon::parse($transaction->SalesTransactionDate)->startOfDay()
            ->diffInDays(now()->startOfDay());

        if ($daysSincePurchase > SalesReturn::RETURN_WINDOW_DAYS) {
            return response()->json([
                'success' => false,
                'message' => "This purchase was made {$daysSincePurchase} day(s) ago, outside the ".SalesReturn::RETURN_WINDOW_DAYS.'-day return window. It can no longer be returned.',
            ], 400);
        }

        // Products tagged "Not returnable / refundable" can't be on a return
        // request (checked here too, not only by the disabled checkbox).
        $nonReturnable = Product::whereIn('ProductID', collect($data['items'])->pluck('product_id'))
            ->where('IsReturnable', false)
            ->pluck('ProductName');
        if ($nonReturnable->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => $nonReturnable->implode(', ').($nonReturnable->count() === 1 ? ' is' : ' are').' not returnable / refundable.',
            ], 400);
        }

        // Guards against duplicate submissions the disabled-submit-button
        // client-side guard can't catch on its own (a second browser tab, a
        // fast double-tap before the button disables, a replayed request) —
        // only one return-request submission per cashier can be in flight
        // at a time. Same pattern as processSale()'s per-cashier checkout
        // lock; the per-item lockForUpdate() below only stops two DIFFERENT
        // requests from jointly over-requesting past what was sold, not two
        // identical duplicate requests that each individually have enough
        // quantity headroom to pass on their own.
        $lock = Cache::lock("return-request:user:{$user->id}", 10);
        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'A return request is already being submitted for your account. Please wait a moment and try again.',
            ], 409);
        }

        try {
            [$salesReturn, $refundAmount, $productNames] = DB::transaction(function () use ($data, $staff, $transaction) {
                $lines = [];

                foreach ($data['items'] as $itemData) {
                    // Locked so two near-simultaneous requests for the same
                    // transaction+product can't both read the same
                    // "already requested" sum before either commits and
                    // jointly over-request more than was sold (see audit
                    // finding F6) — the second request blocks here until
                    // the first's insert below is committed, then re-reads
                    // a sum that already includes it.
                    $saleLines = SalesItem::linesForProduct((int) $data['transaction_id'], (int) $itemData['product_id'], true);
                    $salesItem = $saleLines->first();

                    if (! $salesItem) {
                        throw new \RuntimeException('Product not found in transaction.');
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

                    $quantity = Quantity::normalize($itemData['quantity']);
                    if (floor($quantity) != $quantity && ! $this->returnAllowsDecimals($salesItem)) {
                        throw new \RuntimeException("\"{$salesItem->product?->ProductName}\" can only be returned in whole ".UnitOfMeasure::plural($salesItem->Unit ?: UnitOfMeasure::DEFAULT).'.');
                    }

                    if (round($alreadyRequested + $quantity, 2) > (float) $saleLines->sum(fn ($line) => (float) $line->Quantity)) {
                        throw new \RuntimeException("Return quantity exceeds the quantity sold for \"{$salesItem->product?->ProductName}\".");
                    }

                    $lines[] = [
                        'salesItem' => $salesItem,
                        // Each piece at its own sale line's price, after the pieces already requested.
                        'amounts' => SalesItem::returnedAmounts((int) $data['transaction_id'], (int) $itemData['product_id'], $quantity, (float) $alreadyRequested),
                        'productId' => $itemData['product_id'],
                        'quantity' => $quantity,
                        'reasonLabel' => SalesReturn::REASON_CODES[$itemData['reason_code'] ?? SalesReturn::DEFAULT_REASON_CODE],
                        'serialIds' => $this->returnedSerialIds($salesItem, $quantity, $itemData['serials'] ?? null),
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
                    // In the unit it was sold in; BaseQuantity is what goes
                    // back to stock (or to Damage): 1 box = 100 pieces.
                    $returnItem = SalesReturnItem::create([
                        'SalesReturnID' => $salesReturn->SalesReturnID,
                        'ProductID' => $line['productId'],
                        'Quantity' => $line['quantity'],
                        'Unit' => $line['salesItem']->Unit ?: UnitOfMeasure::DEFAULT,
                        'BaseQuantity' => Quantity::normalize($line['quantity'] * $line['salesItem']->quantityPerUnit()),
                        'UnitPrice' => $line['quantity'] > 0 ? round($line['amounts']['listed'] / $line['quantity'], 2) : $line['salesItem']->UnitPrice,
                        'Reason' => $line['reasonLabel'],
                    ]);

                    // Those exact pieces are now on this return (released if it's declined).
                    if ($line['serialIds']) {
                        ProductSerial::whereIn('ProductSerialID', $line['serialIds'])->update(['SalesReturnID' => $salesReturn->SalesReturnID]);
                    }

                    $refundAmount += $line['amounts']['refundable'];
                    $productNames[] = "{$returnItem->quantityLabel()} x \"{$line['salesItem']->product?->ProductName}\"";
                }

                return [$salesReturn, $refundAmount, $productNames];
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        } finally {
            $lock->release();
        }

        ActivityLog::record('return.requested', "Requested {$data['return_type']} #{$salesReturn->SalesReturnID} for ".implode(', ', $productNames)." (Txn #{$data['transaction_id']})");

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

        ActionNotifier::toUser(auth()->user(), 'Return Request Submitted', 'Return request #'.str_pad((string) $salesReturn->SalesReturnID, 6, '0', STR_PAD_LEFT).' has been sent to the Administrator for approval.', route('cashier.refunds'), 'rotate-ccw');

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
            'account_number' => 'required_if:refund_method,gcash,bank,cheque|nullable|string|max:50',
        ]);

        $staffId = $this->currentStaffId();

        // Owner-scoped: a Cashier may only complete a refund they themselves
        // filed (see CashierReturnController audit finding F2/F1) -- the
        // lookup during refund creation already stamps StaffID from the
        // authenticated user, so this is the same identity, not a new rule.
        $salesReturn = SalesReturn::where('SalesReturnID', $salesReturnId)->where('StaffID', $staffId)->first();

        if (! $salesReturn) {
            return response()->json(['success' => false, 'message' => 'Refund not found.'], 404);
        }

        if ($salesReturn->Status !== SalesReturn::STATUS_APPROVED || $salesReturn->ReturnType !== SalesReturn::TYPE_REFUND) {
            return response()->json(['success' => false, 'message' => 'Refund must be an approved refund-type request before processing.'], 400);
        }

        try {
            $refundAmount = DB::transaction(function () use ($salesReturnId, $staffId, $data) {
                // Re-fetch and lock the SalesReturn row itself (the earlier
                // check above is only a fast-fail before the lock exists).
                // Without this, two near-simultaneous requests for the same
                // return can both read Status=approved before either commits,
                // and both process the same refund — this lock plus the
                // re-check below serializes them so only the first succeeds.
                $salesReturn = SalesReturn::where('SalesReturnID', $salesReturnId)->where('StaffID', $staffId)->lockForUpdate()->first();

                if (! $salesReturn || $salesReturn->Status !== SalesReturn::STATUS_APPROVED || $salesReturn->ReturnType !== SalesReturn::TYPE_REFUND) {
                    throw new \RuntimeException('This refund has already been processed or is no longer approved.');
                }

                // Recompute the payout from the original sale instead of
                // trusting the client-submitted amount — that field is only
                // pre-filled for display and is editable in the browser
                // before this request is sent. Looped per line item since a
                // request can cover multiple products.
                $refundAmount = 0;

                foreach ($salesReturn->items as $item) {
                    $saleLineIds = SalesItem::linesForProduct((int) $salesReturn->SalesTransactionID, (int) $item->ProductID)->pluck('SalesItemID')->all();

                    // The customer is refunded in cash either way — Factory Defect /
                    // Damaged Product units just skip going back into Inventory
                    // (they were already diverted into the Damage module when the
                    // admin approved this return; see SalesReturnController::approve()).
                    // Every other reason also restores the unit to stock.
                    $refundAmount += $saleLineIds ? $item->refundableTotal() : 0;

                    if ($item->is_unsalable) {
                        continue;
                    }

                    $inventory = Inventory::where('ProductID', $item->ProductID)->lockForUpdate()->first();
                    if (! $inventory) {
                        $inventory = Inventory::firstOrCreate(
                            ['ProductID' => $item->ProductID],
                            ['Quantity' => 0, 'Status' => 'Out of Stock']
                        );
                    }

                    // The refunded pieces go back with their own serial stickers.
                    // Back in the product's base unit (a box's 100 pieces).
                    SerialNumbers::returnToStock($salesReturn->SalesTransactionID, $item->ProductID, (int) $item->BaseQuantity, $salesReturn->SalesReturnID);
                    $inventory->Quantity = Quantity::normalize($inventory->Quantity + $item->BaseQuantity);
                    $inventory->Status = Inventory::resolveStatus($inventory->Quantity, $inventory->ReorderThreshold);
                    // Back onto the stock batches the sale took them from.
                    StockBatches::returningFrom($saleLineIds, fn () => $inventory->save());
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

        ActivityLog::record('return.refund_processed', "Processed refund #{$salesReturnId} — ₱".number_format((float) $refundAmount, 2)." via {$data['refund_method']} (Txn #{$salesReturn->SalesTransactionID})");
        $refundText = 'Refund #'.str_pad((string) $salesReturnId, 6, '0', STR_PAD_LEFT).' of ₱'.number_format((float) $refundAmount, 2)." has been processed via {$data['refund_method']}.";
        ActionNotifier::toAdmins('Refund Processed', $refundText, route('admin.sales-returns.index'), 'rotate-ccw', 'success');
        ActionNotifier::toUser(auth()->user(), 'Refund Processed', $refundText, route('cashier.refunds'), 'rotate-ccw', 'success');

        return response()->json([
            'success' => true,
            'message' => 'Refund processed successfully.',
            'receipt_number' => 'RFD-'.str_pad($salesReturn->SalesReturnID, 6, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * The serials of the pieces coming back on a return line, taken from
     * the receipt: a serial-numbered product names each returned piece's
     * sticker (picked from the ones sold on that receipt), picked
     * automatically when the count leaves no choice (bought 1, returning 1).
     * A sale with no serials recorded (sold before they were tracked) has
     * nothing to pick, and other products have none.
     *
     * @param  list<string>|null  $serialNumbers
     * @return list<int>
     */
    private function returnedSerialIds(SalesItem $salesItem, float $quantity, ?array $serialNumbers): array
    {
        $product = $salesItem->product;
        if (! $product?->tracksSerialNumbers()) {
            return [];
        }

        $returnable = ProductSerial::returnableFor($salesItem->SalesTransactionID, $salesItem->ProductID)->lockForUpdate()->get();
        // Pieces sold without a recorded serial can't be named.
        $needed = min((int) round($quantity * $salesItem->quantityPerUnit()), $returnable->count());
        $picked = collect($serialNumbers ?? [])->map(fn ($number) => strtoupper(trim((string) $number)))->filter()->unique()->values();

        if ($needed === 0) {
            return [];
        }

        if ($picked->isEmpty()) {
            if ($returnable->count() === $needed) {
                return $returnable->pluck('ProductSerialID')->all();
            }

            throw new \RuntimeException("Pick the serial number of each \"{$product->ProductName}\" being returned ({$needed} needed).");
        }

        if ($picked->count() !== $needed) {
            throw new \RuntimeException("Pick exactly {$needed} serial number(s) for \"{$product->ProductName}\" ({$picked->count()} picked).");
        }

        $byNumber = $returnable->keyBy('SerialNumber');
        $unknown = $picked->reject(fn ($number) => $byNumber->has($number));
        if ($unknown->isNotEmpty()) {
            throw new \RuntimeException('Serial number '.$unknown->implode(', ')." wasn't sold as \"{$product->ProductName}\" on this receipt, or is already being returned.");
        }

        return $picked->map(fn ($number) => $byNumber[$number]->ProductSerialID)->all();
    }

    public function printRefundReceipt($salesReturnId, ThermalReceiptPrinter $printer)
    {
        $refund = $this->buildRefundReceiptData($this->findOwnProcessedRefund($salesReturnId));

        // Printed from the browser, it prints the same 32-column lines as
        // the thermal printout, exactly like the sales receipt.
        return view('cashier.refund-receipt', $refund + [
            'printLines' => $printer->browserLines($printer->refundLines($refund)),
            'printTail' => $printer->tailText(),
        ]);
    }

    /**
     * Prints the refund receipt straight to the local thermal printer, like
     * a sale's receipt (CashierAuthController::printThermalReceipt()).
     */
    public function printThermalRefundReceipt($salesReturnId, ThermalReceiptPrinter $printer): JsonResponse
    {
        if (! ThermalReceiptPrinter::isEnabled()) {
            return response()->json(['success' => false, 'message' => 'Direct thermal printing is turned off.'], 409);
        }

        $refund = $this->buildRefundReceiptData($this->findOwnProcessedRefund($salesReturnId));

        try {
            $printer->printRefundReceipt($refund);
        } catch (Throwable $e) {
            Log::warning('Thermal refund receipt printing failed', ['refund' => $refund['receiptNumber'], 'exception' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Could not reach the receipt printer. Check that it is on, has paper, and is connected.',
            ], 503);
        }

        return response()->json(['success' => true]);
    }

    /**
     * The refund receipt's thermal-printer lines, for the print bridge on
     * the cashier PC (see partials/print-bridge).
     */
    public function refundReceiptPrintLines($salesReturnId, ThermalReceiptPrinter $printer): JsonResponse
    {
        $refund = $this->buildRefundReceiptData($this->findOwnProcessedRefund($salesReturnId));

        return response()->json([
            'lines' => $printer->refundLines($refund),
            'payment_method' => $refund['refundMethod'],
        ]);
    }

    /**
     * A processed refund of the logged-in cashier's own. Owner-scoped, same
     * as viewReceipt() on the Transaction History page: another cashier's
     * must 404 the same way a nonexistent one would (audit finding F1).
     */
    private function findOwnProcessedRefund($salesReturnId): SalesReturn
    {
        $salesReturn = SalesReturn::with(['items.product', 'salesTransaction.payment', 'processedByUser'])
            ->where('SalesReturnID', $salesReturnId)
            ->where('StaffID', $this->currentStaffId())
            ->first();

        if (! $salesReturn || $salesReturn->Status !== SalesReturn::STATUS_PROCESSED || $salesReturn->ReturnType !== SalesReturn::TYPE_REFUND) {
            abort(404, 'Refund receipt not found');
        }

        return $salesReturn;
    }

    /**
     * Everything the refund receipt shows, shared by its page, the thermal
     * printout and the print bridge so they never differ.
     *
     * @return array{receiptNumber: string, date: string, cashierName: string, originalReceipt: string, customerName: string, items: list<array{name: string, qty_label: string, price: float, amount: float, reason: string}>, refundAmount: float, refundMethod: string, referenceNumber: ?string}
     */
    private function buildRefundReceiptData(SalesReturn $salesReturn): array
    {
        $customer = $salesReturn->CustomerName ?? $salesReturn->salesTransaction?->CustomerName;

        return [
            'receiptNumber' => 'RFD-'.str_pad((string) $salesReturn->SalesReturnID, 6, '0', STR_PAD_LEFT),
            'date' => Carbon::parse($salesReturn->RefundDate ?? now())->format('m/d/Y'),
            'cashierName' => $salesReturn->processedByUser?->name_with_middle_initial ?? 'N/A',
            'originalReceipt' => $salesReturn->salesTransaction?->payment?->ReceiptNumber
                ?? 'RCT-'.str_pad((string) $salesReturn->SalesTransactionID, 6, '0', STR_PAD_LEFT),
            'customerName' => in_array($customer, [null, '', 'Walk-in Customer'], true) ? 'N/A' : $customer,
            'items' => $salesReturn->items->map(fn (SalesReturnItem $item) => [
                'name' => $item->product?->ProductName ?? 'N/A',
                'qty_label' => ($item->Unit ?: UnitOfMeasure::DEFAULT) === UnitOfMeasure::DEFAULT ? UnitOfMeasure::number($item->Quantity) : $item->quantityLabel(),
                'price' => (float) $item->UnitPrice,
                'amount' => round((float) $item->line_total, 2),
                'reason' => (string) $item->Reason,
            ])->values()->all(),
            'refundAmount' => (float) $salesReturn->RefundAmount,
            'refundMethod' => (string) ($salesReturn->RefundMethod ?: 'cash'),
            'referenceNumber' => $salesReturn->RefundAccountNumber ?: null,
        ];
    }

    /**
     * Get refund details.
     */
    public function getRefundDetails($refundId)
    {
        $refund = SalesReturn::with(['transaction', 'items.product'])
            ->where('SalesReturnID', $refundId)
            ->where('StaffID', $this->currentStaffId())
            ->first();

        if (! $refund) {
            return response()->json(['success' => false, 'message' => 'Refund not found.'], 404);
        }

        $items = $refund->items->map(function (SalesReturnItem $item) {
            // The refundable amount for this line — what was actually paid,
            // not the full undiscounted UnitPrice*Quantity — comes from the
            // ORIGINAL sale's SalesItem (which carries the discount that
            // applied at sale time), not SalesReturnItem's own line_total.
            $refundableLineTotal = $item->refundableTotal();

            return [
                'product_name' => $item->product?->ProductName ?? 'Unknown',
                'quantity' => $item->Quantity,
                'quantity_label' => $item->quantityLabel(),
                'reason' => $item->Reason,
                'unit_price' => $item->UnitPrice,
                'line_total' => $refundableLineTotal,
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

        if (! $staff) {
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
