<?php

namespace App\Http\Controllers;

class DashboardController extends Controller
{
    /**
     * Entry point for Breeze's generic post-login/post-verification redirects
     * (route name "dashboard"). Just routes to the role-specific dashboard —
     * Admin\DashboardController owns the actual admin.dashboard view/data.
     */
    public function index()
    {
        $user = auth()->user();

        if ($user && $user->isCashier()) {
            return redirect()->route('cashier.pos');
        }

        if ($user && $user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        abort(403);
    }
}
