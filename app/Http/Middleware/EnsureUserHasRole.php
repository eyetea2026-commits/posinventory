<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * Someone signed in with the wrong role for a page (e.g. an Admin
     * opening /cashier/pos) is sent to their own home page with a short
     * message instead of a bare "403 Forbidden" page. Data requests (JSON
     * and /api) still get a plain 403, since there's no page to redirect to.
     */
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('welcome');
        }

        $allowed = match ($role) {
            'admin' => $user->isAdmin(),
            'cashier' => $user->isCashier(),
            default => true,
        };

        if ($allowed) {
            return $next($request);
        }

        $home = $this->homeFor($user);

        if ($home === null || $request->expectsJson() || $request->is('api/*')) {
            abort(403);
        }

        $pageFor = $role === 'admin' ? 'Admin' : 'Cashier';

        return redirect($home)->with('error', "That page is for {$pageFor} accounts only.");
    }

    /**
     * Where a signed-in user belongs, or null if their account has no role.
     */
    private function homeFor($user): ?string
    {
        if ($user->isAdmin()) {
            return route('admin.dashboard');
        }

        if ($user->isCashier()) {
            return route('cashier.pos');
        }

        return null;
    }
}
