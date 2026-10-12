<?php

namespace App\Providers;

use App\Http\Controllers\Admin\StockReceivingController;
use App\Models\Inventory;
use App\Observers\InventoryObserver;
use App\Services\LiveDataVersions;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Throwable;

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

        // Real-time pages: every write the app makes bumps that table's
        // version, so open pages showing that data refresh by themselves
        // (see LiveDataVersions / LiveUpdatesController).
        DB::listen(fn (QueryExecuted $query) => LiveDataVersions::recordWrite($query));

        // Project-wide password policy — every Password::defaults() call in
        // the app (User creation/edit, admin password reset) now requires a
        // minimum length plus mixed case, numbers, and a special character,
        // not just Laravel's bare 8-character default. The "have I been
        // pwned" breach check only runs outside local/testing, since it
        // requires a live network call to an external API that isn't
        // available (and shouldn't be relied on) in a test run or offline
        // dev environment.
        Password::defaults(function () {
            $rule = Password::min(8)->mixedCase()->numbers()->symbols();

            return $this->app->environment('production') ? $rule->uncompromised() : $rule;
        });

        // Defense-in-depth, not a substitute for correct server config: if
        // debug mode is ever left on in a production environment, log it
        // loudly so it doesn't go unnoticed — verbose error pages leaking
        // stack traces/SQL/env values to real users is a serious exposure.
        if ($this->app->environment('production') && config('app.debug')) {
            Log::critical('APP_DEBUG is enabled in a production environment — verbose error pages are exposing internal details to end users. Set APP_DEBUG=false immediately.');
        }

        View::composer('admin.layout', function ($view) {
            $user = auth()->user();

            // No cron on this hosting, so "delivery due" notices are checked
            // on admin page loads instead — at most once every 10 minutes
            // (Cache::add only succeeds when the key is absent), and never
            // allowed to break the page if something goes wrong.
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

        // Bound to the partial itself, not 'cashier.layout': this partial gets
        // @include'd from inside child views (pos/transactions/refunds) whose
        // content renders *before* Blade ever instantiates the parent layout
        // view under @extends — a composer scoped to 'cashier.layout' would
        // fire too late for those instances and leave them stuck on the
        // ?? 0 / ?? collect() fallback.
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
