<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

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
