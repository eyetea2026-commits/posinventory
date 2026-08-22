<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Billing;
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

    // Display promo codes list (Tab 1) + the product-apply panel (Tab 2)
    public function index(Request $request)
    {
        $search = $request->get('search');

        $discounts = Discount::with('products')
            ->when($search, function ($query) use ($search) {
                $query->where('PromoCode', 'like', "%{$search}%")
                    ->orWhere('Name', 'like', "%{$search}%")
                    ->orWhereHas('products', function ($q) use ($search) {
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

        // Tab 2's product picker — a separate, namespaced search/page so it
        // never collides with Tab 1's promo list query params.
        $productSearch = $request->get('product_search');
        $products = Product::with('category')
            ->when($productSearch, function ($query) use ($productSearch) {
                $query->where(function ($inner) use ($productSearch) {
                    $inner->where('ProductName', 'like', "%{$productSearch}%")
                        ->orWhere('SKU', 'like', "%{$productSearch}%");
                });
            })
            ->orderBy('ProductName')
            ->paginate(15, ['*'], 'product_page')
            ->withQueryString();

        if ($request->boolean('ajax_products')) {
            return response()->json([
                'rows' => view('admin.discounts.partials.apply-product-rows', ['products' => $products])->render(),
                'pagination' => view('admin.discounts.partials.pagination', ['discounts' => $products])->render(),
            ]);
        }

        // Tab 2's "Applied Discount/Promo List" — one row per product-promo
        // assignment (a DiscountProduct pivot row), not per promo.
        $appliedAssignments = \Illuminate\Support\Facades\DB::table('DiscountProduct')
            ->join('Discount', 'Discount.DiscountID', '=', 'DiscountProduct.DiscountID')
            ->join('Product', 'Product.ProductID', '=', 'DiscountProduct.ProductID')
            ->whereNull('Discount.deleted_at')
            ->select(
                'DiscountProduct.id as pivot_id',
                'Discount.DiscountID', 'Discount.Name', 'Discount.PromoCode', 'Discount.DiscountType',
                'Discount.DiscountRate', 'Discount.StartDate', 'Discount.EndDate',
                'Product.ProductID', 'Product.ProductName', 'Product.SKU', 'Product.Price'
            )
            ->orderByDesc('DiscountProduct.id')
            ->paginate(15, ['*'], 'applied_page');

        if ($request->boolean('ajax_applied')) {
            return response()->json([
                'rows' => view('admin.discounts.partials.applied-rows', ['appliedAssignments' => $appliedAssignments])->render(),
                'pagination' => view('admin.discounts.partials.pagination', ['discounts' => $appliedAssignments])->render(),
            ]);
        }

        return view('admin.discounts.index', [
            'discounts' => $discounts,
            'search' => $search,
            'products' => $products,
            'productSearch' => $productSearch,
            'appliedAssignments' => $appliedAssignments,
            'allDiscounts' => Discount::whereNotNull('PromoCode')->orderBy('Name')->get(['DiscountID', 'Name', 'PromoCode']),
        ]);
    }

    // Show create form
    public function create()
    {
        return view('admin.discounts.create');
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

    // Store new promo code — Tab 1 only defines the promo itself, no
    // product selection; that's decided separately via assignProducts().
    public function store(Request $request)
    {
        $data = $this->validatePromo($request);

        $discount = Discount::create([
            'DiscountRate' => $data['DiscountRate'],
            'DiscountType' => $data['DiscountType'],
            'Name' => $data['Name'],
            'PromoCode' => $data['PromoCode'],
            'Description' => $data['Description'] ?? null,
            'StartDate' => $data['StartDate'],
            'EndDate' => $data['EndDate'],
            'CreatedBy' => auth()->id(),
        ]);

        ActivityLog::record('discount.created', "Created promo \"{$discount->PromoCode}\"");

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
        // Edit Promo modal: return just the rendered form fields instead of
        // a full page, so the modal can inject it without navigating.
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'html' => view('admin.discounts.partials.discount-form-fields', [
                    'discount' => $discount,
                ])->render(),
            ]);
        }

        return view('admin.discounts.edit', ['discount' => $discount]);
    }

    // Update promo code — same fields as store(), no product selection here.
    public function update(Request $request, Discount $discount)
    {
        $data = $this->validatePromo($request, $discount);

        $discount->update([
            'DiscountRate' => $data['DiscountRate'],
            'DiscountType' => $data['DiscountType'],
            'Name' => $data['Name'],
            'PromoCode' => $data['PromoCode'],
            'Description' => $data['Description'] ?? null,
            'StartDate' => $data['StartDate'],
            'EndDate' => $data['EndDate'],
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
        $discount->load(['products.category', 'createdByUser']);

        return view('admin.discounts.show', ['discount' => $discount]);
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

    // Assign an existing promo to one or more products (Tab 2). Each
    // product is checked independently — one already covered by another
    // promo's overlapping date window is skipped (reported back, not a
    // hard failure for the whole batch) rather than blocking every other
    // product in the same request.
    public function assignProducts(Request $request, Discount $discount)
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:Product,ProductID'],
        ]);

        $startDate = $discount->StartDate?->format('Y-m-d');
        $endDate = $discount->EndDate?->format('Y-m-d');

        $alreadyAssigned = $discount->products()->pluck('Product.ProductID')->all();
        $assigned = [];
        $rejected = [];

        foreach (array_unique($data['product_ids']) as $productId) {
            $productId = (int) $productId;

            if (in_array($productId, $alreadyAssigned, true)) {
                continue;
            }

            if ($this->hasOverlappingActivePromo($productId, $startDate, $endDate, $discount->DiscountID)) {
                $rejected[] = $productId;
                continue;
            }

            $assigned[] = $productId;
        }

        if (! empty($assigned)) {
            $discount->products()->syncWithoutDetaching($assigned);
        }

        ActivityLog::record('discount.products_assigned', "Assigned promo \"{$discount->PromoCode}\" to " . count($assigned) . ' product(s)');

        $message = count($assigned) . ' product(s) assigned.';
        if (! empty($rejected)) {
            $message .= ' ' . count($rejected) . ' skipped — already covered by another promo in an overlapping date range.';
        }

        return response()->json([
            'success' => true,
            'assigned' => $assigned,
            'rejected' => $rejected,
            'message' => $message,
        ]);
    }

    // Remove one product from a promo's assignment (the "Applied list"
    // row action). Blocked, like destroy(), once the promo has been used
    // in any past transaction — Billing doesn't track a per-product
    // breakdown of a multi-product promo's discount, so this is a
    // conservative "the whole promo was used" guard rather than a
    // per-product one.
    public function detachProduct(Discount $discount, Product $product)
    {
        if ($discount->billings()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove — this promo has already been used in past transactions.',
            ], 422);
        }

        $discount->products()->detach($product->ProductID);

        ActivityLog::record('discount.product_detached', "Removed product #{$product->ProductID} from promo \"{$discount->PromoCode}\"");

        return response()->json(['success' => true]);
    }

    // History modal data — Expired (past EndDate) and Used (referenced by
    // a Billing) promos. Neither is ever deleted; this is purely a
    // read-only audit view over records that already exist.
    public function history(Request $request)
    {
        $today = now()->toDateString();

        $expired = Discount::with('products')
            ->whereNotNull('PromoCode')
            ->whereNotNull('EndDate')
            ->whereDate('EndDate', '<', $today)
            ->orderByDesc('EndDate')
            ->paginate(10, ['*'], 'expired_page');

        $used = Billing::with(['discount.products', 'transaction'])
            ->whereNotNull('DiscountID')
            ->orderByDesc('BillingDate')
            ->paginate(10, ['*'], 'used_page');

        return response()->json([
            'expiredHtml' => view('admin.discounts.partials.history-expired', compact('expired'))->render(),
            'usedHtml' => view('admin.discounts.partials.history-used', compact('used'))->render(),
        ]);
    }

    private function validatePromo(Request $request, ?Discount $discount = null): array
    {
        $discountId = $discount?->DiscountID;

        // Normalized to uppercase before validation runs, so the uniqueness
        // check itself is inherently case-insensitive regardless of the
        // database driver's collation (MySQL's default is already
        // case-insensitive, but the test suite runs on SQLite, which isn't).
        $request->merge(['PromoCode' => strtoupper(trim((string) $request->input('PromoCode', '')))]);

        return $request->validate([
            'DiscountRate' => ['required', 'numeric', 'min:1', 'max:100'],
            'DiscountType' => ['required', Rule::in([Discount::TYPE_PERCENTAGE])],
            'Name' => ['required', 'string', 'max:100'],
            'PromoCode' => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('Discount', 'PromoCode')->ignore($discountId, 'DiscountID')->whereNull('deleted_at'),
            ],
            'Description' => ['nullable', 'string', 'max:1000'],
            'StartDate' => ['required', 'date'],
            'EndDate' => ['required', 'date', 'after_or_equal:StartDate'],
        ], [
            'DiscountRate.min' => 'Discount percentage must be at least 1%.',
            'DiscountRate.max' => 'Discount percentage cannot exceed 100%.',
            'DiscountType.in' => 'Only Percentage promos are supported right now.',
            'PromoCode.regex' => 'Promo code may only contain letters, numbers, and hyphens.',
            'PromoCode.unique' => 'This promo code is already in use.',
            'EndDate.after_or_equal' => 'End Date cannot be earlier than Start Date.',
        ]);
    }

    // Checked at assign-time (per product being assigned) rather than at
    // promo-creation time, since a promo no longer has a product until
    // it's explicitly assigned one. Only one promo may cover a given
    // product for any overlapping date window.
    private function hasOverlappingActivePromo(int $productId, ?string $startDate, ?string $endDate, ?int $excludeDiscountId = null): bool
    {
        return Discount::whereHas('products', fn ($q) => $q->where('Product.ProductID', $productId))
            ->when($excludeDiscountId, fn ($q) => $q->where('DiscountID', '!=', $excludeDiscountId))
            ->where(function ($q) use ($startDate) {
                $q->whereNull('EndDate')->orWhereDate('EndDate', '>=', $startDate ?? now()->toDateString());
            })
            ->where(function ($q) use ($endDate) {
                $q->whereNull('StartDate')->orWhereDate('StartDate', '<=', $endDate ?? '9999-12-31');
            })
            ->exists();
    }
}
