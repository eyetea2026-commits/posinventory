<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes a "Slow request" warning to laravel.log when a page or a save takes
 * longer than logging.slow_request_seconds to answer. A request that runs
 * past the web server's limit shows the user a 504 that never reaches the
 * log; PHP keeps going and finishes, so it is recorded here with its time.
 */
class LogSlowRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        $response = $next($request);

        $seconds = microtime(true) - $startedAt;
        $limit = (float) config('logging.slow_request_seconds', 10);

        if ($seconds >= $limit) {
            Log::warning(sprintf('Slow request: %s /%s took %.1f seconds', $request->method(), ltrim($request->path(), '/'), $seconds), [
                'route' => $request->route()?->getName(),
                'status' => $response->getStatusCode(),
                'user_id' => $request->user()?->id,
                'username' => $request->user()?->name,
            ]);
        }

        return $response;
    }
}
