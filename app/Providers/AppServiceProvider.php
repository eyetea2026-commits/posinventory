<?php

namespace App\Providers;

use App\Http\Controllers\Admin\StockReceivingController;
use App\Models\Inventory;
use App\Notifications\Channels\MailAfterResponseChannel;
use App\Observers\InventoryObserver;
use App\Services\LiveDataVersions;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Inventory::observe(InventoryObserver::class);

        Notification::extend('mail', fn ($app) => $app->make(MailAfterResponseChannel::class));

        DB::listen(fn (QueryExecuted $query) => LiveDataVersions::recordWrite($query));

        Password::defaults(function () {
            $rule = Password::min(8)->mixedCase()->numbers()->symbols();

            return $this->app->environment('production') ? $rule->uncompromised() : $rule;
        });

        if ($this->app->environment('production') && config('app.debug')) {
            Log::critical('APP_DEBUG is enabled in a production environment — verbose error pages are exposing internal details to end users. Set APP_DEBUG=false immediately.');
        }

        View::composer('admin.layout', function ($view) {
            $user = auth()->user();

            if ($user && Cache::add('delivery-due-check', true, 600)) {
                try {
                    StockReceivingController::notifyDueDeliveries();
                } catch (Throwable $e) {
                    Log::error('Delivery due check failed', ['exception' => $e->getMessage()]);
                }
            }

            $view->with([
                'headerUnreadNotifications' => $user
                    ? $user->unreadNotifications()->latest()->take(8)->get()
                    : collect(),
                'headerUnreadCount' => $user ? $user->unreadNotifications()->count() : 0,
            ]);
        });

        View::composer('cashier.partials.notification-bell', function ($view) {
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
