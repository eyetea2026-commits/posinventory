<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserHasRole
{
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
