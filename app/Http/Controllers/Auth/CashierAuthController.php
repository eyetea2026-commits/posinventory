<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\SaleCompleted;
use App\Services\ActionNotifier;
use App\Services\ThermalReceiptPrinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        // Re-fetched fresh rather than trusting the guard's already-resolved
        // instance, which may have been hydrated earlier in the request
        // lifecycle (or session) before current_session_id's latest value.
        $user = Auth::user()?->fresh();
        $sessionId = $request->session()->getId();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Releases this account's one-active-session slot so another
        // device can log in immediately, without waiting for the old
        // session row to age out.
        if ($user && $user->current_session_id === $sessionId) {
            $user->current_session_id = null;
            $user->save();
        }

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
        // Newest product first on the POS grid.
        $products = Product::active()->with(['inventory', 'category'])->orderByDesc('ProductID')->get()->filter(function ($product) {
            return $product->inventory && $product->inventory->Quantity > 0;
        });

        return view('cashier.pos', [
            'products' => $products,
            'productPromoMap' => $this->buildProductPromoMap(),
        ]);
    }

    // Which products currently have an "Apply Promo" option, and what it
    // actually is — the Discount Module's own Discount/DiscountProduct
    // tables are the only source for this (no separate cashier-side promo
    // data anywhere), read fresh on every call so a promo created, edited,
    // assigned, or expired in the admin's Discount Module is reflected here
    // immediately with no cashier-side duplication to keep in sync. Only
    // one promo can ever be active for a given product at once
    // (DiscountController blocks assigning a second one with an
    // overlapping date range to the same product), so this is a straight
    // ProductID => promo map, never a list. Shared by pos() (initial page
    // load) and promoMap() (the periodic poll that keeps an already-open
    // POS tab in sync without requiring a reload).
    private function buildProductPromoMap(): array
    {
        $productPromoMap = [];
        Discount::currentlyActive()->with('products')->get()->each(function ($discount) use (&$productPromoMap) {
            foreach ($discount->products as $product) {
                $productPromoMap[$product->ProductID] = [
                    'discount_id' => $discount->DiscountID,
                    'code' => $discount->PromoCode,
                    'name' => $discount->Name,
                    'description' => $discount->Description,
                    'type' => $discount->DiscountType,
                    'rate' => (float) $discount->DiscountRate,
                ];
            }
        });

        return $productPromoMap;
    }

    // Polled periodically from an already-open POS tab so a promo created,
    // edited, reassigned, or expired in the admin's Discount Module shows
    // up (or disappears) for the cashier without a page reload.
    public function promoMap()
    {
        return response()->json(['products' => $this->buildProductPromoMap()]);
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
        if (! Discount::whereHas('products', fn ($q) => $q->where('Product.ProductID', $data['product_id']))->exists()) {
            return response()->json(['success' => false, 'message' => 'Walang promo sa product na ito.'], 422);
        }

        $discount = Discount::where('PromoCode', $code)->first();

        if (! $discount) {
            return response()->json(['success' => false, 'message' => 'Invalid promo code.'], 404);
        }

        if (! $discount->products()->where('Product.ProductID', $data['product_id'])->exists()) {
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
            // Every product this promo is assigned to — not just the one the
            // cashier clicked — so the POS screen can discount every matching
            // cart line, never just the first one.
            'applicable_product_ids' => $discount->products()->pluck('Product.ProductID')->values(),
        ]);
    }

    // Daily shift view: a Cashier sees EVERY cashier's sales from TODAY, but
    // view-only for anyone else's -- the Print/reprint action only renders on
    // their own rows, and viewReceipt() independently refuses another
    // cashier's receipt, so hiding the button isn't the only safeguard. The
    // today-only boundary is computed here, server-side, from the app's own
    // clock (config('app.timezone') = Asia/Manila), never taken from the
    // request, so no request parameter can widen it. Admin's full-history
    // Sales Report (ReportController) is separate and untouched by this.
    public function transactions(Request $request)
    {
        $user = Auth::user();
        $staff = Staff::where('UserID', $user->id)->first();
        $currentStaffId = $staff?->StaffID;

        $search = $request->get('search');
        $todayStart = now()->startOfDay();
        $tomorrowStart = $todayStart->copy()->addDay();

        $receiptMatchIds = $search ? $this->todaysReceiptIdsMatching($search, $todayStart, $tomorrowStart) : [];

        $transactions = SalesTransaction::with(['staff.user', 'billing', 'items.product'])
            ->where('SalesTransactionDate', '>=', $todayStart)
            ->where('SalesTransactionDate', '<', $tomorrowStart)
            ->when($search, function ($query) use ($search, $receiptMatchIds) {
                // Grouped so the OR can't escape the today-only date scope.
                return $query->where(function ($inner) use ($search, $receiptMatchIds) {
                    $inner->where('CustomerName', 'like', "%{$search}%");

                    if ($receiptMatchIds !== []) {
                        $inner->orWhereIn('SalesTransactionID', $receiptMatchIds);
                    }
                });
            })
            ->orderBy('SalesTransactionID', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Live search on this page fetches this same route over AJAX and
        // swaps in just the rows + pagination — same pattern as the Admin
        // Inventory list's live search/poll.
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'rows' => view('cashier.partials.transactions-rows', ['transactions' => $transactions, 'currentStaffId' => $currentStaffId])->render(),
                'pagination' => view('cashier.partials.transactions-pagination', ['transactions' => $transactions])->render(),
            ]);
        }

        return view('cashier.transactions', compact('transactions', 'search', 'currentStaffId'));
    }

    /**
     * Receipt # search that works while the cashier is still typing (the
     * list searches live on every keystroke): "R", "RCT", "RCT-" match every
     * receipt, "RCT-0000" narrows down, and "RCT-000014", "rct-14",
     * "000014" or "14" find RCT-000014. Matched against today's receipt
     * numbers exactly as the list displays them (RCT- plus 6 digits), so it
     * behaves the same on any database.
     *
     * @return list<int>
     */
    private function todaysReceiptIdsMatching(string $search, $todayStart, $tomorrowStart): array
    {
        $term = strtoupper(preg_replace('/\s+/', '', $search));

        // Optional leading part of "RCT-", then optional digits.
        if (! preg_match('/^(R|RC|RCT|RCT-)?(\d*)$/', $term, $matches) || $term === '') {
            return [];
        }
        $digits = $matches[2];

        return SalesTransaction::where('SalesTransactionDate', '>=', $todayStart)
            ->where('SalesTransactionDate', '<', $tomorrowStart)
            ->pluck('SalesTransactionID')
            ->filter(function ($id) use ($digits) {
                if ($digits === '') {
                    return true;
                }
                $padded = str_pad((string) $id, 6, '0', STR_PAD_LEFT);

                // Typed with leading zeros ("000014") or as a plain number ("14").
                return str_contains($padded, $digits) || (string) $id === ltrim($digits, '0');
            })
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * How long an Administrator's approval of a custom discount stays valid
     * for the sale it was given for.
     */
    private const CUSTOM_DISCOUNT_APPROVAL_MINUTES = 15;

    /**
     * A custom percentage discount at the POS needs an Administrator's
     * approval: the cashier's screen asks for an Administrator's password,
     * checked here. On a match the approval (percent + who approved) is kept
     * in this cashier's session for the next sale only; processSale() refuses
     * a custom discount without it.
     */
    public function approveCustomDiscount(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Two digits: a whole number from 1 to 99.
            'percent' => ['required', 'integer', 'min:1', 'max:99'],
            'admin_password' => ['required', 'string'],
        ], [
            'percent.integer' => 'Enter a whole-number discount from 1% to 99%.',
            'percent.min' => 'Enter a discount from 1% to 99%.',
            'percent.max' => 'Enter a discount from 1% to 99%.',
            'admin_password.required' => "Enter an Administrator's password.",
        ]);

        $approver = User::admins()
            ->filter(fn (User $admin) => $admin->is_active !== false)
            ->first(fn (User $admin) => Hash::check($data['admin_password'], $admin->password));

        if (! $approver) {
            ActivityLog::record('pos.custom_discount_denied', 'Custom discount of '.(float) $data['percent'].'% refused: wrong Administrator password');

            return response()->json(['success' => false, 'message' => 'Incorrect Administrator password.'], 422);
        }

        $percent = round((float) $data['percent'], 2);
        $request->session()->put('custom_discount_approval', [
            'percent' => $percent,
            'approved_by' => $approver->id,
            'expires_at' => now()->addMinutes(self::CUSTOM_DISCOUNT_APPROVAL_MINUTES)->timestamp,
        ]);
        ActivityLog::record('pos.custom_discount_approved', "Custom discount of {$percent}% approved by \"{$approver->full_name}\"");

        return response()->json([
            'success' => true,
            'percent' => $percent,
            'approved_by' => $approver->full_name,
        ]);
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
            // Serial-numbered products: the scanned sticker of each piece.
            'items.*.serials' => 'nullable|array',
            'items.*.serials.*' => 'string|max:20',
            'payment_method' => 'required|in:cash,gcash,bank,cheque',
            'reference_number' => [Rule::requiredIf(fn () => in_array($request->input('payment_method'), $needsPaymentDetails, true)), 'nullable', 'string', 'max:50'],
            'bank_name' => [Rule::requiredIf(fn () => in_array($request->input('payment_method'), $needsPaymentDetails, true)), 'nullable', 'string', 'max:100'],
            'account_name' => [Rule::requiredIf(fn () => in_array($request->input('payment_method'), $needsPaymentDetails, true)), 'nullable', 'string', 'max:100'],
            'payment_date' => [Rule::requiredIf(fn () => in_array($request->input('payment_method'), $needsPaymentDetails, true)), 'nullable', 'date'],
            'payment_time' => [Rule::requiredIf(fn () => $request->input('payment_method') === 'bank'), 'nullable', 'date_format:H:i'],
            'remarks' => 'nullable|string|max:500',
            // 7 whole-peso digits max, matching the POS input's own cap.
            'payment_amount' => 'nullable|numeric|min:0|max:9999999.99',
            // Needs an Administrator's approval first (approveCustomDiscount()).
            'custom_discount_percent' => 'nullable|integer|min:1|max:99',
        ], [
            'payment_amount.max' => 'Payment amount cannot exceed ₱9,999,999.99.',
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
        // CustomerName column rejects the insert. A customer
        // who gave no name is saved as "N/A".
        $customerName = trim((string) $request->input('customer_name')) ?: 'N/A';
        $paymentMethod = $data['payment_method'];
        $paymentAmount = round(floatval($request->input('payment_amount', 0)), 2);
        $referenceNumber = $data['reference_number'] ?? null;
        $bankName = $data['bank_name'] ?? null;
        $accountName = $data['account_name'] ?? null;
        $paymentDate = $data['payment_date'] ?? null;
        $paymentTime = $data['payment_time'] ?? null;
        $remarks = $data['remarks'] ?? null;

        // A custom discount must match the percent an Administrator approved
        // for this cashier, and the approval must not have expired.
        $customDiscountPercent = isset($data['custom_discount_percent']) ? round((float) $data['custom_discount_percent'], 2) : null;
        $customDiscountApprovedBy = null;
        if ($customDiscountPercent !== null) {
            $approval = $request->session()->get('custom_discount_approval');
            $approved = is_array($approval)
                && abs((float) $approval['percent'] - $customDiscountPercent) < 0.001
                && (int) $approval['expires_at'] >= now()->timestamp;
            if (! $approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'The discount needs an Administrator\'s approval. Apply it again and enter an Administrator\'s password.',
                ], 403);
            }
            $customDiscountApprovedBy = (int) $approval['approved_by'];
        }

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
                $items, $quantitiesByProduct, $customerName, $paymentMethod, $paymentAmount,
                $referenceNumber, $bankName, $accountName, $paymentDate, $paymentTime, $remarks, $user,
                $customDiscountPercent, $customDiscountApprovedBy
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

                $products = Product::with('category')->whereIn('ProductID', array_keys($quantitiesByProduct))
                    ->get()
                    ->keyBy('ProductID');

                // The POS screen only lists active products, but a tab left
                // open since before a product was deactivated could still
                // submit one — enforce it here too.
                $inactive = $products->first(fn (Product $product) => ! $product->IsActive);
                if ($inactive) {
                    throw new \RuntimeException("\"{$inactive->ProductName}\" is no longer available for sale. Please remove it from the cart.");
                }

                foreach ($quantitiesByProduct as $productId => $qty) {
                    $inventory = $inventories->get($productId);
                    if (! $inventory || $inventory->Quantity < $qty) {
                        $product = $products->get($productId);
                        throw new \RuntimeException('Insufficient stock for '.($product?->ProductName ?? 'product').'. Available: '.($inventory?->Quantity ?? 0));
                    }
                }

                // Serial-numbered products sell by their scanned stickers:
                // one in-stock serial of that product per piece sold.
                $scannedSerials = [];
                foreach ($items as $item) {
                    foreach ($item['serials'] ?? [] as $serialNumber) {
                        $scannedSerials[$item['id']][] = strtoupper(trim($serialNumber));
                    }
                }
                $serialsToSell = [];
                foreach ($quantitiesByProduct as $productId => $qty) {
                    $product = $products->get($productId);
                    if (! $product->tracksSerialNumbers()) {
                        continue;
                    }

                    $serialNumbers = array_values(array_unique($scannedSerials[$productId] ?? []));
                    if (count($serialNumbers) !== $qty) {
                        throw new \RuntimeException("Scan the serial number sticker of each \"{$product->ProductName}\" ({$qty} needed, ".count($serialNumbers).' scanned).');
                    }

                    $serials = ProductSerial::where('ProductID', $productId)
                        ->whereIn('SerialNumber', $serialNumbers)
                        ->where('Status', ProductSerial::STATUS_IN_STOCK)
                        ->lockForUpdate()
                        ->get();
                    if ($serials->count() !== $qty) {
                        $unavailable = array_diff($serialNumbers, $serials->pluck('SerialNumber')->all());
                        throw new \RuntimeException('Serial number '.implode(', ', $unavailable)." is not an in-stock \"{$product->ProductName}\". Remove it from the cart.");
                    }
                    $serialsToSell[$productId] = $serials;
                }

                // Recompute totals from the DB product prices — never trust
                // client-submitted prices, they're editable in the browser.
                $subtotal = 0;
                foreach ($items as $item) {
                    $subtotal += $products->get($item['id'])->Price * $item['qty'];
                }

                // Apply Promo is fully automatic now — there's no client
                // selection to trust at all, not even an id. For every
                // product actually in this cart, independently look up
                // whatever promo is currently active for it (same
                // Discount/DiscountProduct data and currentlyActive() scope
                // the admin's Discount Module and the POS page's live promo
                // map both use) and discount that line by exactly that
                // promo's own type/rate, reusing discountedPriceFor() so the
                // math can never drift from what's shown anywhere else.
                $discountAmount = 0.0;
                $usedDiscountIds = [];
                $activePromoByProduct = [];
                // Per unit, not per line — multiplied by whatever quantity
                // ends up on each SalesItem row below, so a return/refund
                // can later prorate by exactly the quantity being returned
                // rather than assuming a whole line is returned at once.
                $discountPerUnitByProduct = [];
                Discount::currentlyActive()
                    ->whereHas('products', fn ($q) => $q->whereIn('Product.ProductID', array_keys($quantitiesByProduct)))
                    ->with(['products' => fn ($q) => $q->whereIn('Product.ProductID', array_keys($quantitiesByProduct))])
                    ->get()
                    ->each(function ($promo) use (&$activePromoByProduct) {
                        foreach ($promo->products as $promoProduct) {
                            $activePromoByProduct[$promoProduct->ProductID] = $promo;
                        }
                    });

                foreach ($quantitiesByProduct as $productId => $qty) {
                    $promo = $activePromoByProduct[$productId] ?? null;
                    if (! $promo) {
                        continue;
                    }
                    $product = $products->get($productId);
                    $perUnitDiscount = (float) $product->Price - $promo->discountedPriceFor($product);
                    $discountAmount += $perUnitDiscount * $qty;
                    $discountPerUnitByProduct[$productId] = $perUnitDiscount;
                    $usedDiscountIds[$promo->DiscountID] = true;
                }
                $discountAmount = round($discountAmount, 2);

                // Billing only has room for one DiscountID/PromoCode (it
                // predates per-product promo assignment) — when every
                // discounted line in this sale came from the same promo,
                // which is the normal case, it's recorded exactly as
                // before. The rare case of two DIFFERENT promos each
                // discounting a different product in the same cart still
                // charges the correct total (DiscountAmount above already
                // sums every line correctly regardless), it just can't
                // attribute the receipt's promo-code line to a single promo,
                // so that display is skipped rather than shown wrong.
                $distinctDiscountIds = array_keys($usedDiscountIds);
                $discount = count($distinctDiscountIds) === 1 ? Discount::find($distinctDiscountIds[0]) : null;

                // Round every money value to the cent immediately after
                // computing it — carrying raw floating-point results into
                // the payment-sufficiency comparison below could reject a
                // payment for exactly the (rounded) total shown on screen,
                // due to residual binary-fraction error a step or two back
                // in this same formula.
                // VAT is 12% of the post-discount Total, for the receipt/
                // report breakdown — it is NOT added on top of Total, which
                // stays exactly Subtotal minus Discount regardless of this
                // line. Total is computed first, before VAT, so rounding
                // can't drift the two apart.

                // FORMULA COMPUTATIONS
                $subtotal = round($subtotal, 2);
                // The custom discount is a percent of what's left after the
                // product promos; $discountAmount becomes the sale's whole
                // discount (promos + custom), as Billing has always stored it.
                $afterPromos = round($subtotal - $discountAmount, 2);
                $customDiscountAmount = $customDiscountPercent !== null ? round($afterPromos * $customDiscountPercent / 100, 2) : 0.0;
                $discountAmount = round($discountAmount + $customDiscountAmount, 2);
                $total = round($subtotal - $discountAmount, 2);
                $vatAmount = round($total * 0.12, 2);

                // Sufficiency applies to every payment method, not just cash
                // — GCash/bank/cheque are just as capable of being recorded
                // under-amount as cash, and the Payment Amount field is
                // always collected regardless of method.
                if ($paymentAmount < $total) {
                    throw new \RuntimeException('Payment amount must be greater than or equal to total.');
                }

                // Get or create staff record
                $staff = Staff::where('UserID', $user->id)->first();
                if (! $staff) {
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
                    'CustomDiscountPercent' => $customDiscountPercent,
                    'CustomDiscountAmount' => $customDiscountPercent !== null ? $customDiscountAmount : null,
                    'CustomDiscountApprovedBy' => $customDiscountApprovedBy,
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
                    'ReceiptNumber' => 'RCT-'.str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT),
                    'BillingID' => $billing->BillingID,
                    'ReferenceNumber' => $referenceNumber,
                    'BankName' => $bankName,
                    'AccountName' => $accountName,
                    'PaymentDate' => $paymentDate,
                    'PaymentTime' => $paymentTime,
                    'Remarks' => $remarks,
                ]);

                // The custom discount is split across the lines in proportion
                // to what each still costs after promos (the last line takes
                // the rounding remainder), so a later refund of any line pays
                // back exactly what was paid for it.
                $customShares = [];
                $sharedSoFar = 0.0;
                foreach (array_values($items) as $index => $item) {
                    $lineAfterPromo = $products->get($item['id'])->Price * $item['qty'] - ($discountPerUnitByProduct[$item['id']] ?? 0) * $item['qty'];
                    $share = $index === count($items) - 1
                        ? round($customDiscountAmount - $sharedSoFar, 2)
                        : ($afterPromos > 0 ? round($customDiscountAmount * $lineAfterPromo / $afterPromos, 2) : 0.0);
                    $customShares[$index] = $share;
                    $sharedSoFar = round($sharedSoFar + $share, 2);
                }

                // Create sales items, priced from the DB, not the request
                foreach (array_values($items) as $index => $item) {
                    $perUnitDiscount = $discountPerUnitByProduct[$item['id']] ?? 0;
                    SalesItem::create([
                        'Quantity' => $item['qty'],
                        'UnitPrice' => $products->get($item['id'])->Price,
                        // This line's own share of the sale's discount — lets
                        // a later return/refund pay out exactly what the
                        // customer paid for the returned quantity instead of
                        // the full undiscounted price.
                        'DiscountAmount' => round($perUnitDiscount * $item['qty'] + $customShares[$index], 2),
                        'ProductID' => $item['id'],
                        'SalesTransactionID' => $transaction->SalesTransactionID,
                    ]);
                }

                foreach ($serialsToSell as $serials) {
                    foreach ($serials as $serial) {
                        $serial->update([
                            'Status' => ProductSerial::STATUS_SOLD,
                            'SalesTransactionID' => $transaction->SalesTransactionID,
                            'StatusDate' => now(),
                        ]);
                    }
                }

                // Update inventory using the aggregated per-product quantity
                foreach ($quantitiesByProduct as $productId => $qty) {
                    $inventory = $inventories->get($productId);
                    $inventory->Quantity -= $qty;
                    $inventory->Status = Inventory::resolveStatus($inventory->Quantity, $inventory->ReorderThreshold);
                    $inventory->save();
                }

                return [
                    'receipt_number' => 'RCT-'.str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT),
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

        // One approval, one sale.
        if ($customDiscountPercent !== null) {
            $request->session()->forget('custom_discount_approval');
            $approverName = User::find($customDiscountApprovedBy)?->full_name ?? 'an Administrator';
            ActivityLog::record('pos.custom_discount_used', "Custom discount of {$customDiscountPercent}% given on {$result['receipt_number']} (approved by \"{$approverName}\")");
            ActionNotifier::toAdmins('Custom Discount Given', "A {$customDiscountPercent}% discount was given on {$result['receipt_number']} (approved by {$approverName}).", route('admin.reports.index'), 'percent', 'warning');
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

        ActionNotifier::toUser(auth()->user(), 'Sale Completed', "Sale {$result['receipt_number']} of ₱".number_format((float) $result['total'], 2).' has been completed.', route('cashier.transactions'), 'circle-check', 'success');

        return response()->json([
            'success' => true,
            'receipt_number' => $result['receipt_number'],
            'total' => $result['total'],
        ]);
    }

    /**
     * View and print receipt (REQ102)
     */
    public function viewReceipt($receiptNumber, ThermalReceiptPrinter $printer)
    {
        $receipt = $this->buildReceiptData($this->findOwnTransaction($receiptNumber), $receiptNumber);

        // Printed from the browser, the receipt prints the same 32-column
        // lines as the direct thermal printout (bold / double-height where
        // that printout uses them), so with the thermal printer's own driver
        // the slip looks the same either way.
        return view('cashier.receipt', $receipt + [
            'printLines' => $printer->printLines($receipt),
            'printTail' => $printer->tailText(),
        ]);
    }

    /**
     * The receipt's thermal-printer lines, for the print bridge: the POS
     * sends them to the local copy of this app on the cashier PC, which
     * prints them exactly like a local sale. Same ownership rule as
     * viewReceipt().
     */
    public function receiptPrintLines($receiptNumber, ThermalReceiptPrinter $printer): JsonResponse
    {
        $receipt = $this->buildReceiptData($this->findOwnTransaction($receiptNumber), $receiptNumber);

        return response()->json([
            'lines' => $printer->lines($receipt),
            'payment_method' => $receipt['paymentMethod'],
        ]);
    }

    /**
     * Prints the receipt straight to the local thermal printer (no browser
     * dialog). Same ownership rule as viewReceipt(). Responds with JSON so
     * the POS can fall back to the on-screen receipt if printing fails.
     */
    public function printThermalReceipt(Request $request, $receiptNumber, ThermalReceiptPrinter $printer)
    {
        if (! ThermalReceiptPrinter::isEnabled()) {
            return response()->json(['success' => false, 'message' => 'Direct thermal printing is turned off.'], 409);
        }

        $receipt = $this->buildReceiptData($this->findOwnTransaction($receiptNumber), $receiptNumber);

        // The cash drawer only opens for a fresh cash sale (opt-in via
        // POS_PRINTER_OPEN_DRAWER), never on a reprint.
        $openDrawer = config('services.receipt_printer.open_drawer')
            && $request->boolean('new_sale')
            && $receipt['paymentMethod'] === 'cash';

        try {
            $printer->printReceipt($receipt, $openDrawer);
        } catch (Throwable $e) {
            Log::warning('Thermal receipt printing failed', ['receipt' => $receiptNumber, 'exception' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Could not reach the receipt printer. Check that it is on, has paper, and is connected.',
            ], 503);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Scoped to the logged-in cashier's own sales -- reached from the
     * Transaction History "Print" button, so it must be denied the same way
     * a transaction outside that list would be. A 404 (not 403) is used
     * deliberately: it reads identically whether the receipt number doesn't
     * exist or simply isn't this cashier's, so guessing another cashier's ID
     * can't be used to confirm it exists.
     */
    private function findOwnTransaction(string $receiptNumber): SalesTransaction
    {
        // Extract transaction ID from receipt number (RCT-000001 -> 1)
        $transactionId = (int) str_replace('RCT-', '', $receiptNumber);

        $staff = Staff::where('UserID', Auth::id())->first();

        $transaction = SalesTransaction::with(['items.product', 'billing'])
            ->where('SalesTransactionID', $transactionId)
            ->where('StaffID', $staff->StaffID ?? 0)
            ->first();

        if (! $transaction) {
            abort(404, 'Receipt not found');
        }

        return $transaction;
    }

    /**
     * Everything a receipt shows, shared by the on-screen receipt and the
     * thermal printer so the two can never disagree.
     *
     * @return array<string, mixed>
     */
    private function buildReceiptData(SalesTransaction $transaction, string $receiptNumber): array
    {
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
            // DiscountAmount holds promos + custom discount; the receipt
            // shows the two on separate lines.
            $customDiscountAmount = (float) ($billing->CustomDiscountAmount ?? 0);
            $discountAmount = round((float) $billing->DiscountAmount - $customDiscountAmount, 2);
            $vatAmount = (float) $billing->VatAmount;
            $total = (float) $billing->BillingAmount;

            $discountRate = 0;
            if ($hasDiscount) {
                $promoCode = $billing->PromoCode ?? $billing->discount?->PromoCode;
                // A promo can be assigned to several products — every line on
                // THIS receipt that's one of them contributed to the frozen
                // DiscountAmount above, so all of them are named here.
                $promoProductIds = $billing->discount
                    ? $billing->discount->products()->pluck('Product.ProductID')->all()
                    : [];
                $promoLines = collect($items)->whereIn('id', $promoProductIds);
                $promoProductName = $promoLines->isNotEmpty() ? $promoLines->pluck('name')->implode(', ') : null;
                $promoProductSubtotal = $promoLines->sum(fn ($line) => $line['price'] * $line['qty']);
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
        $payment = Payment::where('BillingID', $billing?->BillingID)->first();
        $paymentAmount = $payment ? ($payment->PaymentAmount ?? $total) : $total;
        $change = max(0, $paymentAmount - $total);
        $paymentMethod = $payment?->PaymentMethod ?? 'cash';

        // Middle name as an initial on the receipt ("Gina J. Cole").
        $cashierName = Auth::user()->name_with_middle_initial;

        return [
            'receiptNumber' => $receiptNumber,
            'date' => $transaction->SalesTransactionDate->format('m/d/Y h:i A'),
            'cashierName' => $cashierName,
            'customerName' => $transaction->CustomerName,
            'items' => $items,
            'subtotal' => $subtotal,
            'hasDiscount' => $hasDiscount,
            'discountRate' => $discountRate,
            'discountAmount' => $discountAmount,
            'promoCode' => $promoCode,
            'promoProductName' => $promoProductName,
            'customDiscountPercent' => $billing?->CustomDiscountPercent !== null ? (float) $billing->CustomDiscountPercent : null,
            'customDiscountAmount' => (float) ($billing?->CustomDiscountAmount ?? 0),
            'vatAmount' => $vatAmount,
            'total' => $total,
            'paymentMethod' => $paymentMethod,
            'paymentAmount' => $paymentAmount,
            'change' => $change,
            'referenceNumber' => $payment?->ReferenceNumber,
            'bankName' => $payment?->BankName,
            'accountName' => $payment?->AccountName,
            'paymentDate' => $payment?->PaymentDate,
        ];
    }
}
