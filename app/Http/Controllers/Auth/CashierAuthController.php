<?php

namespace App\Http\Controllers\Auth;

use App\Casts\Quantity;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Models\Staff;
use App\Models\StockBatchAllocation;
use App\Models\User;
use App\Notifications\SaleCompleted;
use App\Services\ActionNotifier;
use App\Services\PaymentProof;
use App\Services\ReorderPoint;
use App\Services\StockBatches;
use App\Services\ThermalReceiptPrinter;
use App\Services\UnitOfMeasure;
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
    public function logout(Request $request)
    {
        $user = Auth::user()?->fresh();
        $sessionId = $request->session()->getId();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user && $user->current_session_id === $sessionId) {
            $user->current_session_id = null;
            $user->save();
        }

        return redirect('/');
    }

    public function pos()
    {
        $products = Product::active()->with(['inventory', 'category', 'stockBatches' => fn ($query) => $query->where('QuantityRemaining', '>', 0)])->orderByDesc('ProductID')->get()
            ->filter(fn ($product) => $product->inventory && $product->inventory->Quantity > 0)
            ->sortBy(fn ($product) => match (true) {
                strcasecmp((string) $product->category?->CategoryName, 'CONSUMABLES') === 0 => 0,
                ! $product->tracksSerialNumbers() => 1,
                default => 2,
            })
            ->values();

        return view('cashier.pos', [
            'products' => $products,
            'productPromoMap' => $this->buildProductPromoMap(),
        ]);
    }

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

    public function promoMap()
    {
        return response()->json(['products' => $this->buildProductPromoMap()]);
    }

    public function applyPromo(Request $request)
    {
        $data = $request->validate([
            'promo_code' => 'required|string|max:30',
            'product_id' => 'required|integer|exists:Product,ProductID',
        ]);

        $code = strtoupper(trim($data['promo_code']));

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
            'applicable_product_ids' => $discount->products()->pluck('Product.ProductID')->values(),
        ]);
    }

    public function transactions(Request $request)
    {
        $user = Auth::user();
        $staff = Staff::where('UserID', $user->id)->first();
        $currentStaffId = $staff?->StaffID;

        $search = $request->get('search');
        $todayStart = now()->startOfDay();
        $tomorrowStart = $todayStart->copy()->addDay();

        $receiptMatchIds = $search ? $this->todaysReceiptIdsMatching($search, $todayStart, $tomorrowStart) : [];

        $transactions = SalesTransaction::with(['staff.user', 'payment', 'items.product'])
            ->where('SalesTransactionDate', '>=', $todayStart)
            ->where('SalesTransactionDate', '<', $tomorrowStart)
            ->when($search, function ($query) use ($search, $receiptMatchIds) {
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

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'rows' => view('cashier.partials.transactions-rows', ['transactions' => $transactions, 'currentStaffId' => $currentStaffId])->render(),
                'pagination' => view('cashier.partials.transactions-pagination', ['transactions' => $transactions])->render(),
            ]);
        }

        return view('cashier.transactions', compact('transactions', 'search', 'currentStaffId'));
    }

    private function todaysReceiptIdsMatching(string $search, $todayStart, $tomorrowStart): array
    {
        $term = strtoupper(preg_replace('/\s+/', '', $search));

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

                return str_contains($padded, $digits) || (string) $id === ltrim($digits, '0');
            })
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private const CUSTOM_DISCOUNT_APPROVAL_MINUTES = 15;

    public function approveCustomDiscount(Request $request): JsonResponse
    {
        $data = $request->validate([
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
        $needsPaymentDetails = ['cheque', 'bank'];
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:Product,ProductID',
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:9999999', 'decimal:0,2'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.serials' => 'nullable|array',
            'items.*.serials.*' => 'string|max:20',
            'payment_method' => 'required|in:cash,gcash,bank,cheque',
            'reference_number' => [Rule::requiredIf(fn () => in_array($request->input('payment_method'), $needsPaymentDetails, true)), 'nullable', 'string', 'max:50'],
            'bank_name' => [Rule::requiredIf(fn () => in_array($request->input('payment_method'), $needsPaymentDetails, true)), 'nullable', 'string', 'max:100'],
            'account_name' => [Rule::requiredIf(fn () => in_array($request->input('payment_method'), $needsPaymentDetails, true)), 'nullable', 'string', 'max:100'],
            'payment_date' => [Rule::requiredIf(fn () => in_array($request->input('payment_method'), $needsPaymentDetails, true)), 'nullable', 'date'],
            'payment_time' => [Rule::requiredIf(fn () => $request->input('payment_method') === 'bank'), 'nullable', 'date_format:H:i'],
            'remarks' => 'nullable|string|max:500',
            'payment_amount' => 'nullable|numeric|min:0|max:9999999.99',
            'payment_proof' => [Rule::requiredIf(fn () => in_array($request->input('payment_method'), PaymentProof::METHODS, true)), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'custom_discount_percent' => 'nullable|integer|min:1|max:99',
        ], [
            'items.*.qty.gt' => 'Every quantity must be greater than 0.',
            'items.*.qty.decimal' => 'Quantities can have up to 2 decimals.',
            'payment_amount.max' => 'Payment amount cannot exceed ₱9,999,999.99.',
            'payment_proof.required' => 'Please upload the proof of payment photo.',
            'payment_proof.image' => 'The proof of payment must be a photo (JPG, PNG or WebP).',
            'payment_proof.mimes' => 'The proof of payment must be a photo (JPG, PNG or WebP).',
            'payment_proof.max' => 'The proof of payment photo must be 5 MB or smaller.',
            'reference_number.required' => 'Please enter the reference/cheque number.',
            'bank_name.required' => 'Please enter the bank name.',
            'account_name.required' => 'Please enter the account name.',
            'payment_date.required' => 'Please enter the payment date.',
            'payment_time.required' => 'Please enter the transfer time.',
        ]);

        $items = $data['items'];
        $customerName = trim((string) $request->input('customer_name')) ?: 'N/A';
        $paymentMethod = $data['payment_method'];
        $paymentAmount = round(floatval($request->input('payment_amount', 0)), 2);
        $referenceNumber = $data['reference_number'] ?? null;
        $bankName = $data['bank_name'] ?? null;
        $accountName = $data['account_name'] ?? null;
        $paymentDate = $data['payment_date'] ?? null;
        $paymentTime = $data['payment_time'] ?? null;
        $remarks = $data['remarks'] ?? null;

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

        $productIds = array_values(array_unique(array_map(fn ($item) => (int) $item['id'], $items)));

        $user = Auth::user();

        $lock = Cache::lock("checkout:user:{$user->id}", 10);
        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'A checkout is already being processed for your account. Please wait a moment and try again.',
            ], 409);
        }

        $proofPath = null;
        $saleCompleted = false;
        if ($request->hasFile('payment_proof') && in_array($paymentMethod, PaymentProof::METHODS, true)) {
            try {
                $proofPath = PaymentProof::store($request->file('payment_proof'));
            } catch (\RuntimeException $e) {
                $lock->release();

                return response()->json(['success' => false, 'message' => 'The proof of payment photo could not be read. Please choose another photo.'], 422);
            }
        }

        try {
            $result = DB::transaction(function () use (
                $items, $productIds, $customerName, $paymentMethod, $paymentAmount,
                $referenceNumber, $bankName, $accountName, $paymentDate, $paymentTime, $remarks, $user, $proofPath,
                $customDiscountPercent, $customDiscountApprovedBy
            ) {
                if ($referenceNumber && Payment::where('PaymentMethod', $paymentMethod)->where('ReferenceNumber', $referenceNumber)->exists()) {
                    throw new \RuntimeException("This {$paymentMethod} reference number has already been recorded for another sale.");
                }

                $inventories = Inventory::whereIn('ProductID', $productIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('ProductID');

                $products = Product::with('category')->whereIn('ProductID', $productIds)
                    ->get()
                    ->keyBy('ProductID');

                $inactive = $products->first(fn (Product $product) => ! $product->IsActive);
                if ($inactive) {
                    throw new \RuntimeException("\"{$inactive->ProductName}\" is no longer available for sale. Please remove it from the cart.");
                }

                $unpriced = $products->first(fn (Product $product) => (float) $product->Price <= 0);
                if ($unpriced) {
                    throw new \RuntimeException("\"{$unpriced->ProductName}\" has no selling price yet. Ask the admin to set its price in Products, then remove it from the cart.");
                }

                $lines = [];
                foreach ($items as $item) {
                    $product = $products->get($item['id']);
                    $unit = filled($item['unit'] ?? null) ? $item['unit'] : $product->baseUnit();
                    $sellingUnit = $product->sellingUnits()[$unit] ?? null;
                    if (! $sellingUnit) {
                        throw new \RuntimeException("\"{$product->ProductName}\" is not sold by the {$unit}.");
                    }
                    if (isset($lines[$product->ProductID]) && $lines[$product->ProductID]['unit'] !== $unit) {
                        throw new \RuntimeException("Sell \"{$product->ProductName}\" in one unit per sale.");
                    }

                    $line = $lines[$product->ProductID] ?? ['unit' => $unit, 'price' => $sellingUnit['price'], 'quantity_per_unit' => $sellingUnit['quantity_per_unit'], 'qty' => 0, 'serials' => []];
                    $line['qty'] = Quantity::normalize($line['qty'] + (float) $item['qty']);
                    $line['serials'] = array_merge($line['serials'], $item['serials'] ?? []);
                    if (! $sellingUnit['fractional'] && floor($line['qty']) != $line['qty']) {
                        throw new \RuntimeException("\"{$product->ProductName}\" is sold in whole {$sellingUnit['plural']}.");
                    }
                    $lines[$product->ProductID] = $line;
                }

                $quantitiesByProduct = [];
                foreach ($lines as $productId => $line) {
                    $quantitiesByProduct[$productId] = Quantity::normalize($line['qty'] * $line['quantity_per_unit']);
                }

                foreach ($quantitiesByProduct as $productId => $qty) {
                    $inventory = $inventories->get($productId);
                    if (! $inventory || $inventory->Quantity < $qty) {
                        $product = $products->get($productId);
                        throw new \RuntimeException('Insufficient stock for '.($product?->ProductName ?? 'product').'. Available: '.($product ? $product->formatQuantity($inventory?->Quantity ?? 0) : ($inventory?->Quantity ?? 0)));
                    }
                }

                $scannedSerials = [];
                foreach ($lines as $productId => $line) {
                    foreach ($line['serials'] as $serialNumber) {
                        $scannedSerials[$productId][] = strtoupper(trim($serialNumber));
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

                $saleLines = [];
                foreach ($lines as $productId => $line) {
                    $line['product_id'] = $productId;
                    if ((float) $line['quantity_per_unit'] != 1.0) {
                        $saleLines[] = $line;

                        continue;
                    }
                    foreach (StockBatches::priceSlices($products->get($productId), (float) $line['qty']) as $slice) {
                        $saleLines[] = array_merge($line, ['price' => $slice['price'], 'qty' => $slice['quantity']]);
                    }
                }

                $subtotal = 0;
                foreach ($saleLines as $line) {
                    $subtotal += $line['price'] * $line['qty'];
                }

                $discountAmount = 0.0;
                $usedDiscountIds = [];
                $activePromoByProduct = [];
                $discountPerUnitByLine = [];
                Discount::currentlyActive()
                    ->whereHas('products', fn ($q) => $q->whereIn('Product.ProductID', array_keys($quantitiesByProduct)))
                    ->with(['products' => fn ($q) => $q->whereIn('Product.ProductID', array_keys($quantitiesByProduct))])
                    ->get()
                    ->each(function ($promo) use (&$activePromoByProduct) {
                        foreach ($promo->products as $promoProduct) {
                            $activePromoByProduct[$promoProduct->ProductID] = $promo;
                        }
                    });

                foreach ($saleLines as $index => $line) {
                    $promo = $activePromoByProduct[$line['product_id']] ?? null;
                    if (! $promo) {
                        continue;
                    }
                    $perUnitDiscount = $line['price'] - $promo->discountedPrice($line['price']);
                    $discountAmount += $perUnitDiscount * $line['qty'];
                    $discountPerUnitByLine[$index] = $perUnitDiscount;
                    $usedDiscountIds[$promo->DiscountID] = true;
                }
                $discountAmount = round($discountAmount, 2);

                $distinctDiscountIds = array_keys($usedDiscountIds);
                $discount = count($distinctDiscountIds) === 1 ? Discount::find($distinctDiscountIds[0]) : null;

                $subtotal = round($subtotal, 2);
                $afterPromos = round($subtotal - $discountAmount, 2);
                $customDiscountAmount = $customDiscountPercent !== null ? round($afterPromos * $customDiscountPercent / 100, 2) : 0.0;
                $discountAmount = round($discountAmount + $customDiscountAmount, 2);
                $total = round($subtotal - $discountAmount, 2);
                $vatAmount = SalesTransaction::vatIncludedIn($total);

                if ($paymentAmount < $total) {
                    throw new \RuntimeException('Payment amount must be greater than or equal to total.');
                }

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

                $transaction = SalesTransaction::create([
                    'CustomerName' => $customerName,
                    'SalesTransactionDate' => now(),
                    'StaffID' => $staff->StaffID,
                    'VatApplied' => '12%',
                    'TotalAmount' => $total,
                    'Subtotal' => $subtotal,
                    'DiscountAmount' => $discountAmount,
                    'CustomDiscountPercent' => $customDiscountPercent,
                    'CustomDiscountAmount' => $customDiscountPercent !== null ? $customDiscountAmount : null,
                    'CustomDiscountApprovedBy' => $customDiscountApprovedBy,
                    'PromoCode' => $discount?->PromoCode,
                    'VatAmount' => $vatAmount,
                    'DiscountID' => $discount?->DiscountID,
                ]);

                Payment::create([
                    'PaymentAmount' => $paymentAmount,
                    'PaymentMethod' => $paymentMethod,
                    'ReceiptNumber' => 'RCT-'.str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT),
                    'SalesTransactionID' => $transaction->SalesTransactionID,
                    'ReferenceNumber' => $referenceNumber,
                    'BankName' => $bankName,
                    'AccountName' => $accountName,
                    'PaymentDate' => $paymentDate,
                    'PaymentTime' => $paymentTime,
                    'Remarks' => $remarks,
                    'ProofImagePath' => $proofPath,
                ]);

                $customShares = [];
                $sharedSoFar = 0.0;
                $lastIndex = array_key_last($saleLines);
                foreach ($saleLines as $index => $line) {
                    $lineAfterPromo = $line['price'] * $line['qty'] - ($discountPerUnitByLine[$index] ?? 0) * $line['qty'];
                    $share = $index === $lastIndex
                        ? round($customDiscountAmount - $sharedSoFar, 2)
                        : ($afterPromos > 0 ? round($customDiscountAmount * $lineAfterPromo / $afterPromos, 2) : 0.0);
                    $customShares[$index] = $share;
                    $sharedSoFar = round($sharedSoFar + $share, 2);
                }

                $salesItems = [];
                foreach ($saleLines as $index => $line) {
                    $perUnitDiscount = $discountPerUnitByLine[$index] ?? 0;
                    $salesItems[$index] = SalesItem::create([
                        'Quantity' => $line['qty'],
                        'Unit' => $line['unit'],
                        'BaseQuantity' => Quantity::normalize($line['qty'] * $line['quantity_per_unit']),
                        'UnitPrice' => $line['price'],
                        'DiscountAmount' => round($perUnitDiscount * $line['qty'] + $customShares[$index], 2),
                        'ProductID' => $line['product_id'],
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

                foreach ($salesItems as $salesItem) {
                    $inventory = $inventories->get($salesItem->ProductID);
                    $inventory->Quantity = Quantity::normalize($inventory->Quantity - $salesItem->BaseQuantity);
                    $inventory->Status = Inventory::resolveStatus($inventory->Quantity, $inventory->ReorderThreshold);
                    StockBatches::takingFor(
                        ['Reason' => StockBatchAllocation::REASON_SALE, 'SalesItemID' => $salesItem->SalesItemID],
                        fn () => $inventory->save()
                    );
                    $salesItem->update(['CostAmount' => StockBatches::costOfSalesItem($salesItem->SalesItemID)]);
                }

                return [
                    'receipt_number' => 'RCT-'.str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT),
                    'total' => $total,
                    'transaction_id' => $transaction->SalesTransactionID,
                    'product_ids' => array_keys($quantitiesByProduct),
                ];
            });
            $saleCompleted = true;
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } finally {
            $lock->release();
            if (! $saleCompleted) {
                PaymentProof::delete($proofPath);
            }
        }

        try {
            ReorderPoint::refresh($result['product_ids']);
        } catch (Throwable $e) {
            Log::error('Re-Order Point refresh after a sale failed: '.$e->getMessage());
        }

        if ($customDiscountPercent !== null) {
            $request->session()->forget('custom_discount_approval');
            $approverName = User::find($customDiscountApprovedBy)?->full_name ?? 'an Administrator';
            ActivityLog::record('pos.custom_discount_used', "Custom discount of {$customDiscountPercent}% given on {$result['receipt_number']} (approved by \"{$approverName}\")");
            ActionNotifier::toAdmins('Custom Discount Given', "A {$customDiscountPercent}% discount was given on {$result['receipt_number']} (approved by {$approverName}).", route('admin.reports.index'), 'percent', 'warning');
        }

        try {
            $completedTransaction = SalesTransaction::with(['items'])->find($result['transaction_id']);
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

    public function viewReceipt($receiptNumber, ThermalReceiptPrinter $printer)
    {
        $receipt = $this->buildReceiptData($this->findOwnTransaction($receiptNumber), $receiptNumber);

        return view('cashier.receipt', $receipt + [
            'printLines' => $printer->printLines($receipt),
            'printTail' => $printer->tailText(),
        ]);
    }

    public function receiptPrintLines($receiptNumber, ThermalReceiptPrinter $printer): JsonResponse
    {
        $receipt = $this->buildReceiptData($this->findOwnTransaction($receiptNumber), $receiptNumber);

        return response()->json([
            'lines' => $printer->lines($receipt),
            'payment_method' => $receipt['paymentMethod'],
        ]);
    }

    public function printThermalReceipt(Request $request, $receiptNumber, ThermalReceiptPrinter $printer)
    {
        if (! ThermalReceiptPrinter::isEnabled()) {
            return response()->json(['success' => false, 'message' => 'Direct thermal printing is turned off.'], 409);
        }

        $receipt = $this->buildReceiptData($this->findOwnTransaction($receiptNumber), $receiptNumber);

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

    private function findOwnTransaction(string $receiptNumber): SalesTransaction
    {
        $transactionId = (int) str_replace('RCT-', '', $receiptNumber);

        $staff = Staff::where('UserID', Auth::id())->first();

        $transaction = SalesTransaction::with(['items.product', 'payment', 'discount'])
            ->where('SalesTransactionID', $transactionId)
            ->where('StaffID', $staff->StaffID ?? 0)
            ->first();

        if (! $transaction) {
            abort(404, 'Receipt not found');
        }

        return $transaction;
    }

    private function buildReceiptData(SalesTransaction $transaction, string $receiptNumber): array
    {
        $sale = $transaction;
        $items = $transaction->items->map(function ($item) {
            return [
                'id' => $item->ProductID,
                'name' => $item->product?->ProductName ?? 'Unknown Product',
                'price' => $item->UnitPrice,
                'qty' => $item->Quantity,
                'qty_label' => ($item->Unit ?: UnitOfMeasure::DEFAULT) === UnitOfMeasure::DEFAULT
                    ? UnitOfMeasure::number($item->Quantity)
                    : $item->quantityLabel(),
            ];
        })->toArray();

        $hasDiscount = $sale->DiscountID !== null;
        $promoCode = null;
        $promoProductName = null;

        if ($sale->Subtotal !== null) {
            $subtotal = (float) $sale->Subtotal;
            $customDiscountAmount = (float) ($sale->CustomDiscountAmount ?? 0);
            $discountAmount = round((float) $sale->DiscountAmount - $customDiscountAmount, 2);
            $vatAmount = (float) $sale->VatAmount;
            $total = (float) $sale->TotalAmount;

            $discountRate = 0;
            if ($hasDiscount) {
                $promoCode = $sale->PromoCode ?? $sale->discount?->PromoCode;
                $promoProductIds = $sale->discount
                    ? $sale->discount->products()->pluck('Product.ProductID')->all()
                    : [];
                $promoLines = collect($items)->whereIn('id', $promoProductIds);
                $promoProductName = $promoLines->isNotEmpty() ? $promoLines->pluck('name')->implode(', ') : null;
                $promoProductSubtotal = $promoLines->sum(fn ($line) => $line['price'] * $line['qty']);
                $discountRate = $promoProductSubtotal > 0 ? round(($discountAmount / $promoProductSubtotal) * 100, 2) : 0;
            }
        } else {
            $subtotal = array_sum(array_map(function ($item) {
                return $item['price'] * $item['qty'];
            }, $items));

            $discountRate = 0;
            if ($sale->discount) {
                $discountRate = $sale->discount->DiscountRate ?? 0;
            }
            $discountAmount = $subtotal * ($discountRate / 100);
            $total = round($subtotal - $discountAmount, 2);
            $vatAmount = SalesTransaction::vatIncludedIn($total);
        }

        $payment = $transaction->payment;
        $paymentAmount = $payment ? ($payment->PaymentAmount ?? $total) : $total;
        $change = max(0, $paymentAmount - $total);
        $paymentMethod = $payment?->PaymentMethod ?? 'cash';

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
            'customDiscountPercent' => $sale->CustomDiscountPercent !== null ? (float) $sale->CustomDiscountPercent : null,
            'customDiscountAmount' => (float) ($sale->CustomDiscountAmount ?? 0),
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
