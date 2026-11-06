<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives JavaScript errors from the browser (partials/error-reporter) and
 * writes them to laravel.log as "Browser error", so a page that breaks in
 * the browser leaves a trace like a server error does.
 */
class ClientErrorController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'page' => ['nullable', 'string', 'max:500'],
            'source' => ['nullable', 'string', 'max:500'],
            'line' => ['nullable', 'integer', 'min:0'],
            'column' => ['nullable', 'integer', 'min:0'],
            'stack' => ['nullable', 'string', 'max:4000'],
        ]);

        Log::error('Browser error: '.$data['message'], [
            'page' => $data['page'] ?? null,
            'source' => isset($data['source']) ? $data['source'].':'.($data['line'] ?? '?').':'.($data['column'] ?? '?') : null,
            'stack' => $data['stack'] ?? null,
            'user_id' => $request->user()?->id,
            'username' => $request->user()?->name,
            'browser' => substr((string) $request->userAgent(), 0, 300),
        ]);

        return response()->noContent();
    }
}
