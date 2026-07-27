<?php

namespace Tests\Feature\Security;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SessionExpiryTest extends TestCase
{
    // A stale form (expired session/CSRF token) used to hit Laravel's bare
    // "419 | Page Expired" page with no way back. bootstrap/app.php now
    // redirects to login with a friendly message instead.
    //
    // Laravel's Handler::prepareException() maps TokenMismatchException into
    // a generic Symfony HttpException(419, ...) BEFORE any custom render()
    // callback runs — a callback typed for TokenMismatchException itself
    // would silently never match. This test exercises the actual mapped
    // type (HttpException with status 419) the way it really arrives, so a
    // future regression back to the wrong type-hint would be caught here
    // instead of only surfacing as a raw 419 page in the browser.
    public function test_a_419_http_exception_redirects_to_login_with_a_friendly_message(): void
    {
        $handler = app(ExceptionHandler::class);
        $request = Request::create('/some-stale-form', 'POST');
        $exception = new HttpException(419, 'CSRF token mismatch.');

        $response = $handler->render($request, $exception);

        $this->assertTrue($response->isRedirect(route('welcome')));
        $this->assertEquals('Your session expired. Please sign in again.', session('status'));
    }

    // Other HTTP status codes must fall through to normal rendering, not
    // get swallowed by the 419 check.
    public function test_a_404_http_exception_is_not_redirected_to_login(): void
    {
        $handler = app(ExceptionHandler::class);
        $request = Request::create('/does-not-exist', 'GET');
        $exception = new HttpException(404, 'Not Found.');

        $response = $handler->render($request, $exception);

        $this->assertSame(404, $response->getStatusCode());
    }
}
