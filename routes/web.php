<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\CashierAuthController;

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

// AJAX route to check user role for password reset eligibility
// Rate limited to 10 requests per minute to prevent abuse
Route::post('/check-user-role', function (\Illuminate\Http\Request $request) {
    $username = $request->input('username');

    if (!$username) {
        return response()->json(['error' => 'Username is required'], 400);
    }

    $user = \App\Models\User::where('name', $username)->first();

    if (!$user) {
        return response()->json([
            'exists' => false,
            'message' => 'No account found with this username.'
        ]);
    }

    $isAdmin = $user->isAdmin();
    $isCashier = $user->isCashier();

    return response()->json([
        'exists' => true,
        'isAdmin' => $isAdmin,
        'isCashier' => $isCashier,
        'message' => $isAdmin
            ? 'Administrator account found. Password reset available.'
            : 'Password reset is not available for Cashier accounts. Please contact your Administrator.'
    ]);
})->name('check.user.role')->middleware('throttle:10,1');

// API route for barcode scanning (POS)
Route::get('/api/products/barcode/{barcode}', function ($barcode) {
    $product = \App\Models\Product::with('inventory')
        ->where('Barcode', $barcode)
        ->first();

    if (!$product) {
        return response()->json(['product' => null], 404);
    }

    return response()->json(['product' => $product]);
});

// API route for getting customers (POS)
Route::get('/api/customers/search', function (\Illuminate\Http\Request $request) {
    $search = $request->query('q', '');

    $customers = \App\Models\Customer::where('CustomerName', 'like', "%{$search}%")
        ->orWhere('Email', 'like', "%{$search}%")
        ->limit(10)
        ->get();

    return response()->json(['customers' => $customers]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Admin routes
Route::prefix('admin')->group(function () {
    Route::get('login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('login', [AdminAuthController::class, 'login'])->name('admin.login.post')->middleware('throttle:6,1');
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
        ->name('admin.products.index');
    Route::get('products/create', [App\Http\Controllers\Admin\ProductController::class, 'create'])
        ->name('admin.products.create');
    Route::post('products/check-name', [App\Http\Controllers\Admin\ProductController::class, 'checkName'])
        ->name('admin.products.check-name');
    Route::post('products', [App\Http\Controllers\Admin\ProductController::class, 'store'])
        ->name('admin.products.store');
    Route::get('products/{product}', [App\Http\Controllers\Admin\ProductController::class, 'show'])
        ->name('admin.products.show');
    Route::get('products/{product}/edit', [App\Http\Controllers\Admin\ProductController::class, 'edit'])
        ->name('admin.products.edit');
    Route::put('products/{product}', [App\Http\Controllers\Admin\ProductController::class, 'update'])
        ->name('admin.products.update');
    Route::delete('products/{product}', [App\Http\Controllers\Admin\ProductController::class, 'destroy'])
        ->name('admin.products.destroy');

    // Inventory (view-only)
    Route::get('inventory', [App\Http\Controllers\Admin\InventoryController::class, 'index'])
        ->name('admin.inventory.index');
    Route::get('inventory/{product}', [App\Http\Controllers\Admin\InventoryController::class, 'show'])
        ->name('admin.inventory.show');

    // Categories
    Route::get('categories', [App\Http\Controllers\Admin\CategoryController::class, 'index'])
        ->name('admin.categories.index');
    Route::get('categories/create', [App\Http\Controllers\Admin\CategoryController::class, 'create'])
        ->name('admin.categories.create');
    Route::post('categories', [App\Http\Controllers\Admin\CategoryController::class, 'store'])
        ->name('admin.categories.store');
    Route::get('categories/{category}/edit', [App\Http\Controllers\Admin\CategoryController::class, 'edit'])
        ->name('admin.categories.edit');
    Route::put('categories/{category}', [App\Http\Controllers\Admin\CategoryController::class, 'update'])
        ->name('admin.categories.update');
    Route::delete('categories/{category}', [App\Http\Controllers\Admin\CategoryController::class, 'destroy'])
        ->name('admin.categories.destroy');

    // Discounts
    Route::get('discounts', [App\Http\Controllers\Admin\DiscountController::class, 'index'])
        ->name('admin.discounts.index');
    Route::get('discounts/create', [App\Http\Controllers\Admin\DiscountController::class, 'create'])
        ->name('admin.discounts.create');
    Route::post('discounts', [App\Http\Controllers\Admin\DiscountController::class, 'store'])
        ->name('admin.discounts.store');
    Route::get('discounts/{discount}/edit', [App\Http\Controllers\Admin\DiscountController::class, 'edit'])
        ->name('admin.discounts.edit');
    Route::put('discounts/{discount}', [App\Http\Controllers\Admin\DiscountController::class, 'update'])
        ->name('admin.discounts.update');
    Route::delete('discounts/{discount}', [App\Http\Controllers\Admin\DiscountController::class, 'destroy'])
        ->name('admin.discounts.destroy');

    // Damages
    Route::get('damages', [App\Http\Controllers\Admin\DamageController::class, 'index'])
        ->name('admin.damages.index')->middleware(['auth', 'role:admin']);
    Route::get('damages/create', [App\Http\Controllers\Admin\DamageController::class, 'create'])
        ->name('admin.damages.create')->middleware(['auth', 'role:admin']);
    Route::post('damages', [App\Http\Controllers\Admin\DamageController::class, 'store'])
        ->name('admin.damages.store')->middleware(['auth', 'role:admin']);
    Route::get('damages/export', [App\Http\Controllers\Admin\DamageController::class, 'export'])
        ->name('admin.damages.export')->middleware(['auth', 'role:admin']);
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

    Route::get('suppliers', [App\Http\Controllers\Admin\SupplierController::class, 'index'])
        ->name('admin.suppliers.index');
    Route::get('suppliers/create', [App\Http\Controllers\Admin\SupplierController::class, 'create'])
        ->name('admin.suppliers.create');
    Route::post('suppliers', [App\Http\Controllers\Admin\SupplierController::class, 'store'])
        ->name('admin.suppliers.store');
    Route::get('suppliers/{supplier}/edit', [App\Http\Controllers\Admin\SupplierController::class, 'edit'])
        ->name('admin.suppliers.edit');
    Route::put('suppliers/{supplier}', [App\Http\Controllers\Admin\SupplierController::class, 'update'])
        ->name('admin.suppliers.update');

    Route::get('stock-receivings', [App\Http\Controllers\Admin\StockReceivingController::class, 'index'])
        ->name('admin.stock-receivings.index');
    Route::get('stock-receivings/create', [App\Http\Controllers\Admin\StockReceivingController::class, 'create'])
        ->name('admin.stock-receivings.create');
    Route::post('stock-receivings', [App\Http\Controllers\Admin\StockReceivingController::class, 'store'])
        ->name('admin.stock-receivings.store');

    Route::get('purchase-orders', [App\Http\Controllers\Admin\PurchaseOrderController::class, 'index'])
        ->name('admin.purchase-orders.index');
    Route::get('purchase-orders/create', [App\Http\Controllers\Admin\PurchaseOrderController::class, 'create'])
        ->name('admin.purchase-orders.create');
    Route::post('purchase-orders', [App\Http\Controllers\Admin\PurchaseOrderController::class, 'store'])
        ->name('admin.purchase-orders.store');
    Route::get('purchase-orders/{purchaseOrder}', [App\Http\Controllers\Admin\PurchaseOrderController::class, 'show'])
        ->name('admin.purchase-orders.show');

    Route::get('stock-adjustments', [App\Http\Controllers\Admin\StockAdjustmentController::class, 'index'])
        ->name('admin.stock-adjustments.index');
    Route::get('stock-adjustments/create', [App\Http\Controllers\Admin\StockAdjustmentController::class, 'create'])
        ->name('admin.stock-adjustments.create');
    Route::post('stock-adjustments', [App\Http\Controllers\Admin\StockAdjustmentController::class, 'store'])
        ->name('admin.stock-adjustments.store');

    Route::get('sales-returns', [App\Http\Controllers\Admin\SalesReturnController::class, 'index'])
        ->name('admin.sales-returns.index')->middleware(['auth', 'role:admin']);
    Route::get('sales-returns/create', [App\Http\Controllers\Admin\SalesReturnController::class, 'create'])
        ->name('admin.sales-returns.create')->middleware(['auth', 'role:admin']);
    Route::post('sales-returns', [App\Http\Controllers\Admin\SalesReturnController::class, 'store'])
        ->name('admin.sales-returns.store')->middleware(['auth', 'role:admin']);
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
        ->name('admin.reports.index');
    Route::get('reports/export', [App\Http\Controllers\Admin\ReportController::class, 'export'])
        ->name('admin.reports.export');
});

// Cashier routes
Route::prefix('cashier')->group(function () {
    Route::get('login', [CashierAuthController::class, 'showLogin'])->name('cashier.login');
    Route::post('login', [CashierAuthController::class, 'login'])->name('cashier.login.post')->middleware('throttle:6,1');
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
    Route::get('replacement-inventory/search', [App\Http\Controllers\Cashier\CashierReturnController::class, 'searchReplacementInventory'])->name('cashier.replacement-inventory.search')->middleware(['auth', 'role:cashier']);
    Route::get('stats', [App\Http\Controllers\Cashier\CashierReturnController::class, 'getCashierStats'])->name('cashier.stats')->middleware(['auth', 'role:cashier']);

    // Receipt route (REQ102)
    Route::get('receipt/{receiptNumber}', [CashierAuthController::class, 'viewReceipt'])->name('cashier.receipt')->middleware(['auth', 'role:cashier']);
});