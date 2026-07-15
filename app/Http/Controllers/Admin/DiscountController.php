<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Discount;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (! auth()->user() || ! auth()->user()->isAdmin()) {
                abort(403);
            }

            return $next($request);
        });
    }

    // Display discounts list
    public function index(Request $request)
    {
        $search = $request->get('search');

        $discounts = Discount::when($search, function($query) use ($search) {
            return $query->where('DiscountRate', 'like', "%{$search}%");
        })->orderBy('DiscountID', 'desc')->get();

        return view('admin.discounts.index', compact('discounts', 'search'));
    }

    // Show create form
    public function create()
    {
        return view('admin.discounts.create');
    }

    // Store new discount
    public function store(Request $request)
    {
        $request->validate([
            'DiscountRate' => 'required|numeric|min:0|max:100|unique:Discount,DiscountRate'
        ], [
            'DiscountRate.required' => 'Discount rate is required.',
            'DiscountRate.numeric' => 'Discount rate must be a number.',
            'DiscountRate.min' => 'Discount rate cannot be negative.',
            'DiscountRate.max' => 'Discount rate cannot exceed 100%.',
            'DiscountRate.unique' => 'A discount policy with this rate already exists.',
        ]);

        Discount::create([
            'DiscountRate' => $request->DiscountRate
        ]);

        ActivityLog::record('discount.created', "Created discount policy \"{$request->DiscountRate}%\"");

        return redirect()->route('admin.discounts.index')->with('success', 'Discount policy created successfully.');
    }

    // Show edit form
    public function edit(Discount $discount)
    {
        return view('admin.discounts.edit', compact('discount'));
    }

    // Update discount
    public function update(Request $request, Discount $discount)
    {
        $request->validate([
            'DiscountRate' => 'required|numeric|min:0|max:100|unique:Discount,DiscountRate,' . $discount->DiscountID . ',DiscountID'
        ], [
            'DiscountRate.required' => 'Discount rate is required.',
            'DiscountRate.numeric' => 'Discount rate must be a number.',
            'DiscountRate.min' => 'Discount rate cannot be negative.',
            'DiscountRate.max' => 'Discount rate cannot exceed 100%.',
            'DiscountRate.unique' => 'A discount policy with this rate already exists.',
        ]);

        $discount->update([
            'DiscountRate' => $request->DiscountRate
        ]);

        return redirect()->route('admin.discounts.index')->with('success', 'Discount policy updated successfully.');
    }

    // Delete discount
    public function destroy(Discount $discount)
    {
        // The 0% row is POS's permanent "No Discount" baseline — every sale's
        // Billing.DiscountID must reference a real Discount row even when no
        // discount applies, so this one can never go away.
        if ((float) $discount->DiscountRate === 0.0) {
            return redirect()->route('admin.discounts.index')->with('error', 'The 0% "No Discount" option cannot be deleted — POS depends on it.');
        }

        // Check if discount is used in any billing
        if ($discount->billings()->count() > 0) {
            return redirect()->route('admin.discounts.index')->with('error', 'Cannot delete discount that is associated with transactions.');
        }

        $discount->delete();

        return redirect()->route('admin.discounts.index')->with('success', 'Discount policy deleted successfully.');
    }
}