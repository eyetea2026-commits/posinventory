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
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (! auth()->user() || ! auth()->user()->isCashier()) {
                abort(403);
            }

            return $next($request);
        });
    }

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

        $refunds = SalesReturn::with(['staff', 'salesTransaction', 'items.product'])
            ->where('StaffID', $staff->StaffID ?? 0)
            ->when($search, function ($query) use ($search) {
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

        $product = Product::where('Barcode', $query)->orWhere('SKU', $query)->first();

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

        $items = $transaction->items->groupBy('ProductID')->map(function ($lines) use ($transaction) {
            $item = $lines->first();
            $quantityPurchased = Quantity::normalize($lines->sum(fn ($line) => (float) $line->Quantity));
            $totalPrice = round($lines->sum(fn ($line) => (float) $line->Quantity * (float) $line->UnitPrice), 2);
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
                'QuantityPurchased' => $quantityPurchased,
                'RemainingReturnableQty' => Quantity::normalize(max(0, $quantityPurchased - $alreadyRequested)),
                'Unit' => $unit,
                'UnitPlural' => UnitOfMeasure::plural($unit),
                'AllowsDecimals' => $this->returnAllowsDecimals($item),
                'IsReturnable' => (bool) ($item->product?->IsReturnable ?? true),
                'UnitPrice' => (float) $quantityPurchased > 0 ? round($totalPrice / (float) $quantityPurchased, 2) : (float) $item->UnitPrice,
                'TotalPrice' => $totalPrice,
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

    private function returnAllowsDecimals(SalesItem $salesItem): bool
    {
        $unit = $salesItem->Unit ?: UnitOfMeasure::DEFAULT;

        return (bool) ($salesItem->product?->sellingUnits()[$unit]['fractional'] ?? false)
            || floor((float) $salesItem->Quantity) != (float) $salesItem->Quantity;
    }

    public function createRefund(Request $request)
    {
        $data = $request->validate([
            'transaction_id' => 'required|integer|exists:SalesTransaction,SalesTransactionID',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:Product,ProductID',
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'items.*.reason_code' => 'nullable|in:'.implode(',', array_keys(SalesReturn::REASON_CODES)),
            'items.*.serials' => 'nullable|array',
            'items.*.serials.*' => 'string|max:20',
            'return_type' => 'required|in:refund',
            'remarks' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $staff = Staff::where('UserID', $user->id)->first();

        $transaction = SalesTransaction::find($data['transaction_id']);

        $daysSincePurchase = Carbon::parse($transaction->SalesTransactionDate)->startOfDay()
            ->diffInDays(now()->startOfDay());

        if ($daysSincePurchase > SalesReturn::RETURN_WINDOW_DAYS) {
            return response()->json([
                'success' => false,
                'message' => "This purchase was made {$daysSincePurchase} day(s) ago, outside the ".SalesReturn::RETURN_WINDOW_DAYS.'-day return window. It can no longer be returned.',
            ], 400);
        }

        $nonReturnable = Product::whereIn('ProductID', collect($data['items'])->pluck('product_id'))
            ->where('IsReturnable', false)
            ->pluck('ProductName');
        if ($nonReturnable->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => $nonReturnable->implode(', ').($nonReturnable->count() === 1 ? ' is' : ' are').' not returnable / refundable.',
            ], 400);
        }

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
                    $saleLines = SalesItem::linesForProduct((int) $data['transaction_id'], (int) $itemData['product_id'], true);
                    $salesItem = $saleLines->first();

                    if (! $salesItem) {
                        throw new \RuntimeException('Product not found in transaction.');
                    }

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
                    $returnItem = SalesReturnItem::create([
                        'SalesReturnID' => $salesReturn->SalesReturnID,
                        'ProductID' => $line['productId'],
                        'Quantity' => $line['quantity'],
                        'Unit' => $line['salesItem']->Unit ?: UnitOfMeasure::DEFAULT,
                        'BaseQuantity' => Quantity::normalize($line['quantity'] * $line['salesItem']->quantityPerUnit()),
                        'UnitPrice' => $line['quantity'] > 0 ? round($line['amounts']['listed'] / $line['quantity'], 2) : $line['salesItem']->UnitPrice,
                        'Reason' => $line['reasonLabel'],
                    ]);

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

    public function processRefund(Request $request, $salesReturnId)
    {
        $data = $request->validate([
            'refund_method' => 'required|in:cash,gcash,bank,cheque',
            'account_number' => 'required_if:refund_method,gcash,bank,cheque|nullable|string|max:50',
        ]);

        $staffId = $this->currentStaffId();

        $salesReturn = SalesReturn::where('SalesReturnID', $salesReturnId)->where('StaffID', $staffId)->first();

        if (! $salesReturn) {
            return response()->json(['success' => false, 'message' => 'Refund not found.'], 404);
        }

        if ($salesReturn->Status !== SalesReturn::STATUS_APPROVED || $salesReturn->ReturnType !== SalesReturn::TYPE_REFUND) {
            return response()->json(['success' => false, 'message' => 'Refund must be an approved refund-type request before processing.'], 400);
        }

        try {
            $refundAmount = DB::transaction(function () use ($salesReturnId, $staffId, $data) {
                $salesReturn = SalesReturn::where('SalesReturnID', $salesReturnId)->where('StaffID', $staffId)->lockForUpdate()->first();

                if (! $salesReturn || $salesReturn->Status !== SalesReturn::STATUS_APPROVED || $salesReturn->ReturnType !== SalesReturn::TYPE_REFUND) {
                    throw new \RuntimeException('This refund has already been processed or is no longer approved.');
                }

                $refundAmount = 0;

                foreach ($salesReturn->items as $item) {
                    $saleLineIds = SalesItem::linesForProduct((int) $salesReturn->SalesTransactionID, (int) $item->ProductID)->pluck('SalesItemID')->all();

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

                    SerialNumbers::returnToStock($salesReturn->SalesTransactionID, $item->ProductID, (int) $item->BaseQuantity, $salesReturn->SalesReturnID);
                    $inventory->Quantity = Quantity::normalize($inventory->Quantity + $item->BaseQuantity);
                    $inventory->Status = Inventory::resolveStatus($inventory->Quantity, $inventory->ReorderThreshold);
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

    private function returnedSerialIds(SalesItem $salesItem, float $quantity, ?array $serialNumbers): array
    {
        $product = $salesItem->product;
        if (! $product?->tracksSerialNumbers()) {
            return [];
        }

        $returnable = ProductSerial::returnableFor($salesItem->SalesTransactionID, $salesItem->ProductID)->lockForUpdate()->get();
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

        return view('cashier.refund-receipt', $refund + [
            'printLines' => $printer->browserLines($printer->refundLines($refund)),
            'printTail' => $printer->tailText(),
        ]);
    }

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

    public function refundReceiptPrintLines($salesReturnId, ThermalReceiptPrinter $printer): JsonResponse
    {
        $refund = $this->buildRefundReceiptData($this->findOwnProcessedRefund($salesReturnId));

        return response()->json([
            'lines' => $printer->refundLines($refund),
            'payment_method' => $refund['refundMethod'],
        ]);
    }

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
            'awaiting_action' => (clone $base)->where('Status', SalesReturn::STATUS_APPROVED)->count(),
        ]);
    }
}
