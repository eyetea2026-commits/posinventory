<?php

namespace App\Providers;

use App\Models\Inventory;
use App\Observers\InventoryObserver;
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
        Inventory::observe(InventoryObserver::class);

        View::composer('admin.layout', function ($view) {
            $user = auth()->user();

            $view->with([
                'headerUnreadNotifications' => $user
                    ? $user->unreadNotifications()->latest()->take(8)->get()
                    : collect(),
                'headerUnreadCount' => $user ? $user->unreadNotifications()->count() : 0,
            ]);
        });
    }
}
