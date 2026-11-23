<?php

namespace App\Http\Controllers;

class DashboardController extends Controller
{
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
