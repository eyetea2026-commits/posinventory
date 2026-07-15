<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\SalesTransaction;
use App\Models\SalesItem;
use App\Models\Billing;
use App\Models\Payment;
use App\Models\Discount;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Mail\OtpMail;

class CashierAuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.cashier.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required'],
            'password' => ['required'],
        ]);

        // Authenticate using username (name field) instead of email
        $loginCredentials = [
            'name' => $credentials['username'],
            'password' => $credentials['password']
        ];

        if (Auth::attempt($loginCredentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            // Check if user is active
            if (isset($user->is_active) && !$user->is_active) {
                Auth::logout();
                return back()->withErrors(['username' => 'Your account has been deactivated. Please contact the administrator.']);
            }

            if (! $user->isCashier()) {
                Auth::logout();
                return back()->withErrors(['username' => 'Unauthorized for cashier area.']);
            }

            ActivityLog::record('auth.login', "\"{$user->name}\" logged in (Cashier)", $user->id);

            return redirect()->intended('/cashier/pos');
        }

        return back()->withErrors(['username' => 'The provided credentials do not match our records.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    public function showForgot()
    {
        return view('auth.cashier.forgot');
    }

    public function sendOtp(Request $request)
    {
        $data = $request->validate(['email' => ['required','email']]);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->isCashier()) {
            return back()->withErrors(['email' => 'No cashier account found for that email.']);
        }

        $otp = rand(100000, 999999);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $data['email']],
            ['token' => (string) $otp, 'created_at' => now()]
        );

        Mail::to($data['email'])->send(new OtpMail($otp));

        return redirect()->route('cashier.otp.form')->with('email', $data['email']);
    }

    public function showOtpForm(Request $request)
    {
        $email = session('email') ?? $request->get('email');
        return view('auth.cashier.otp', ['email' => $email]);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate(['email' => ['required','email'], 'otp' => ['required']]);
        $record = DB::table('password_reset_tokens')->where('email', $data['email'])->first();
        if (! $record || $record->token !== $data['otp']) {
            return back()->withErrors(['otp' => 'Invalid OTP code.']);
        }
        return redirect()->route('cashier.password.reset.form', ['email' => $data['email']])->with('otp_verified', true);
    }

    public function showResetForm(Request $request)
    {
        $email = $request->get('email');
        $otpVerified = session('otp_verified');
        if (! $otpVerified) {
            return redirect()->route('cashier.forgot')->withErrors(['email' => 'OTP verification required.']);
        }
        return view('auth.cashier.reset', ['email' => $email]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required','confirmed','min:8'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->isCashier()) {
            return back()->withErrors(['email' => 'No cashier account found.']);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        return redirect()->route('cashier.login')->with('status', 'Password reset successful. You may login now.');
    }

    public function pos()
    {
        $products = Product::with('inventory')->get()->filter(function($product) {
            return $product->inventory && $product->inventory->Quantity > 0;
        });
        $discounts = Discount::orderBy('DiscountRate')->get();
        return view('cashier.pos', compact('products', 'discounts'));
    }

    // Polled by the POS screen so a discount an admin creates/updates
    // mid-shift shows up without the cashier reloading the page.
    public function discounts()
    {
        return response()->json([
            'discounts' => Discount::orderBy('DiscountRate')->get(['DiscountID', 'DiscountRate']),
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
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:Product,ProductID',
            'items.*.qty' => 'required|integer|min:1',
            'payment_method' => 'required|in:cash,gcash,bank,cheque',
            'discount_id' => 'required|integer|exists:Discount,DiscountID',
        ]);

        $items = $data['items'];
        // Customer Name is optional in the UI, so an empty string (converted to null
        // by the ConvertEmptyStringsToNull middleware) is common — input()'s default
        // only applies when the key is entirely absent, not when it's null/blank, so
        // that case has to be handled explicitly or SalesTransaction's NOT NULL
        // CustomerName column rejects the insert.
        $customerName = trim((string) $request->input('customer_name')) ?: 'Walk-in Customer';
        $discountId = (int) $data['discount_id'];
        $paymentMethod = $data['payment_method'];
        $accountNumber = $request->input('account_number');
        $paymentAmount = floatval($request->input('payment_amount', 0));

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
            $result = DB::transaction(function () use ($items, $quantitiesByProduct, $customerName, $discountId, $paymentMethod, $accountNumber, $paymentAmount, $user) {
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

                // Discounts are admin-managed policies now, not a rate a cashier
                // can type — look up the chosen row and use its rate, never a
                // client-submitted percentage.
                $discount = Discount::find($discountId);
                if (!$discount) {
                    throw new \RuntimeException('Selected discount is no longer available.');
                }
                $discountRate = (float) $discount->DiscountRate;

                $discountAmount = $subtotal * ($discountRate / 100);
                $vatAmount = ($subtotal - $discountAmount) * 0.12;
                $total = $subtotal - $discountAmount + $vatAmount;

                if ($paymentMethod === 'cash' && $paymentAmount < $total) {
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

                // Create billing
                $billing = Billing::create([
                    'CustomerName' => $customerName,
                    'VatApplied' => '12%',
                    'BillingAmount' => $total,
                    'BillingDate' => now(),
                    'DiscountID' => $discount->DiscountID,
                    'SalesTransactionID' => $transaction->SalesTransactionID,
                ]);

                // Create payment
                Payment::create([
                    'PaymentAmount' => $paymentAmount > 0 ? $paymentAmount : $total,
                    'PaymentMethod' => $paymentMethod,
                    'ReceiptNumber' => 'RCT-' . str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT),
                    'BillingID' => $billing->BillingID,
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

        $subtotal = array_sum(array_map(function ($item) {
            return $item['price'] * $item['qty'];
        }, $items));

        // Get discount info
        $discountRate = 0;
        if ($billing && $billing->discount) {
            $discountRate = $billing->discount->DiscountRate ?? 0;
        }
        $discountAmount = $subtotal * ($discountRate / 100);
        $vatAmount = ($subtotal - $discountAmount) * 0.12;
        $total = $subtotal - $discountAmount + $vatAmount;

        // Get payment info
        $payment = \App\Models\Payment::where('BillingID', $billing?->BillingID)->first();
        $paymentAmount = $payment ? ($payment->PaymentAmount ?? $total) : $total;
        $change = max(0, $paymentAmount - $total);
        $paymentMethod = $payment?->PaymentMethod ?? 'cash';

        $cashierName = Auth::user()->name;

        return view('cashier.receipt', [
            'receiptNumber' => $receiptNumber,
            'date' => $transaction->SalesTransactionDate->format('M d, Y h:i A'),
            'cashierName' => $cashierName,
            'customerName' => $transaction->CustomerName,
            'items' => $items,
            'subtotal' => $subtotal,
            'discountRate' => $discountRate,
            'discountAmount' => $discountAmount,
            'vatAmount' => $vatAmount,
            'total' => $total,
            'paymentMethod' => $paymentMethod,
            'paymentAmount' => $paymentAmount,
            'change' => $change,
        ]);
    }
}
