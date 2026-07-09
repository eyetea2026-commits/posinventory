<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($role === 'admin' && ! $user->isAdmin()) {
            abort(403);
        }

        if ($role === 'cashier' && ! $user->isCashier()) {
            abort(403);
        }

        return $next($request);
    }
}
