<?php

namespace App\Providers;

use App\Models\Inventory;
use App\Models\PurchaseOrder;
use App\Models\SalesReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('admin.layout', function ($view) {
            $view->with([
                'headerPendingPurchaseOrders' => PurchaseOrder::where('Status', 'pending')->count(),
                'headerPendingReturns' => SalesReturn::where('Status', 'pending')->count(),
                'headerOutOfStockCount' => Inventory::where('Quantity', '<=', 0)->count(),
            ]);
        });
    }
}
