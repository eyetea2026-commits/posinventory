<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Discount;
use App\Models\Product;
use App\Models\User;
use App\Notifications\DiscountUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Throwable;

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

    // Display promo codes list
    public function index(Request $request)
    {
        $search = $request->get('search');

        $discounts = Discount::with('product')
            ->when($search, function ($query) use ($search) {
                $query->where('PromoCode', 'like', "%{$search}%")
                    ->orWhere('Name', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($q) use ($search) {
                        $q->where('ProductName', 'like', "%{$search}%");
                    });
            })
            ->orderByDesc('DiscountID')
            ->paginate(15)
            ->withQueryString();

        // Live search — matches the Inventory/Damage module pattern. Keyed
        // off an explicit ?ajax=1 flag rather than the X-Requested-With
        // header alone: the Add/Edit Promo modal's own AJAX submit follows
        // its redirect back to this same route carrying that same header,
        // and a header-only check would hijack that redirect-follow into
        // returning this JSON instead of the full HTML page the modal's
        // shared submit helper expects (see the identical Category/Damage
        // module fix).
        if ($request->boolean('ajax')) {
            return response()->json([
                'rows' => view('admin.discounts.partials.rows', ['discounts' => $discounts])->render(),
                'pagination' => view('admin.discounts.partials.pagination', ['discounts' => $discounts])->render(),
            ]);
        }

        return view('admin.discounts.index', [
            'discounts' => $discounts,
            'search' => $search,
            'products' => Product::orderBy('ProductName')->get(['ProductID', 'ProductName', 'Price']),
        ]);
    }

    // Show create form
    public function create()
    {
        return view('admin.discounts.create', [
            'products' => Product::orderBy('ProductName')->get(['ProductID', 'ProductName', 'Price']),
        ]);
    }

    // Live promo-code uniqueness check (mirrors ProductController::checkName)
    public function checkPromoCode(Request $request)
    {
        $code = strtoupper(trim((string) $request->input('PromoCode', '')));
        $excludeId = $request->input('exclude_id');

        $taken = $code !== '' && Discount::where('PromoCode', $code)
            ->when($excludeId, fn ($q) => $q->where('DiscountID', '!=', $excludeId))
            ->exists();

        return response()->json(['promo_code' => $taken, 'promo_code_value' => $request->input('PromoCode', '')]);
    }

    // Store new promo code
    public function store(Request $request)
    {
        $data = $this->validatePromo($request);

        $discount = Discount::create([
            'ProductID' => $data['ProductID'],
            'DiscountRate' => $data['DiscountRate'],
            'Name' => $data['Name'],
            'PromoCode' => $data['PromoCode'],
            'Description' => $data['Description'] ?? null,
            'StartDate' => $data['StartDate'],
            'EndDate' => $data['EndDate'],
            'Status' => $data['Status'],
            'CreatedBy' => auth()->id(),
        ]);

        ActivityLog::record('discount.created', "Created promo \"{$discount->PromoCode}\" for product #{$discount->ProductID}");

        try {
            Notification::send(User::admins(), new DiscountUpdated($discount, 'Created'));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch DiscountUpdated notification', [
                'discount_id' => $discount->DiscountID,
                'exception' => $e->getMessage(),
            ]);
        }

        return redirect()->route('admin.discounts.index')->with('success', 'Promo code created successfully.');
    }

    // Show edit form
    public function edit(Request $request, Discount $discount)
    {
        $products = Product::orderBy('ProductName')->get(['ProductID', 'ProductName', 'Price']);

        // Edit Promo modal: return just the rendered form fields instead of
        // a full page, so the modal can inject it without navigating.
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'html' => view('admin.discounts.partials.discount-form-fields', [
                    'discount' => $discount,
                    'products' => $products,
                ])->render(),
            ]);
        }

        return view('admin.discounts.edit', ['discount' => $discount, 'products' => $products]);
    }

    // Update promo code
    public function update(Request $request, Discount $discount)
    {
        $data = $this->validatePromo($request, $discount);

        $discount->update([
            'ProductID' => $data['ProductID'],
            'DiscountRate' => $data['DiscountRate'],
            'Name' => $data['Name'],
            'PromoCode' => $data['PromoCode'],
            'Description' => $data['Description'] ?? null,
            'StartDate' => $data['StartDate'],
            'EndDate' => $data['EndDate'],
            'Status' => $data['Status'],
        ]);

        ActivityLog::record('discount.updated', "Updated promo \"{$discount->PromoCode}\"");

        try {
            Notification::send(User::admins(), new DiscountUpdated($discount, 'Updated'));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch DiscountUpdated notification', [
                'discount_id' => $discount->DiscountID,
                'exception' => $e->getMessage(),
            ]);
        }

        return redirect()->route('admin.discounts.index')->with('success', 'Promo code updated successfully.');
    }

    // Promo Details page
    public function show(Discount $discount)
    {
        $discount->load(['product.category', 'createdByUser']);

        return view('admin.discounts.show', ['discount' => $discount]);
    }

    public function activate(Discount $discount)
    {
        if ($discount->effective_status === Discount::STATUS_EXPIRED) {
            return back()->with('error', 'An expired promo cannot be reactivated — extend its End Date first.');
        }

        if ($this->hasOverlappingActivePromo($discount->ProductID, $discount->StartDate?->toDateString(), $discount->EndDate?->toDateString(), $discount->DiscountID)) {
            return back()->with('error', 'This product already has another active promo covering an overlapping date range.');
        }

        $discount->update(['Status' => Discount::STATUS_ACTIVE]);
        ActivityLog::record('discount.activated', "Activated promo \"{$discount->PromoCode}\"");

        return back()->with('success', 'Promo activated.');
    }

    public function deactivate(Discount $discount)
    {
        $discount->update(['Status' => Discount::STATUS_INACTIVE]);
        ActivityLog::record('discount.deactivated', "Deactivated promo \"{$discount->PromoCode}\"");

        return back()->with('success', 'Promo deactivated.');
    }

    // Delete promo code
    public function destroy(Discount $discount)
    {
        // Check if discount is used in any billing
        if ($discount->billings()->count() > 0) {
            return redirect()->route('admin.discounts.index')->with('error', 'Cannot delete a promo that is associated with past transactions.');
        }

        $discount->delete();

        ActivityLog::record('discount.deleted', "Deleted promo \"{$discount->PromoCode}\"");

        return redirect()->route('admin.discounts.index')->with('success', 'Promo code deleted successfully.');
    }

    private function validatePromo(Request $request, ?Discount $discount = null): array
    {
        $discountId = $discount?->DiscountID;

        // Normalized to uppercase before validation runs, so the uniqueness
        // check itself is inherently case-insensitive regardless of the
        // database driver's collation (MySQL's default is already
        // case-insensitive, but the test suite runs on SQLite, which isn't).
        $request->merge(['PromoCode' => strtoupper(trim((string) $request->input('PromoCode', '')))]);

        $data = $request->validate([
            'ProductID' => ['required', 'integer', 'exists:Product,ProductID'],
            'DiscountRate' => ['required', 'numeric', 'min:1', 'max:100'],
            'Name' => ['required', 'string', 'max:100'],
            'PromoCode' => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('Discount', 'PromoCode')->ignore($discountId, 'DiscountID')->whereNull('deleted_at'),
            ],
            'Description' => ['nullable', 'string', 'max:1000'],
            'StartDate' => ['required', 'date'],
            'EndDate' => ['required', 'date', 'after_or_equal:StartDate'],
            'Status' => ['required', Rule::in([Discount::STATUS_ACTIVE, Discount::STATUS_INACTIVE])],
        ], [
            'ProductID.required' => 'Please select a product.',
            'DiscountRate.min' => 'Discount percentage must be at least 1%.',
            'DiscountRate.max' => 'Discount percentage cannot exceed 100%.',
            'PromoCode.regex' => 'Promo code may only contain letters, numbers, and hyphens.',
            'PromoCode.unique' => 'This promo code is already in use.',
            'EndDate.after_or_equal' => 'End Date cannot be earlier than Start Date.',
        ]);

        // Only one ACTIVE promo may cover a given product at any point in
        // time — a date-range overlap check rather than a flat "one row
        // per product" rule, so a future promo can still be scheduled while
        // a current one is running as long as their windows don't collide.
        if ($data['Status'] === Discount::STATUS_ACTIVE
            && $this->hasOverlappingActivePromo($data['ProductID'], $data['StartDate'], $data['EndDate'], $discountId)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ProductID' => 'This product already has another active promo covering an overlapping date range.',
            ]);
        }

        return $data;
    }

    private function hasOverlappingActivePromo(int $productId, ?string $startDate, ?string $endDate, ?int $excludeId = null): bool
    {
        return Discount::where('ProductID', $productId)
            ->where('Status', Discount::STATUS_ACTIVE)
            ->when($excludeId, fn ($q) => $q->where('DiscountID', '!=', $excludeId))
            ->where(function ($q) use ($startDate) {
                $q->whereNull('EndDate')->orWhereDate('EndDate', '>=', $startDate ?? now()->toDateString());
            })
            ->where(function ($q) use ($endDate) {
                $q->whereNull('StartDate')->orWhereDate('StartDate', '<=', $endDate ?? '9999-12-31');
            })
            ->exists();
    }
}
