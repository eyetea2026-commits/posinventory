<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\LogSlowRequests;
use App\Http\Middleware\PreventBackHistory;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('welcome'));

        $middleware->web(append: [
            LogSlowRequests::class,
            PreventBackHistory::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'admin/security/login-events/*/confirm',
            'admin/security/login-events/*/deny',
        ]);

        $middleware->trimStrings(except: [
            fn (Request $request) => $request->is('print-bridge'),
        ]);
        $middleware->convertEmptyStringsToNull(except: [
            fn (Request $request) => $request->is('print-bridge'),
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpException $e, $request) {
            if ($e->getStatusCode() === 419) {
                return redirect()->route('welcome')
                    ->with('status', 'Your session expired. Please sign in again.');
            }
        });
    })->create();
