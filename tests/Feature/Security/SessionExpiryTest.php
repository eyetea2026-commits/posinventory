<?php

namespace Tests\Feature\Security;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SessionExpiryTest extends TestCase
{
    public function test_a_419_http_exception_redirects_to_login_with_a_friendly_message(): void
    {
        $handler = app(ExceptionHandler::class);
        $request = Request::create('/some-stale-form', 'POST');
        $exception = new HttpException(419, 'CSRF token mismatch.');

        $response = $handler->render($request, $exception);

        $this->assertTrue($response->isRedirect(route('welcome')));
        $this->assertEquals('Your session expired. Please sign in again.', session('status'));
    }

    public function test_a_404_http_exception_is_not_redirected_to_login(): void
    {
        $handler = app(ExceptionHandler::class);
        $request = Request::create('/does-not-exist', 'GET');
        $exception = new HttpException(404, 'Not Found.');

        $response = $handler->render($request, $exception);

        $this->assertSame(404, $response->getStatusCode());
    }
}
