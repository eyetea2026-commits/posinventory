<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\SalesTransaction;
use App\Models\SalesItem;
use App\Models\Billing;
use App\Models\Payment;
use App\Models\Discount;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\SaleCompleted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Throwable;

class CashierAuthController extends Controller
{
    // Login itself is handled by the unified AuthController now — this
    // controller owns everything else in the cashier area (POS, logout).

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    // Password reset for Cashier accounts is intentionally not available —
    // only an Administrator can reset a cashier's password (routes/web.php).
    // The forgot/OTP/reset methods and views that used to sit here were
    // dead code with no route ever pointing at them, and had weaker OTP
    // handling than the live Administrator flow — removed rather than kept
    // around unreachable and unmaintained.

    public function pos()
    {
        $products = Product::with('inventory')->get()->filter(function($product) {
            return $product->inventory && $product->inventory->Quantity > 0;
        });
        return view('cashier.pos', compact('products'));
    }

    // "Apply Promo" — validates a promo code against one specific product
    // already in the cart. Never trusts a client-sent rate/amount: only the
    // resolved DiscountID is handed back, and processSale() re-validates and
    // recomputes everything itself from that ID at checkout time.
    public function applyPromo(Request $request)
    {
        $data = $request->validate([
            'promo_code' => 'required|string|max:30',
            'product_id' => 'required|integer|exists:Product,ProductID',
        ]);

        $code = strtoupper(trim($data['promo_code']));

        // Checked before the code lookup so the cashier gets the most useful
        // message when this product has never had a promo at all — deliberately
        // NOT scoped to currentlyActive() here, since a product with an expired
        // or not-yet-started promo should still fall through to the code lookup
        // below and get the more specific "has expired" / "not currently active"
        // message rather than this generic one.
        if (! Discount::query()->where('ProductID', $data['product_id'])->exists()) {
            return response()->json(['success' => false, 'message' => 'Walang promo sa product na ito.'], 422);
        }

        $discount = Discount::where('PromoCode', $code)->first();

        if (! $discount) {
            return response()->json(['success' => false, 'message' => 'Invalid promo code.'], 404);
        }

        if ((int) $discount->ProductID !== (int) $data['product_id']) {
            return response()->json(['success' => false, 'message' => 'No promo is available for this product.'], 422);
        }

        if ($discount->effective_status !== Discount::STATUS_ACTIVE) {
            $reason = $discount->effective_status === Discount::STATUS_EXPIRED ? 'has expired' : 'is not currently active';
            return response()->json(['success' => false, 'message' => "This promo code {$reason}."], 422);
        }

        return response()->json([
            'success' => true,
            'discount_id' => $discount->DiscountID,
            'discount_rate' => (float) $discount->DiscountRate,
            'promo_name' => $discount->Name,
            'promo_code' => $discount->PromoCode,
            'product_id' => $discount->ProductID,
        ]);
    }

    public function transactions(Request $request)
    {
        $user = Auth::user();
        $staff = Staff::where('UserID', $user->id)->first();

        $search = $request->get('search');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $transactions = SalesTransaction::with(['staff', 'billing', 'items.product'])
            ->when($staff, function($query) use ($staff) {
                return $query->where('StaffID', $staff->StaffID);
            })
            ->when($search, function($query) use ($search) {
                return $query->where('CustomerName', 'like', "%{$search}%");
            })
            ->when($dateFrom, function($query) use ($dateFrom) {
                return $query->whereDate('SalesTransactionDate', '>=', $dateFrom);
            })
            ->when($dateTo, function($query) use ($dateTo) {
                return $query->whereDate('SalesTransactionDate', '<=', $dateTo);
            })
            ->orderBy('SalesTransactionID', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('cashier.transactions', compact('transactions', 'search', 'dateFrom', 'dateTo'));
    }

    public function processSale(Request $request)
    {
        // Cheque/Bank Transfer genuinely need these fields captured — Cash
        // never does, and GCash's reference number stays optional (matching
        // its existing behavior; only the fact that it was being silently
        // discarded is the bug being fixed, not its validation strictness).
        $needsPaymentDetails = ['cheque', 'bank'];
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:Product,ProductID',
            'items.*.qty' => 'required|integer|min:1',
            'payment_method' => 'required|in:cash,gcash,bank,cheque',
            'discount_id' => 'nullable|integer|exists:Discount,DiscountID',
            'reference_number' => [Rule::requiredIf(fn () => in_array($request->input('payment_method'), $needsPaymentDetails, true)), 'nullable', 'string', 'max:50'],
            'bank_name' => [Rule::requiredIf(fn () => in_array($request->input('payment_method'), $needsPaymentDetails, true)), 'nullable', 'string', 'max:100'],
            'account_name' => [Rule::requiredIf(fn () => in_array($request->input('payment_method'), $needsPaymentDetails, true)), 'nullable', 'string', 'max:100'],
            'payment_date' => [Rule::requiredIf(fn () => in_array($request->input('payment_method'), $needsPaymentDetails, true)), 'nullable', 'date'],
            'payment_time' => [Rule::requiredIf(fn () => $request->input('payment_method') === 'bank'), 'nullable', 'date_format:H:i'],
            'remarks' => 'nullable|string|max:500',
        ], [
            'reference_number.required' => 'Please enter the reference/cheque number.',
            'bank_name.required' => 'Please enter the bank name.',
            'account_name.required' => 'Please enter the account name.',
            'payment_date.required' => 'Please enter the payment date.',
            'payment_time.required' => 'Please enter the transfer time.',
        ]);

        $items = $data['items'];
        // Customer Name is optional in the UI, so an empty string (converted to null
        // by the ConvertEmptyStringsToNull middleware) is common — input()'s default
        // only applies when the key is entirely absent, not when it's null/blank, so
        // that case has to be handled explicitly or SalesTransaction's NOT NULL
        // CustomerName column rejects the insert.
        $customerName = trim((string) $request->input('customer_name')) ?: 'Walk-in Customer';
        $discountId = $data['discount_id'] ?? null;
        $paymentMethod = $data['payment_method'];
        $paymentAmount = round(floatval($request->input('payment_amount', 0)), 2);
        $referenceNumber = $data['reference_number'] ?? null;
        $bankName = $data['bank_name'] ?? null;
        $accountName = $data['account_name'] ?? null;
        $paymentDate = $data['payment_date'] ?? null;
        $paymentTime = $data['payment_time'] ?? null;
        $remarks = $data['remarks'] ?? null;

        // Aggregate requested quantity per product: the cart can list the same
        // product across more than one line, and checking/decrementing stock
        // per line independently would let the combined quantity oversell.
        $quantitiesByProduct = [];
        foreach ($items as $item) {
            $quantitiesByProduct[$item['id']] = ($quantitiesByProduct[$item['id']] ?? 0) + (int) $item['qty'];
        }

        $user = Auth::user();

        // Guards against duplicate submissions the disabled-checkout-button
        // client-side guard can't catch on its own (a second browser tab, a
        // fast double-tap before the button disables, a replayed request) —
        // only one checkout per cashier can be in flight at a time.
        $lock = Cache::lock("checkout:user:{$user->id}", 10);
        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'A checkout is already being processed for your account. Please wait a moment and try again.',
            ], 409);
        }

        try {
            $result = DB::transaction(function () use (
                $items, $quantitiesByProduct, $customerName, $discountId, $paymentMethod, $paymentAmount,
                $referenceNumber, $bankName, $accountName, $paymentDate, $paymentTime, $remarks, $user
            ) {
                // Prevent recording the same cheque/reference twice for the
                // same payment method — a plain exists() check here (backed
                // by the migration's unique index as the concurrency-safe
                // fallback for two requests racing past this check at once).
                if ($referenceNumber && Payment::where('PaymentMethod', $paymentMethod)->where('ReferenceNumber', $referenceNumber)->exists()) {
                    throw new \RuntimeException("This {$paymentMethod} reference number has already been recorded for another sale.");
                }

                // Lock the inventory rows involved so a concurrent sale can't
                // deduct the same stock before this transaction commits.
                $inventories = Inventory::whereIn('ProductID', array_keys($quantitiesByProduct))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('ProductID');

                $products = Product::whereIn('ProductID', array_keys($quantitiesByProduct))
                    ->get()
                    ->keyBy('ProductID');

                foreach ($quantitiesByProduct as $productId => $qty) {
                    $inventory = $inventories->get($productId);
                    if (!$inventory || $inventory->Quantity < $qty) {
                        $product = $products->get($productId);
                        throw new \RuntimeException('Insufficient stock for ' . ($product?->ProductName ?? 'product') . '. Available: ' . ($inventory?->Quantity ?? 0));
                    }
                }

                // Recompute totals from the DB product prices — never trust
                // client-submitted prices, they're editable in the browser.
                $subtotal = 0;
                foreach ($items as $item) {
                    $subtotal += $products->get($item['id'])->Price * $item['qty'];
                }

                // Promo codes are admin-managed, product-specific policies —
                // never a rate the cashier can type. Re-validated from
                // scratch here exactly like Apply Promo does client-side,
                // since the id alone is all the client ever sent: it must
                // still exist, still be active/within its date window, and
                // still apply to a product actually in this cart. No
                // selection at all means no discount applies, not an error.
                $discount = null;
                $discountAmount = 0.0;
                if ($discountId) {
                    $discount = Discount::find($discountId);
                    if (!$discount) {
                        throw new \RuntimeException('Selected promo is no longer available.');
                    }
                    if ($discount->effective_status !== Discount::STATUS_ACTIVE) {
                        throw new \RuntimeException('Selected promo is no longer active.');
                    }
                    if (!array_key_exists($discount->ProductID, $quantitiesByProduct)) {
                        throw new \RuntimeException('Selected promo does not apply to any product in this cart.');
                    }

                    // The discount applies only to its own product's line —
                    // not the whole cart — so it's computed against just
                    // that line's subtotal.
                    $promoProductSubtotal = $products->get($discount->ProductID)->Price * $quantitiesByProduct[$discount->ProductID];
                    $discountAmount = round($promoProductSubtotal * ((float) $discount->DiscountRate / 100), 2);
                }

                // Round every money value to the cent immediately after
                // computing it — carrying raw floating-point results into
                // the payment-sufficiency comparison below could reject a
                // payment for exactly the (rounded) total shown on screen,
                // due to residual binary-fraction error a step or two back
                // in this same formula.
                $subtotal = round($subtotal, 2);
                $vatAmount = round(($subtotal - $discountAmount) * 0.12, 2);
                $total = round($subtotal - $discountAmount + $vatAmount, 2);

                // Sufficiency applies to every payment method, not just cash
                // — GCash/bank/cheque are just as capable of being recorded
                // under-amount as cash, and the Payment Amount field is
                // always collected regardless of method.
                if ($paymentAmount < $total) {
                    throw new \RuntimeException('Payment amount must be greater than or equal to total.');
                }

                // Get or create staff record
                $staff = Staff::where('UserID', $user->id)->first();
                if (!$staff) {
                    $staff = Staff::create([
                        'FirstName' => $user->first_name ?: explode(' ', $user->name)[0],
                        'MiddleName' => $user->middle_name ?: '-',
                        'LastName' => $user->last_name ?: (explode(' ', $user->name)[count(explode(' ', $user->name)) - 1] ?? ''),
                        'ContactNumber' => $user->contact_number ?: '-',
                        'Email' => $user->email,
                        'Age' => $user->age ?: '0',
                        'Gender' => $user->gender ?: '-',
                        'UserID' => $user->id,
                    ]);
                }

                // Create sales transaction
                $transaction = SalesTransaction::create([
                    'CustomerName' => $customerName,
                    'SalesTransactionDate' => now(),
                    'StaffID' => $staff->StaffID,
                ]);

                // Create billing — Subtotal/DiscountAmount/VatAmount are
                // stored here (not just BillingAmount) so the receipt can
                // later show exactly what was charged at sale time, immune
                // to the Discount's rate being edited afterward.
                $billing = Billing::create([
                    'CustomerName' => $customerName,
                    'VatApplied' => '12%',
                    'BillingAmount' => $total,
                    'Subtotal' => $subtotal,
                    'DiscountAmount' => $discountAmount,
                    'PromoCode' => $discount?->PromoCode,
                    'VatAmount' => $vatAmount,
                    'BillingDate' => now(),
                    'DiscountID' => $discount?->DiscountID,
                    'SalesTransactionID' => $transaction->SalesTransactionID,
                ]);

                // Create payment
                Payment::create([
                    // The sufficiency check above already guarantees
                    // $paymentAmount >= $total for every payment method by
                    // this point, so it's always the right value to record.
                    'PaymentAmount' => $paymentAmount,
                    'PaymentMethod' => $paymentMethod,
                    'ReceiptNumber' => 'RCT-' . str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT),
                    'BillingID' => $billing->BillingID,
                    'ReferenceNumber' => $referenceNumber,
                    'BankName' => $bankName,
                    'AccountName' => $accountName,
                    'PaymentDate' => $paymentDate,
                    'PaymentTime' => $paymentTime,
                    'Remarks' => $remarks,
                ]);

                // Create sales items, priced from the DB, not the request
                foreach ($items as $item) {
                    SalesItem::create([
                        'Quantity' => $item['qty'],
                        'UnitPrice' => $products->get($item['id'])->Price,
                        'ProductID' => $item['id'],
                        'SalesTransactionID' => $transaction->SalesTransactionID,
                    ]);
                }

                // Update inventory using the aggregated per-product quantity
                foreach ($quantitiesByProduct as $productId => $qty) {
                    $inventory = $inventories->get($productId);
                    $inventory->Quantity -= $qty;

                    if ($inventory->Quantity <= 0) {
                        $inventory->Status = 'Out of Stock';
                    } elseif ($inventory->Quantity <= 10) {
                        $inventory->Status = 'Low Stock';
                    } else {
                        $inventory->Status = 'Available';
                    }

                    $inventory->save();
                }

                return [
                    'receipt_number' => 'RCT-' . str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT),
                    'total' => $total,
                    'transaction_id' => $transaction->SalesTransactionID,
                ];
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } finally {
            $lock->release();
        }

        // Dispatched after the transaction has committed — a notification
        // failure must never roll back an otherwise-successful sale.
        try {
            $completedTransaction = SalesTransaction::with(['items', 'billing'])->find($result['transaction_id']);
            if ($completedTransaction) {
                Notification::send(User::admins(), new SaleCompleted($completedTransaction));
            }
        } catch (Throwable $e) {
            Log::error('Failed to dispatch SaleCompleted notification', [
                'transaction_id' => $result['transaction_id'],
                'exception' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'receipt_number' => $result['receipt_number'],
            'total' => $result['total'],
        ]);
    }

    /**
     * View and print receipt (REQ102)
     */
    public function viewReceipt($receiptNumber)
    {
        // Extract transaction ID from receipt number (RCT-000001 -> 1)
        $transactionId = (int) str_replace('RCT-', '', $receiptNumber);

        $transaction = SalesTransaction::with(['items.product', 'billing'])
            ->where('SalesTransactionID', $transactionId)
            ->first();

        if (!$transaction) {
            abort(404, 'Receipt not found');
        }

        $billing = $transaction->billing;
        $items = $transaction->items->map(function ($item) {
            return [
                'id' => $item->ProductID,
                'name' => $item->product?->ProductName ?? 'Unknown Product',
                'price' => $item->UnitPrice,
                'qty' => $item->Quantity,
            ];
        })->toArray();

        $hasDiscount = $billing && $billing->DiscountID !== null;
        $promoCode = null;
        $promoProductName = null;

        if ($billing && $billing->Subtotal !== null) {
            // Historically-accurate path: read back exactly what was charged
            // at sale time, immune to the Discount's rate/code being edited
            // since then (PromoCode is frozen onto Billing itself for that
            // reason). The rate shown is derived from the promo'd product's
            // OWN line subtotal, not the whole cart — a promo only ever
            // discounts the one product it's tied to — so the percentage and
            // amount on screen always agree with each other.
            $subtotal = (float) $billing->Subtotal;
            $discountAmount = (float) $billing->DiscountAmount;
            $vatAmount = (float) $billing->VatAmount;
            $total = (float) $billing->BillingAmount;

            $discountRate = 0;
            if ($hasDiscount) {
                $promoCode = $billing->PromoCode ?? $billing->discount?->PromoCode;
                $promoProductId = $billing->discount?->ProductID;
                $promoLine = collect($items)->firstWhere('id', $promoProductId);
                $promoProductName = $promoLine['name'] ?? null;
                $promoProductSubtotal = $promoLine ? $promoLine['price'] * $promoLine['qty'] : 0;
                $discountRate = $promoProductSubtotal > 0 ? round(($discountAmount / $promoProductSubtotal) * 100, 2) : 0;
            }
        } else {
            // Legacy fallback for rows predating the Subtotal/DiscountAmount/
            // VatAmount columns — recomputed live from SalesItem + the
            // Discount's CURRENT rate, same as before this fix (unavoidably
            // imprecise if that rate has since changed).
            $subtotal = array_sum(array_map(function ($item) {
                return $item['price'] * $item['qty'];
            }, $items));

            $discountRate = 0;
            if ($billing && $billing->discount) {
                $discountRate = $billing->discount->DiscountRate ?? 0;
            }
            $discountAmount = $subtotal * ($discountRate / 100);
            $vatAmount = ($subtotal - $discountAmount) * 0.12;
            $total = $subtotal - $discountAmount + $vatAmount;
        }

        // Get payment info
        $payment = \App\Models\Payment::where('BillingID', $billing?->BillingID)->first();
        $paymentAmount = $payment ? ($payment->PaymentAmount ?? $total) : $total;
        $change = max(0, $paymentAmount - $total);
        $paymentMethod = $payment?->PaymentMethod ?? 'cash';

        $cashierName = Auth::user()->full_name;

        return view('cashier.receipt', [
            'receiptNumber' => $receiptNumber,
            'date' => $transaction->SalesTransactionDate->format('M d, Y h:i A'),
            'cashierName' => $cashierName,
            'customerName' => $transaction->CustomerName,
            'items' => $items,
            'subtotal' => $subtotal,
            'hasDiscount' => $hasDiscount,
            'discountRate' => $discountRate,
            'discountAmount' => $discountAmount,
            'promoCode' => $promoCode,
            'promoProductName' => $promoProductName,
            'vatAmount' => $vatAmount,
            'total' => $total,
            'paymentMethod' => $paymentMethod,
            'paymentAmount' => $paymentAmount,
            'change' => $change,
            'referenceNumber' => $payment?->ReferenceNumber,
            'bankName' => $payment?->BankName,
            'accountName' => $payment?->AccountName,
            'paymentDate' => $payment?->PaymentDate,
        ]);
    }
}
