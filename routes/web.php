<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\CashierAuthController;

// The one sign-in entry point for the whole system — see AuthController for
// why this replaced separate admin/cashier login forms and a portal-picker
// landing page.
Route::get('/', [AuthController::class, 'showLogin'])->name('welcome');
// throttle:15,1 is an outer safety net only — the real brute-force defense is
// AuthController's own per-username RateLimiter (5 attempts/60s, with a
// friendly "too many attempts" message). This route limit used to be 6/min,
// which was tight enough that a handful of legitimate retries (typos, a
// couple of wrong-password attempts) could trip Laravel's raw, unstyled 429
// page before the friendly per-account lockout ever kicked in.
Route::post('/login', [AuthController::class, 'login'])->name('login.post')->middleware('throttle:15,1');

// Live role badge on the login form — an explicit, informed tradeoff: it
// discloses whether a typed username is a recognized Admin/Cashier account,
// re-opening a small username-enumeration surface the rest of the auth
// hardening otherwise closes. Rate limited to keep bulk probing slow, but
// generous enough that normal typing (including corrections) of a single
// username doesn't trip it — the client also only queries at 3+ characters
// and debounces, so legitimate use stays well under this. Do not reuse this
// for the forgot-password flow — /check-user-role below stays deliberately
// generic for that purpose.
Route::get('/login/role-lookup', [AuthController::class, 'lookupRole'])->name('login.role-lookup')->middleware('throttle:40,1');

// AJAX route to check user role for password reset eligibility. Deliberately
// unauthenticated — it's called from the pre-login "forgot password" screen,
// before any session exists — but the response is intentionally generic and
// never confirms/denies account existence or discloses a role, to prevent
// username enumeration. Rate limited to slow down probing.
Route::post('/check-user-role', function (\Illuminate\Http\Request $request) {
    $username = $request->input('username');

    if (!$username) {
        return response()->json(['error' => 'Username is required'], 400);
    }

    // Same generic message regardless of whether the account exists, its
    // role, or eligibility — an attacker can't distinguish "no such account"
    // from "found, but a cashier" from "found, admin, reset available".
    return response()->json([
        'message' => 'If this is a valid Administrator account, password reset instructions are available. Cashier accounts must contact an Administrator for a password reset.',
    ]);
})->name('check.user.role')->middleware('throttle:10,1');

// API route for barcode scanning (POS) — cashier-only, mirrors every other
// data-bearing route's auth requirement instead of being publicly reachable.
Route::get('/api/products/barcode/{barcode}', function ($barcode) {
    $product = \App\Models\Product::with('inventory')
        ->where('Barcode', $barcode)
        ->first();

    if (!$product) {
        return response()->json(['product' => null], 404);
    }

    return response()->json(['product' => $product]);
})->middleware(['auth', 'role:cashier', 'throttle:60,1']);

// API route for getting customers (POS) — cashier-only; previously reachable
// with no authentication at all.
Route::get('/api/customers/search', function (\Illuminate\Http\Request $request) {
    $search = $request->query('q', '');

    $customers = \App\Models\Customer::where('CustomerName', 'like', "%{$search}%")
        ->orWhere('Email', 'like', "%{$search}%")
        ->limit(10)
        ->get();

    return response()->json(['customers' => $customers]);
})->middleware(['auth', 'role:cashier', 'throttle:60,1']);

// Smart post-login landing spot for the "dashboard" route name (nothing in
// this app links here directly anymore — admin/cashier logins redirect
// straight to their own dashboard/POS — kept as a harmless fallback in case
// anything still resolves an old intended-redirect to this URL).
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

// Admin routes
Route::prefix('admin')->group(function () {
    Route::post('logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    Route::get('forgot-password', [AdminAuthController::class, 'showForgot'])->name('admin.forgot');
    Route::post('forgot-password', [AdminAuthController::class, 'sendOtp'])->name('admin.forgot.post')->middleware('throttle:6,1');
    Route::get('otp', [AdminAuthController::class, 'showOtpForm'])->name('admin.otp.form');
    Route::post('otp', [AdminAuthController::class, 'verifyOtp'])->name('admin.otp.verify')->middleware('throttle:10,1');
    Route::get('reset-password', [AdminAuthController::class, 'showResetForm'])->name('admin.password.reset.form');
    Route::post('reset-password', [AdminAuthController::class, 'resetPassword'])->name('admin.password.reset');

    Route::get('dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])
        ->name('admin.dashboard')
        ->middleware(['auth', 'role:admin']);
    Route::get('dashboard/live-inventory', [App\Http\Controllers\Admin\DashboardController::class, 'liveInventory'])
        ->name('admin.dashboard.live-inventory')
        ->middleware(['auth', 'role:admin']);

    Route::get('users', [App\Http\Controllers\Admin\UserController::class, 'index'])
        ->name('admin.users.index')
        ->middleware(['auth', 'role:admin']);
    Route::get('users/create', [App\Http\Controllers\Admin\UserController::class, 'create'])
        ->name('admin.users.create')
        ->middleware(['auth', 'role:admin']);
    Route::post('users/check-name', [App\Http\Controllers\Admin\UserController::class, 'checkName'])
        ->name('admin.users.check-name')
        ->middleware(['auth', 'role:admin']);
    Route::post('users', [App\Http\Controllers\Admin\UserController::class, 'store'])
        ->name('admin.users.store')
        ->middleware(['auth', 'role:admin']);
    Route::get('users/{user}/edit', [App\Http\Controllers\Admin\UserController::class, 'edit'])
        ->name('admin.users.edit')
        ->middleware(['auth', 'role:admin']);
    Route::put('users/{user}', [App\Http\Controllers\Admin\UserController::class, 'update'])
        ->name('admin.users.update')
        ->middleware(['auth', 'role:admin']);
    Route::post('users/{user}/deactivate', [App\Http\Controllers\Admin\UserController::class, 'deactivate'])
        ->name('admin.users.deactivate')
        ->middleware(['auth', 'role:admin']);
    Route::post('users/{user}/activate', [App\Http\Controllers\Admin\UserController::class, 'activate'])
        ->name('admin.users.activate')
        ->middleware(['auth', 'role:admin']);
    Route::delete('users/{user}', [App\Http\Controllers\Admin\UserController::class, 'destroy'])
        ->name('admin.users.destroy')
        ->middleware(['auth', 'role:admin']);
    Route::get('users/{user}', [App\Http\Controllers\Admin\UserController::class, 'show'])
        ->name('admin.users.show')
        ->middleware(['auth', 'role:admin']);

    Route::get('products', [App\Http\Controllers\Admin\ProductController::class, 'index'])
        ->name('admin.products.index')->middleware(['auth', 'role:admin']);
    Route::get('products/create', [App\Http\Controllers\Admin\ProductController::class, 'create'])
        ->name('admin.products.create')->middleware(['auth', 'role:admin']);
    Route::post('products/check-name', [App\Http\Controllers\Admin\ProductController::class, 'checkName'])
        ->name('admin.products.check-name')->middleware(['auth', 'role:admin']);
    Route::post('products', [App\Http\Controllers\Admin\ProductController::class, 'store'])
        ->name('admin.products.store')->middleware(['auth', 'role:admin']);
    Route::get('products/{product}', [App\Http\Controllers\Admin\ProductController::class, 'show'])
        ->name('admin.products.show')->middleware(['auth', 'role:admin']);
    Route::get('products/{product}/edit', [App\Http\Controllers\Admin\ProductController::class, 'edit'])
        ->name('admin.products.edit')->middleware(['auth', 'role:admin']);
    Route::put('products/{product}', [App\Http\Controllers\Admin\ProductController::class, 'update'])
        ->name('admin.products.update')->middleware(['auth', 'role:admin']);
    Route::delete('products/{product}', [App\Http\Controllers\Admin\ProductController::class, 'destroy'])
        ->name('admin.products.destroy')->middleware(['auth', 'role:admin']);

    // Inventory (view-only)
    Route::get('inventory', [App\Http\Controllers\Admin\InventoryController::class, 'index'])
        ->name('admin.inventory.index')->middleware(['auth', 'role:admin']);
    Route::get('inventory/{product}', [App\Http\Controllers\Admin\InventoryController::class, 'show'])
        ->name('admin.inventory.show')->middleware(['auth', 'role:admin']);

    // Categories
    Route::get('categories', [App\Http\Controllers\Admin\CategoryController::class, 'index'])
        ->name('admin.categories.index')->middleware(['auth', 'role:admin']);
    Route::get('categories/create', [App\Http\Controllers\Admin\CategoryController::class, 'create'])
        ->name('admin.categories.create')->middleware(['auth', 'role:admin']);
    Route::post('categories', [App\Http\Controllers\Admin\CategoryController::class, 'store'])
        ->name('admin.categories.store')->middleware(['auth', 'role:admin']);
    Route::get('categories/{category}/edit', [App\Http\Controllers\Admin\CategoryController::class, 'edit'])
        ->name('admin.categories.edit')->middleware(['auth', 'role:admin']);
    Route::put('categories/{category}', [App\Http\Controllers\Admin\CategoryController::class, 'update'])
        ->name('admin.categories.update')->middleware(['auth', 'role:admin']);
    Route::delete('categories/{category}', [App\Http\Controllers\Admin\CategoryController::class, 'destroy'])
        ->name('admin.categories.destroy')->middleware(['auth', 'role:admin']);

    // Discounts
    Route::get('discounts', [App\Http\Controllers\Admin\DiscountController::class, 'index'])
        ->name('admin.discounts.index')->middleware(['auth', 'role:admin']);
    Route::get('discounts/create', [App\Http\Controllers\Admin\DiscountController::class, 'create'])
        ->name('admin.discounts.create')->middleware(['auth', 'role:admin']);
    Route::post('discounts', [App\Http\Controllers\Admin\DiscountController::class, 'store'])
        ->name('admin.discounts.store')->middleware(['auth', 'role:admin']);
    Route::get('discounts/{discount}/edit', [App\Http\Controllers\Admin\DiscountController::class, 'edit'])
        ->name('admin.discounts.edit')->middleware(['auth', 'role:admin']);
    Route::put('discounts/{discount}', [App\Http\Controllers\Admin\DiscountController::class, 'update'])
        ->name('admin.discounts.update')->middleware(['auth', 'role:admin']);
    Route::delete('discounts/{discount}', [App\Http\Controllers\Admin\DiscountController::class, 'destroy'])
        ->name('admin.discounts.destroy')->middleware(['auth', 'role:admin']);

    // Damages
    Route::get('damages', [App\Http\Controllers\Admin\DamageController::class, 'index'])
        ->name('admin.damages.index')->middleware(['auth', 'role:admin']);
    Route::get('damages/create', [App\Http\Controllers\Admin\DamageController::class, 'create'])
        ->name('admin.damages.create')->middleware(['auth', 'role:admin']);
    Route::post('damages', [App\Http\Controllers\Admin\DamageController::class, 'store'])
        ->name('admin.damages.store')->middleware(['auth', 'role:admin']);
    Route::get('damages/{damage}/print', [App\Http\Controllers\Admin\DamageController::class, 'printReport'])
        ->name('admin.damages.print')->middleware(['auth', 'role:admin']);
    Route::get('damages/{damage}', [App\Http\Controllers\Admin\DamageController::class, 'show'])
        ->name('admin.damages.show')->middleware(['auth', 'role:admin']);
    Route::get('damages/{damage}/edit', [App\Http\Controllers\Admin\DamageController::class, 'edit'])
        ->name('admin.damages.edit')->middleware(['auth', 'role:admin']);
    Route::put('damages/{damage}', [App\Http\Controllers\Admin\DamageController::class, 'update'])
        ->name('admin.damages.update')->middleware(['auth', 'role:admin']);
    Route::delete('damages/{damage}', [App\Http\Controllers\Admin\DamageController::class, 'destroy'])
        ->name('admin.damages.destroy')->middleware(['auth', 'role:admin']);
    Route::post('damages/{damage}/mark-supplier-return', [App\Http\Controllers\Admin\DamageController::class, 'markForSupplierReturn'])
        ->name('admin.damages.mark-supplier-return')->middleware(['auth', 'role:admin']);
    Route::post('damages/{damage}/confirm-supplier-return', [App\Http\Controllers\Admin\DamageController::class, 'confirmSupplierReturn'])
        ->name('admin.damages.confirm-supplier-return')->middleware(['auth', 'role:admin']);
    Route::post('damages/{damage}/dispose', [App\Http\Controllers\Admin\DamageController::class, 'markDisposed'])
        ->name('admin.damages.dispose')->middleware(['auth', 'role:admin']);
    Route::post('damages/{damage}/receive-replacement', [App\Http\Controllers\Admin\DamageController::class, 'receiveReplacement'])
        ->name('admin.damages.receive-replacement')->middleware(['auth', 'role:admin']);
    Route::post('damages/{damage}/cancel', [App\Http\Controllers\Admin\DamageController::class, 'cancel'])
        ->name('admin.damages.cancel')->middleware(['auth', 'role:admin']);
    Route::post('damages/bulk-return-to-supplier', [App\Http\Controllers\Admin\DamageController::class, 'bulkConfirmSupplierReturn'])
        ->name('admin.damages.bulk-return-to-supplier')->middleware(['auth', 'role:admin']);

    Route::get('suppliers', [App\Http\Controllers\Admin\SupplierController::class, 'index'])
        ->name('admin.suppliers.index')->middleware(['auth', 'role:admin']);
    Route::get('suppliers/create', [App\Http\Controllers\Admin\SupplierController::class, 'create'])
        ->name('admin.suppliers.create')->middleware(['auth', 'role:admin']);
    Route::post('suppliers', [App\Http\Controllers\Admin\SupplierController::class, 'store'])
        ->name('admin.suppliers.store')->middleware(['auth', 'role:admin']);
    Route::get('suppliers/{supplier}/edit', [App\Http\Controllers\Admin\SupplierController::class, 'edit'])
        ->name('admin.suppliers.edit')->middleware(['auth', 'role:admin']);
    Route::put('suppliers/{supplier}', [App\Http\Controllers\Admin\SupplierController::class, 'update'])
        ->name('admin.suppliers.update')->middleware(['auth', 'role:admin']);
    Route::get('suppliers/{supplier}', [App\Http\Controllers\Admin\SupplierController::class, 'show'])
        ->name('admin.suppliers.show')->middleware(['auth', 'role:admin']);
    Route::get('suppliers/{supplier}/purchase-orders/{purchaseOrder}', [App\Http\Controllers\Admin\SupplierController::class, 'purchaseOrderDetails'])
        ->name('admin.suppliers.purchase-order-details')->middleware(['auth', 'role:admin']);

    Route::get('products/{product}/suppliers', [App\Http\Controllers\Admin\ProductSupplierController::class, 'index'])
        ->name('admin.products.suppliers.index')->middleware(['auth', 'role:admin']);
    Route::post('products/{product}/suppliers', [App\Http\Controllers\Admin\ProductSupplierController::class, 'store'])
        ->name('admin.products.suppliers.store')->middleware(['auth', 'role:admin']);
    Route::post('product-suppliers/{productSupplier}/prefer', [App\Http\Controllers\Admin\ProductSupplierController::class, 'markPreferred'])
        ->name('admin.product-suppliers.prefer')->middleware(['auth', 'role:admin']);
    Route::delete('product-suppliers/{productSupplier}', [App\Http\Controllers\Admin\ProductSupplierController::class, 'destroy'])
        ->name('admin.product-suppliers.destroy')->middleware(['auth', 'role:admin']);

    Route::get('stock-receivings', [App\Http\Controllers\Admin\StockReceivingController::class, 'index'])
        ->name('admin.stock-receivings.index')->middleware(['auth', 'role:admin']);
    Route::get('stock-receivings/create', [App\Http\Controllers\Admin\StockReceivingController::class, 'create'])
        ->name('admin.stock-receivings.create')->middleware(['auth', 'role:admin']);
    Route::post('stock-receivings', [App\Http\Controllers\Admin\StockReceivingController::class, 'store'])
        ->name('admin.stock-receivings.store')->middleware(['auth', 'role:admin']);

    Route::get('purchase-orders', [App\Http\Controllers\Admin\PurchaseOrderController::class, 'index'])
        ->name('admin.purchase-orders.index')->middleware(['auth', 'role:admin']);
    Route::get('purchase-orders/create', [App\Http\Controllers\Admin\PurchaseOrderController::class, 'create'])
        ->name('admin.purchase-orders.create')->middleware(['auth', 'role:admin']);
    Route::get('purchase-orders/create-from-reorder/{product}', [App\Http\Controllers\Admin\PurchaseOrderController::class, 'createFromReorder'])
        ->name('admin.purchase-orders.create-from-reorder')->middleware(['auth', 'role:admin']);
    Route::post('purchase-orders/create-from-reorder/{product}', [App\Http\Controllers\Admin\PurchaseOrderController::class, 'storeFromReorder'])
        ->name('admin.purchase-orders.store-from-reorder')->middleware(['auth', 'role:admin']);
    Route::post('purchase-orders', [App\Http\Controllers\Admin\PurchaseOrderController::class, 'store'])
        ->name('admin.purchase-orders.store')->middleware(['auth', 'role:admin']);
    Route::get('purchase-orders/{purchaseOrder}', [App\Http\Controllers\Admin\PurchaseOrderController::class, 'show'])
        ->name('admin.purchase-orders.show')->middleware(['auth', 'role:admin']);
    Route::get('purchase-orders/{purchaseOrder}/edit', [App\Http\Controllers\Admin\PurchaseOrderController::class, 'edit'])
        ->name('admin.purchase-orders.edit')->middleware(['auth', 'role:admin']);
    Route::put('purchase-orders/{purchaseOrder}', [App\Http\Controllers\Admin\PurchaseOrderController::class, 'update'])
        ->name('admin.purchase-orders.update')->middleware(['auth', 'role:admin']);
    Route::post('purchase-orders/{purchaseOrder}/submit', [App\Http\Controllers\Admin\PurchaseOrderController::class, 'submit'])
        ->name('admin.purchase-orders.submit')->middleware(['auth', 'role:admin']);
    Route::post('purchase-orders/{purchaseOrder}/approve', [App\Http\Controllers\Admin\PurchaseOrderController::class, 'approve'])
        ->name('admin.purchase-orders.approve')->middleware(['auth', 'role:admin']);
    Route::post('purchase-orders/{purchaseOrder}/cancel', [App\Http\Controllers\Admin\PurchaseOrderController::class, 'cancel'])
        ->name('admin.purchase-orders.cancel')->middleware(['auth', 'role:admin']);
    Route::get('purchase-orders/{purchaseOrder}/export', [App\Http\Controllers\Admin\PurchaseOrderController::class, 'export'])
        ->name('admin.purchase-orders.export')->middleware(['auth', 'role:admin']);

    Route::get('stock-adjustments', [App\Http\Controllers\Admin\StockAdjustmentController::class, 'index'])
        ->name('admin.stock-adjustments.index')->middleware(['auth', 'role:admin']);
    Route::get('stock-adjustments/create', [App\Http\Controllers\Admin\StockAdjustmentController::class, 'create'])
        ->name('admin.stock-adjustments.create')->middleware(['auth', 'role:admin']);
    Route::post('stock-adjustments', [App\Http\Controllers\Admin\StockAdjustmentController::class, 'store'])
        ->name('admin.stock-adjustments.store')->middleware(['auth', 'role:admin']);

    Route::get('sales-returns', [App\Http\Controllers\Admin\SalesReturnController::class, 'index'])
        ->name('admin.sales-returns.index')->middleware(['auth', 'role:admin']);
    Route::get('sales-returns/{salesReturn}', [App\Http\Controllers\Admin\SalesReturnController::class, 'show'])
        ->name('admin.sales-returns.show')->middleware(['auth', 'role:admin']);
    Route::post('sales-returns/{salesReturn}/approve', [App\Http\Controllers\Admin\SalesReturnController::class, 'approve'])
        ->name('admin.sales-returns.approve')->middleware(['auth', 'role:admin']);
    Route::post('sales-returns/{salesReturn}/decline', [App\Http\Controllers\Admin\SalesReturnController::class, 'decline'])
        ->name('admin.sales-returns.decline')->middleware(['auth', 'role:admin']);

    Route::get('notifications', [App\Http\Controllers\Admin\NotificationController::class, 'index'])
        ->name('admin.notifications.index')->middleware(['auth', 'role:admin']);
    Route::post('notifications/{notification}/read', [App\Http\Controllers\Admin\NotificationController::class, 'markAsRead'])
        ->name('admin.notifications.read')->middleware(['auth', 'role:admin']);
    Route::post('notifications/read-all', [App\Http\Controllers\Admin\NotificationController::class, 'markAllAsRead'])
        ->name('admin.notifications.read-all')->middleware(['auth', 'role:admin']);

    Route::get('reports', [App\Http\Controllers\Admin\ReportController::class, 'index'])
        ->name('admin.reports.index')->middleware(['auth', 'role:admin']);
    Route::get('reports/preview', [App\Http\Controllers\Admin\ReportController::class, 'preview'])
        ->name('admin.reports.preview')->middleware(['auth', 'role:admin']);
    Route::get('reports/export', [App\Http\Controllers\Admin\ReportController::class, 'export'])
        ->name('admin.reports.export')->middleware(['auth', 'role:admin']);
    Route::get('reports/details', [App\Http\Controllers\Admin\ReportController::class, 'details'])
        ->name('admin.reports.details')->middleware(['auth', 'role:admin']);
});

// Cashier routes
Route::prefix('cashier')->group(function () {
    Route::post('logout', [CashierAuthController::class, 'logout'])->name('cashier.logout');

    // Forgot password functionality is disabled for Cashier - Only Administrator can reset passwords
    // Cashier users must contact the Administrator for password resets

    Route::get('pos', [CashierAuthController::class, 'pos'])->name('cashier.pos')->middleware(['auth', 'role:cashier']);
    Route::get('pos/discounts', [CashierAuthController::class, 'discounts'])->name('cashier.pos.discounts')->middleware(['auth', 'role:cashier']);
    Route::post('pos/process-sale', [CashierAuthController::class, 'processSale'])->name('cashier.process-sale')->middleware(['auth', 'role:cashier']);
    Route::get('transactions', [CashierAuthController::class, 'transactions'])->name('cashier.transactions')->middleware(['auth', 'role:cashier']);

    // Return/Refund/Replacement routes
    Route::get('refunds', [App\Http\Controllers\Cashier\CashierReturnController::class, 'index'])->name('cashier.refunds')->middleware(['auth', 'role:cashier']);
    Route::get('refunds/search', [App\Http\Controllers\Cashier\CashierReturnController::class, 'searchTransaction'])->name('cashier.refunds.search')->middleware(['auth', 'role:cashier']);
    Route::post('refunds/create', [App\Http\Controllers\Cashier\CashierReturnController::class, 'createRefund'])->name('cashier.refunds.create')->middleware(['auth', 'role:cashier']);
    Route::get('refunds/{transactionId}/transaction', [App\Http\Controllers\Cashier\CashierReturnController::class, 'getTransactionDetails'])->name('cashier.refunds.transaction')->middleware(['auth', 'role:cashier']);
    Route::post('refunds/{salesReturn}/process-refund', [App\Http\Controllers\Cashier\CashierReturnController::class, 'processRefund'])->name('cashier.refunds.process')->middleware(['auth', 'role:cashier']);
    Route::post('refunds/{salesReturn}/process-replacement', [App\Http\Controllers\Cashier\CashierReturnController::class, 'processReplacement'])->name('cashier.refunds.process-replacement')->middleware(['auth', 'role:cashier']);
    Route::get('refunds/{salesReturn}/details', [App\Http\Controllers\Cashier\CashierReturnController::class, 'getRefundDetails'])->name('cashier.refunds.details')->middleware(['auth', 'role:cashier']);
    Route::get('refunds/{salesReturn}/slip', [App\Http\Controllers\Cashier\CashierReturnController::class, 'printReplacementSlip'])->name('cashier.refunds.slip')->middleware(['auth', 'role:cashier']);
    Route::get('refunds/{salesReturn}/receipt', [App\Http\Controllers\Cashier\CashierReturnController::class, 'printRefundReceipt'])->name('cashier.refunds.receipt')->middleware(['auth', 'role:cashier']);
    Route::get('replacement-inventory/search', [App\Http\Controllers\Cashier\CashierReturnController::class, 'searchReplacementInventory'])->name('cashier.replacement-inventory.search')->middleware(['auth', 'role:cashier']);
    Route::get('stats', [App\Http\Controllers\Cashier\CashierReturnController::class, 'getCashierStats'])->name('cashier.stats')->middleware(['auth', 'role:cashier']);

    // Notification routes
    Route::get('notifications', [App\Http\Controllers\Cashier\NotificationController::class, 'index'])
        ->name('cashier.notifications.index')->middleware(['auth', 'role:cashier']);
    Route::post('notifications/{notification}/read', [App\Http\Controllers\Cashier\NotificationController::class, 'markAsRead'])
        ->name('cashier.notifications.read')->middleware(['auth', 'role:cashier']);
    Route::post('notifications/read-all', [App\Http\Controllers\Cashier\NotificationController::class, 'markAllAsRead'])
        ->name('cashier.notifications.read-all')->middleware(['auth', 'role:cashier']);

    // Receipt route (REQ102)
    Route::get('receipt/{receiptNumber}', [CashierAuthController::class, 'viewReceipt'])->name('cashier.receipt')->middleware(['auth', 'role:cashier']);
});