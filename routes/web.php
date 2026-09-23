<?php

use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DamageController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\LoginSecurityController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductSupplierController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SalesReturnController;
use App\Http\Controllers\Admin\StockAdjustmentController;
use App\Http\Controllers\Admin\StockReceivingController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\CashierAuthController;
use App\Http\Controllers\Cashier\CashierReturnController;
use App\Http\Controllers\DashboardController;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
Route::post('/check-user-role', function (Request $request) {
    $username = $request->input('username');

    if (! $username) {
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
    $product = Product::with('inventory')
        ->where('Barcode', $barcode)
        ->first();

    if (! $product) {
        return response()->json(['product' => null], 404);
    }

    return response()->json(['product' => $product]);
})->middleware(['auth', 'role:cashier', 'throttle:60,1']);

// API route for getting customers (POS) — cashier-only; previously reachable
// with no authentication at all.
Route::get('/api/customers/search', function (Request $request) {
    $search = $request->query('q', '');

    $customers = Customer::where('CustomerName', 'like', "%{$search}%")
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

    Route::get('users', [UserController::class, 'index'])
        ->name('admin.users.index')
        ->middleware(['auth', 'role:admin']);
    Route::get('users/create', [UserController::class, 'create'])
        ->name('admin.users.create')
        ->middleware(['auth', 'role:admin']);
    Route::post('users/check-name', [UserController::class, 'checkName'])
        ->name('admin.users.check-name')
        ->middleware(['auth', 'role:admin']);
    Route::post('users', [UserController::class, 'store'])
        ->name('admin.users.store')
        ->middleware(['auth', 'role:admin']);
    Route::get('users/{user}/edit', [UserController::class, 'edit'])
        ->name('admin.users.edit')
        ->middleware(['auth', 'role:admin']);
    Route::put('users/{user}', [UserController::class, 'update'])
        ->name('admin.users.update')
        ->middleware(['auth', 'role:admin']);
    Route::post('users/verify-password', [UserController::class, 'verifyPassword'])
        ->name('admin.users.verify-password')
        ->middleware(['auth', 'role:admin']);
    Route::patch('users/{user}/reset-password', [UserController::class, 'resetPassword'])
        ->name('admin.users.reset-password')
        ->middleware(['auth', 'role:admin']);
    Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])
        ->name('admin.users.deactivate')
        ->middleware(['auth', 'role:admin']);
    Route::post('users/{user}/activate', [UserController::class, 'activate'])
        ->name('admin.users.activate')
        ->middleware(['auth', 'role:admin']);
    Route::delete('users/{user}', [UserController::class, 'destroy'])
        ->name('admin.users.destroy')
        ->middleware(['auth', 'role:admin']);
    Route::get('users/{user}', [UserController::class, 'show'])
        ->name('admin.users.show')
        ->middleware(['auth', 'role:admin']);

    Route::get('products', [ProductController::class, 'index'])
        ->name('admin.products.index')->middleware(['auth', 'role:admin']);
    Route::get('products/create', [ProductController::class, 'create'])
        ->name('admin.products.create')->middleware(['auth', 'role:admin']);
    Route::post('products/check-name', [ProductController::class, 'checkName'])
        ->name('admin.products.check-name')->middleware(['auth', 'role:admin']);
    Route::post('products', [ProductController::class, 'store'])
        ->name('admin.products.store')->middleware(['auth', 'role:admin']);
    Route::get('products/{product}', [ProductController::class, 'show'])
        ->name('admin.products.show')->middleware(['auth', 'role:admin']);
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])
        ->name('admin.products.edit')->middleware(['auth', 'role:admin']);
    Route::put('products/{product}', [ProductController::class, 'update'])
        ->name('admin.products.update')->middleware(['auth', 'role:admin']);
    Route::delete('products/{product}', [ProductController::class, 'destroy'])
        ->name('admin.products.destroy')->middleware(['auth', 'role:admin']);

    // Inventory (view-only)
    Route::get('inventory', [InventoryController::class, 'index'])
        ->name('admin.inventory.index')->middleware(['auth', 'role:admin']);
    Route::get('inventory/{product}', [InventoryController::class, 'show'])
        ->name('admin.inventory.show')->middleware(['auth', 'role:admin']);

    // Categories
    Route::get('categories', [CategoryController::class, 'index'])
        ->name('admin.categories.index')->middleware(['auth', 'role:admin']);
    Route::get('categories/create', [CategoryController::class, 'create'])
        ->name('admin.categories.create')->middleware(['auth', 'role:admin']);
    Route::post('categories', [CategoryController::class, 'store'])
        ->name('admin.categories.store')->middleware(['auth', 'role:admin']);
    Route::post('categories/check-name', [CategoryController::class, 'checkName'])
        ->name('admin.categories.check-name')->middleware(['auth', 'role:admin']);
    Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])
        ->name('admin.categories.edit')->middleware(['auth', 'role:admin']);
    Route::put('categories/{category}', [CategoryController::class, 'update'])
        ->name('admin.categories.update')->middleware(['auth', 'role:admin']);
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])
        ->name('admin.categories.destroy')->middleware(['auth', 'role:admin']);

    // Brands (Category Module — every Brand belongs to exactly one Category)
    Route::get('brands', [BrandController::class, 'index'])
        ->name('admin.brands.index')->middleware(['auth', 'role:admin']);
    Route::get('brands/create', [BrandController::class, 'create'])
        ->name('admin.brands.create')->middleware(['auth', 'role:admin']);
    Route::post('brands', [BrandController::class, 'store'])
        ->name('admin.brands.store')->middleware(['auth', 'role:admin']);
    Route::get('brands/{brand}', [BrandController::class, 'show'])
        ->name('admin.brands.show')->middleware(['auth', 'role:admin']);
    Route::get('brands/{brand}/edit', [BrandController::class, 'edit'])
        ->name('admin.brands.edit')->middleware(['auth', 'role:admin']);
    Route::put('brands/{brand}', [BrandController::class, 'update'])
        ->name('admin.brands.update')->middleware(['auth', 'role:admin']);
    Route::delete('brands/{brand}', [BrandController::class, 'destroy'])
        ->name('admin.brands.destroy')->middleware(['auth', 'role:admin']);

    // Discounts
    Route::get('discounts', [DiscountController::class, 'index'])
        ->name('admin.discounts.index')->middleware(['auth', 'role:admin']);
    Route::get('discounts/create', [DiscountController::class, 'create'])
        ->name('admin.discounts.create')->middleware(['auth', 'role:admin']);
    Route::post('discounts', [DiscountController::class, 'store'])
        ->name('admin.discounts.store')->middleware(['auth', 'role:admin']);
    Route::post('discounts/check-promo-code', [DiscountController::class, 'checkPromoCode'])
        ->name('admin.discounts.check-promo-code')->middleware(['auth', 'role:admin']);
    // Must stay before the wildcard discounts/{discount} routes below —
    // both are static segments that would otherwise be swallowed by it.
    Route::get('discounts/history', [DiscountController::class, 'history'])
        ->name('admin.discounts.history')->middleware(['auth', 'role:admin']);
    Route::get('discounts/{discount}/edit', [DiscountController::class, 'edit'])
        ->name('admin.discounts.edit')->middleware(['auth', 'role:admin']);
    Route::put('discounts/{discount}', [DiscountController::class, 'update'])
        ->name('admin.discounts.update')->middleware(['auth', 'role:admin']);
    Route::delete('discounts/{discount}', [DiscountController::class, 'destroy'])
        ->name('admin.discounts.destroy')->middleware(['auth', 'role:admin']);
    Route::post('discounts/{discount}/products', [DiscountController::class, 'assignProducts'])
        ->name('admin.discounts.assign-products')->middleware(['auth', 'role:admin']);
    Route::delete('discounts/{discount}/products/{product}', [DiscountController::class, 'detachProduct'])
        ->name('admin.discounts.detach-product')->middleware(['auth', 'role:admin']);
    Route::get('discounts/{discount}', [DiscountController::class, 'show'])
        ->name('admin.discounts.show')->middleware(['auth', 'role:admin']);

    // Damages
    Route::get('damages', [DamageController::class, 'index'])
        ->name('admin.damages.index')->middleware(['auth', 'role:admin']);
    Route::get('damages/create', [DamageController::class, 'create'])
        ->name('admin.damages.create')->middleware(['auth', 'role:admin']);
    Route::post('damages', [DamageController::class, 'store'])
        ->name('admin.damages.store')->middleware(['auth', 'role:admin']);
    Route::get('damages/{damage}/print', [DamageController::class, 'printReport'])
        ->name('admin.damages.print')->middleware(['auth', 'role:admin']);
    Route::get('damages/{damage}', [DamageController::class, 'show'])
        ->name('admin.damages.show')->middleware(['auth', 'role:admin']);
    Route::get('damages/{damage}/edit', [DamageController::class, 'edit'])
        ->name('admin.damages.edit')->middleware(['auth', 'role:admin']);
    Route::put('damages/{damage}', [DamageController::class, 'update'])
        ->name('admin.damages.update')->middleware(['auth', 'role:admin']);
    Route::delete('damages/{damage}', [DamageController::class, 'destroy'])
        ->name('admin.damages.destroy')->middleware(['auth', 'role:admin']);
    Route::post('damages/{damage}/mark-supplier-return', [DamageController::class, 'markForSupplierReturn'])
        ->name('admin.damages.mark-supplier-return')->middleware(['auth', 'role:admin']);
    Route::post('damages/{damage}/confirm-supplier-return', [DamageController::class, 'confirmSupplierReturn'])
        ->name('admin.damages.confirm-supplier-return')->middleware(['auth', 'role:admin']);
    Route::post('damages/{damage}/dispose', [DamageController::class, 'markDisposed'])
        ->name('admin.damages.dispose')->middleware(['auth', 'role:admin']);
    Route::post('damages/{damage}/receive-replacement', [DamageController::class, 'receiveReplacement'])
        ->name('admin.damages.receive-replacement')->middleware(['auth', 'role:admin']);
    Route::post('damages/{damage}/cancel', [DamageController::class, 'cancel'])
        ->name('admin.damages.cancel')->middleware(['auth', 'role:admin']);
    Route::post('damages/bulk-return-to-supplier', [DamageController::class, 'bulkConfirmSupplierReturn'])
        ->name('admin.damages.bulk-return-to-supplier')->middleware(['auth', 'role:admin']);

    Route::get('suppliers', [SupplierController::class, 'index'])
        ->name('admin.suppliers.index')->middleware(['auth', 'role:admin']);
    Route::get('suppliers/create', [SupplierController::class, 'create'])
        ->name('admin.suppliers.create')->middleware(['auth', 'role:admin']);
    Route::post('suppliers', [SupplierController::class, 'store'])
        ->name('admin.suppliers.store')->middleware(['auth', 'role:admin']);
    Route::post('suppliers/check-name', [SupplierController::class, 'checkName'])
        ->name('admin.suppliers.check-name')->middleware(['auth', 'role:admin']);
    Route::get('suppliers/{supplier}/edit', [SupplierController::class, 'edit'])
        ->name('admin.suppliers.edit')->middleware(['auth', 'role:admin']);
    Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])
        ->name('admin.suppliers.update')->middleware(['auth', 'role:admin']);
    Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])
        ->name('admin.suppliers.show')->middleware(['auth', 'role:admin']);
    Route::get('suppliers/{supplier}/purchase-orders/{purchaseOrder}', [SupplierController::class, 'purchaseOrderDetails'])
        ->name('admin.suppliers.purchase-order-details')->middleware(['auth', 'role:admin']);

    Route::get('products/{product}/suppliers', [ProductSupplierController::class, 'index'])
        ->name('admin.products.suppliers.index')->middleware(['auth', 'role:admin']);
    Route::post('products/{product}/suppliers', [ProductSupplierController::class, 'store'])
        ->name('admin.products.suppliers.store')->middleware(['auth', 'role:admin']);
    Route::post('product-suppliers/{productSupplier}/prefer', [ProductSupplierController::class, 'markPreferred'])
        ->name('admin.product-suppliers.prefer')->middleware(['auth', 'role:admin']);
    Route::delete('product-suppliers/{productSupplier}', [ProductSupplierController::class, 'destroy'])
        ->name('admin.product-suppliers.destroy')->middleware(['auth', 'role:admin']);

    Route::get('stock-receivings', [StockReceivingController::class, 'index'])
        ->name('admin.stock-receivings.index')->middleware(['auth', 'role:admin']);
    Route::get('stock-receivings/create', [StockReceivingController::class, 'create'])
        ->name('admin.stock-receivings.create')->middleware(['auth', 'role:admin']);
    Route::post('stock-receivings', [StockReceivingController::class, 'store'])
        ->name('admin.stock-receivings.store')->middleware(['auth', 'role:admin']);
    Route::get('stock-receivings/batches/{stockReceivingBatch}', [StockReceivingController::class, 'showBatch'])
        ->name('admin.stock-receivings.batches.show')->middleware(['auth', 'role:admin']);
    Route::post('stock-receivings/batches/{stockReceivingBatch}/add-to-inventory', [StockReceivingController::class, 'addToInventory'])
        ->name('admin.stock-receivings.batches.add-to-inventory')->middleware(['auth', 'role:admin']);

    Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])
        ->name('admin.purchase-orders.index')->middleware(['auth', 'role:admin']);
    Route::get('purchase-orders/create', [PurchaseOrderController::class, 'create'])
        ->name('admin.purchase-orders.create')->middleware(['auth', 'role:admin']);
    Route::get('purchase-orders/create-from-reorder/{product}', [PurchaseOrderController::class, 'createFromReorder'])
        ->name('admin.purchase-orders.create-from-reorder')->middleware(['auth', 'role:admin']);
    Route::post('purchase-orders/create-from-reorder/{product}', [PurchaseOrderController::class, 'storeFromReorder'])
        ->name('admin.purchase-orders.store-from-reorder')->middleware(['auth', 'role:admin']);
    Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])
        ->name('admin.purchase-orders.store')->middleware(['auth', 'role:admin']);
    Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
        ->name('admin.purchase-orders.show')->middleware(['auth', 'role:admin']);
    Route::get('purchase-orders/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])
        ->name('admin.purchase-orders.edit')->middleware(['auth', 'role:admin']);
    Route::put('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])
        ->name('admin.purchase-orders.update')->middleware(['auth', 'role:admin']);
    Route::post('purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])
        ->name('admin.purchase-orders.submit')->middleware(['auth', 'role:admin']);
    Route::post('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])
        ->name('admin.purchase-orders.approve')->middleware(['auth', 'role:admin']);
    Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
        ->name('admin.purchase-orders.cancel')->middleware(['auth', 'role:admin']);
    Route::get('purchase-orders/{purchaseOrder}/export', [PurchaseOrderController::class, 'export'])
        ->name('admin.purchase-orders.export')->middleware(['auth', 'role:admin']);
    Route::get('purchase-orders/{purchaseOrder}/print', [PurchaseOrderController::class, 'printPreview'])
        ->name('admin.purchase-orders.print')->middleware(['auth', 'role:admin']);

    Route::get('stock-adjustments', [StockAdjustmentController::class, 'index'])
        ->name('admin.stock-adjustments.index')->middleware(['auth', 'role:admin']);
    Route::get('stock-adjustments/create', [StockAdjustmentController::class, 'create'])
        ->name('admin.stock-adjustments.create')->middleware(['auth', 'role:admin']);
    Route::post('stock-adjustments', [StockAdjustmentController::class, 'store'])
        ->name('admin.stock-adjustments.store')->middleware(['auth', 'role:admin']);

    Route::get('sales-returns', [SalesReturnController::class, 'index'])
        ->name('admin.sales-returns.index')->middleware(['auth', 'role:admin']);
    Route::get('sales-returns/{salesReturn}', [SalesReturnController::class, 'show'])
        ->name('admin.sales-returns.show')->middleware(['auth', 'role:admin']);
    Route::post('sales-returns/{salesReturn}/approve', [SalesReturnController::class, 'approve'])
        ->name('admin.sales-returns.approve')->middleware(['auth', 'role:admin']);
    Route::post('sales-returns/{salesReturn}/decline', [SalesReturnController::class, 'decline'])
        ->name('admin.sales-returns.decline')->middleware(['auth', 'role:admin']);

    Route::get('notifications', [NotificationController::class, 'index'])
        ->name('admin.notifications.index')->middleware(['auth', 'role:admin']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->name('admin.notifications.read')->middleware(['auth', 'role:admin']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->name('admin.notifications.read-all')->middleware(['auth', 'role:admin']);

    Route::get('reports', [ReportController::class, 'index'])
        ->name('admin.reports.index')->middleware(['auth', 'role:admin']);
    Route::get('reports/preview', [ReportController::class, 'preview'])
        ->name('admin.reports.preview')->middleware(['auth', 'role:admin']);
    Route::get('reports/export', [ReportController::class, 'export'])
        ->name('admin.reports.export')->middleware(['auth', 'role:admin']);
    Route::get('reports/details', [ReportController::class, 'details'])
        ->name('admin.reports.details')->middleware(['auth', 'role:admin']);
    Route::get('reports/print', [ReportController::class, 'printPreview'])
        ->name('admin.reports.print')->middleware(['auth', 'role:admin']);
});

// Admin login security review — deliberately OUTSIDE the auth-protected
// admin group above: the "Was this you?" email must be actionable from a
// phone that isn't logged in. Each route accepts either a valid signed URL
// (LoginSecurityController::authorizeAccess()) or an authenticated admin
// session, so the in-app prompt's fetch calls reuse the exact same routes.
// throttle limits brute-force guessing of a login-event ID.
Route::prefix('admin/security')->middleware('throttle:30,1')->group(function () {
    Route::get('login-events/{loginSecurityEvent}/review', [LoginSecurityController::class, 'review'])
        ->name('admin.security.review');
    Route::post('login-events/{loginSecurityEvent}/confirm', [LoginSecurityController::class, 'confirm'])
        ->name('admin.security.confirm');
    Route::post('login-events/{loginSecurityEvent}/deny', [LoginSecurityController::class, 'deny'])
        ->name('admin.security.deny');
});

// Cashier routes
Route::prefix('cashier')->group(function () {
    Route::post('logout', [CashierAuthController::class, 'logout'])->name('cashier.logout');

    // Forgot password functionality is disabled for Cashier - Only Administrator can reset passwords
    // Cashier users must contact the Administrator for password resets

    Route::get('pos', [CashierAuthController::class, 'pos'])->name('cashier.pos')->middleware(['auth', 'role:cashier']);
    Route::get('pos/promo-map', [CashierAuthController::class, 'promoMap'])->name('cashier.pos.promo-map')->middleware(['auth', 'role:cashier']);
    Route::post('pos/apply-promo', [CashierAuthController::class, 'applyPromo'])->name('cashier.pos.apply-promo')->middleware(['auth', 'role:cashier']);
    Route::post('pos/process-sale', [CashierAuthController::class, 'processSale'])->name('cashier.process-sale')->middleware(['auth', 'role:cashier']);
    Route::get('transactions', [CashierAuthController::class, 'transactions'])->name('cashier.transactions')->middleware(['auth', 'role:cashier']);

    // Return/Refund/Replacement routes
    Route::get('refunds', [CashierReturnController::class, 'index'])->name('cashier.refunds')->middleware(['auth', 'role:cashier']);
    Route::get('refunds/search', [CashierReturnController::class, 'searchTransaction'])->name('cashier.refunds.search')->middleware(['auth', 'role:cashier']);
    Route::post('refunds/create', [CashierReturnController::class, 'createRefund'])->name('cashier.refunds.create')->middleware(['auth', 'role:cashier']);
    Route::get('refunds/{transactionId}/transaction', [CashierReturnController::class, 'getTransactionDetails'])->name('cashier.refunds.transaction')->middleware(['auth', 'role:cashier']);
    Route::post('refunds/{salesReturn}/process-refund', [CashierReturnController::class, 'processRefund'])->name('cashier.refunds.process')->middleware(['auth', 'role:cashier']);
    Route::post('refunds/{salesReturn}/process-replacement', [CashierReturnController::class, 'processReplacement'])->name('cashier.refunds.process-replacement')->middleware(['auth', 'role:cashier']);
    Route::get('refunds/{salesReturn}/details', [CashierReturnController::class, 'getRefundDetails'])->name('cashier.refunds.details')->middleware(['auth', 'role:cashier']);
    Route::get('refunds/{salesReturn}/slip', [CashierReturnController::class, 'printReplacementSlip'])->name('cashier.refunds.slip')->middleware(['auth', 'role:cashier']);
    Route::get('refunds/{salesReturn}/receipt', [CashierReturnController::class, 'printRefundReceipt'])->name('cashier.refunds.receipt')->middleware(['auth', 'role:cashier']);
    Route::get('replacement-inventory/search', [CashierReturnController::class, 'searchReplacementInventory'])->name('cashier.replacement-inventory.search')->middleware(['auth', 'role:cashier']);
    Route::get('stats', [CashierReturnController::class, 'getCashierStats'])->name('cashier.stats')->middleware(['auth', 'role:cashier']);

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
