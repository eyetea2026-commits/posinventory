<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);

        // One login page for the whole system (see AuthController) — every
        // unauthenticated guest lands there regardless of which area they
        // were trying to reach.
        $middleware->redirectGuestsTo(fn () => route('welcome'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A stale form (a tab left open past SESSION_LIFETIME, or the
        // session cookie otherwise no longer matching) used to hit
        // Laravel's bare "419 | Page Expired" page with no way back except
        // typing the URL again. Redirect to the login page with a plain
        // explanation instead — the session is gone either way, so sending
        // them back to sign in again is the correct next step regardless of
        // which page they were on.
        //
        // Note: Handler::prepareException() maps TokenMismatchException into
        // a generic Symfony HttpException(419, ...) before any custom
        // render() callback is checked, so a callback typed for
        // TokenMismatchException itself would never match — it has to catch
        // the mapped HttpException and check the status code instead.
        $exceptions->render(function (HttpException $e, $request) {
            if ($e->getStatusCode() === 419) {
                return redirect()->route('welcome')
                    ->with('status', 'Your session expired. Please sign in again.');
            }
        });
    })->create();
