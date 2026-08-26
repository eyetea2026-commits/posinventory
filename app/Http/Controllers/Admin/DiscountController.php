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

        // Expired promos never show in this list — they're moved to History
        // automatically (a live query, not a stored flag: see
        // Discount::scopeNotExpired() / getEffectiveStatusAttribute()) as
        // soon as their EndDate passes, with no admin action needed.
        $discounts = Discount::with('products')
            ->notExpired()
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
            $applyTabData = $this->applyTabData();

            return response()->json([
                'rows' => view('admin.discounts.partials.rows', ['discounts' => $discounts])->render(),
                'pagination' => view('admin.discounts.partials.pagination', ['discounts' => $discounts])->render(),
                // So a promo just created/edited via this same page's Add/Edit
                // modal shows up (or updates) in the Apply tab's "Choose Promo
                // Discount" dropdown and View Details data without a full page
                // reload — see refreshDiscountsTable()/updateApplyTabData() in
                // the view.
                'allDiscounts' => $applyTabData['allDiscounts']->map(fn ($d) => [
                    'id' => $d->DiscountID, 'name' => $d->Name, 'code' => $d->PromoCode,
                ])->values(),
                'discountMeta' => $applyTabData['discountMeta'],
                'discountProductMap' => $applyTabData['discountProductMap'],
            ]);
        }

        // Tab 2's product picker — a compact searchable multi-select, not a
        // paginated catalog, so this just returns a capped, unpaginated
        // JSON list of matches for whatever's currently typed.
        if ($request->boolean('ajax_products')) {
            $productSearch = $request->get('product_search');

            $products = Product::with('category')
                ->when($productSearch, function ($query) use ($productSearch) {
                    $query->where(function ($inner) use ($productSearch) {
                        $inner->where('ProductName', 'like', "%{$productSearch}%")
                            ->orWhere('SKU', 'like', "%{$productSearch}%");
                    });
                })
                ->orderBy('ProductName')
                ->limit(20)
                ->get();

            return response()->json([
                'products' => $products->map(fn ($p) => [
                    'id' => $p->ProductID,
                    'name' => $p->ProductName,
                    'sku' => $p->SKU,
                    'category' => $p->category?->CategoryName,
                    'price' => (float) $p->Price,
                ]),
            ]);
        }

        // Tab 2's "Applied Discount/Promo List" — one row per product-promo
        // assignment (a DiscountProduct pivot row), not per promo. Once the
        // promo's EndDate passes, its assignment rows stop showing here at
        // all — they're not deleted, just no longer part of this "currently
        // active" view; they keep showing under History → Expired instead
        // (same underlying Discount/DiscountProduct rows, no duplication).
        $today = now()->toDateString();

        $appliedAssignments = \Illuminate\Support\Facades\DB::table('DiscountProduct')
            ->join('Discount', 'Discount.DiscountID', '=', 'DiscountProduct.DiscountID')
            ->join('Product', 'Product.ProductID', '=', 'DiscountProduct.ProductID')
            ->whereNull('Discount.deleted_at')
            ->where(function ($q) use ($today) {
                $q->whereNull('Discount.EndDate')->orWhereDate('Discount.EndDate', '>=', $today);
            })
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

        $applyTabData = $this->applyTabData();

        return view('admin.discounts.index', [
            'discounts' => $discounts,
            'search' => $search,
            'appliedAssignments' => $appliedAssignments,
            'allDiscounts' => $applyTabData['allDiscounts'],
            // Pre-built in PHP (not inline in the Blade view) so the two
            // @json() calls there stay simple single-variable expressions —
            // Blade's @json directive splits its argument on every
            // top-level comma to find an optional second (encoding-options)
            // argument, which silently mangles anything more elaborate
            // embedded directly in the view.
            'discountProductMap' => $applyTabData['discountProductMap'],
            'discountMeta' => $applyTabData['discountMeta'],
        ]);
    }

    // Shared by the initial page render and the Tab 1 live-search/pagination
    // AJAX response (so a promo created or edited via the Add/Edit popup
    // shows up correctly in the Apply tab's dropdown and View Details data
    // without requiring a full page reload).
    //
    // discountMeta/discountProductMap are built from EVERY Discount row
    // (not just ones with a PromoCode) so the View Details popup works for
    // every row Tab 1's list can show, including a pre-redesign legacy row
    // that predates PromoCode existing — expired ones included, since
    // History needs to display them too. allDiscounts (the "Choose Promo
    // Discount" dropdown's source) stays restricted to named, non-expired
    // promos — an expired promo can never be selected to apply, matching
    // assignProducts()'s own server-side expiry guard.
    private function applyTabData(): array
    {
        $allDiscounts = Discount::whereNotNull('PromoCode')->notExpired()->orderBy('Name')
            ->get(['DiscountID', 'Name', 'PromoCode', 'DiscountType', 'DiscountRate', 'StartDate', 'EndDate']);

        // Was $d->products()->with('category')->get() inside the
        // mapWithKeys() below — a fresh pair of queries per discount (N+1).
        // Eager-loading products.category here instead makes this whole
        // method a constant 3 queries total (discounts, products, product
        // categories) no matter how many discounts exist.
        $allDiscountsForDetails = Discount::with('products.category')
            ->orderByDesc('DiscountID')
            ->get(['DiscountID', 'Name', 'PromoCode', 'DiscountType', 'DiscountRate', 'StartDate', 'EndDate']);

        return [
            'allDiscounts' => $allDiscounts,
            'discountProductMap' => $allDiscountsForDetails->mapWithKeys(function ($d) {
                return [$d->DiscountID => $d->products->map(fn ($p) => [
                    'id' => $p->ProductID,
                    'name' => $p->ProductName,
                    'sku' => $p->SKU,
                    'category' => $p->category?->CategoryName,
                ])];
            }),
            'discountMeta' => $allDiscountsForDetails->mapWithKeys(function ($d) {
                return [$d->DiscountID => [
                    'name' => $d->Name,
                    'code' => $d->PromoCode ?? '—',
                    'typeLabel' => $d->DiscountType === Discount::TYPE_FIXED ? 'Fixed Amount' : 'Percentage',
                    'valueLabel' => $d->DiscountType === Discount::TYPE_FIXED
                        ? '₱' . number_format($d->DiscountRate, 2)
                        : number_format($d->DiscountRate, 2) . '%',
                    'start' => $d->StartDate?->format('M d, Y') ?? '—',
                    'end' => $d->EndDate?->format('M d, Y') ?? '—',
                    // "Scheduled" here (Apply tab display only) rather than
                    // the "Inactive" label Discount::STATUS_LABELS uses
                    // elsewhere — a promo that hasn't started yet reads more
                    // clearly as scheduled than inactive in this context.
                    'statusLabel' => $d->effective_status === Discount::STATUS_EXPIRED ? 'Expired'
                        : ($d->effective_status === Discount::STATUS_INACTIVE ? 'Scheduled' : 'Active'),
                    'statusClass' => $d->effective_status === Discount::STATUS_EXPIRED ? 'badge-secondary'
                        : ($d->effective_status === Discount::STATUS_INACTIVE ? 'badge-warning' : 'badge-success'),
                ]];
            }),
        ];
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

        // Guarded like the notification below it: the discount is already
        // committed to the database at this point, so a logging failure
        // here must never turn into a 500 that tells the admin their promo
        // wasn't saved when it actually was.
        try {
            ActivityLog::record('discount.created', "Created promo \"{$discount->PromoCode}\"");
        } catch (Throwable $e) {
            Log::error('Failed to record discount.created activity log', [
                'discount_id' => $discount->DiscountID,
                'exception' => $e->getMessage(),
            ]);
        }

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
        // Belt-and-suspenders: the "Choose Promo Discount" dropdown already
        // excludes expired promos, but that's a client-side snapshot — a
        // tab left open past the promo's EndDate, or a direct API call,
        // must still be rejected here. An expired promo can never be
        // applied to a product, full stop.
        if ($discount->effective_status === Discount::STATUS_EXPIRED) {
            return response()->json([
                'success' => false,
                'message' => 'This promo has already expired and can no longer be applied.',
            ], 422);
        }

        // Just shape/type validation here — deliberately not
        // 'exists:Product,ProductID' on 'product_ids.*': Laravel's exists
        // rule validates each array item as its own field, which runs one
        // presence-check query PER selected product instead of batching
        // them. Existence for the whole selection is checked below in one
        // whereIn() query instead.
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer'],
        ]);

        $requestedIds = array_values(array_unique(array_map('intval', $data['product_ids'])));
        $validIds = Product::whereIn('ProductID', $requestedIds)->pluck('ProductID')->map(fn ($id) => (int) $id)->all();

        if (count($validIds) !== count($requestedIds)) {
            return response()->json([
                'success' => false,
                'message' => 'One or more selected products could not be found.',
            ], 422);
        }

        $startDate = $discount->StartDate?->format('Y-m-d');
        $endDate = $discount->EndDate?->format('Y-m-d');

        // One query for "already assigned", one query for "covered by an
        // overlapping promo" — checked for the whole batch of selected
        // products at once (whereIn), not per product in a loop. Selecting
        // 10 products used to mean 10+ separate overlap queries; now it's
        // always exactly 2 regardless of how many are selected.
        $alreadyAssigned = $discount->products()->pluck('Product.ProductID')->all();
        $candidateIds = array_values(array_diff($requestedIds, $alreadyAssigned));

        $overlapping = $this->productIdsWithOverlappingActivePromo($candidateIds, $startDate, $endDate, $discount->DiscountID);
        $assigned = array_values(array_diff($candidateIds, $overlapping));
        $rejected = array_values($overlapping);

        if (! empty($assigned)) {
            // A single multi-row insert instead of
            // $discount->products()->syncWithoutDetaching($assigned) —
            // that helper re-queries current pivot rows (redundant, we
            // already have $alreadyAssigned above) and then inserts one row
            // at a time. $assigned is already guaranteed not-yet-attached,
            // so a plain bulk insert is enough; insertOrIgnore only as a
            // safety net against a concurrent request assigning the same
            // pair in the moment between our check and this write.
            $now = now();
            \Illuminate\Support\Facades\DB::table('DiscountProduct')->insertOrIgnore(
                collect($assigned)->map(fn ($productId) => [
                    'DiscountID' => $discount->DiscountID,
                    'ProductID' => $productId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );
        }

        // Guarded like store()'s activity log: the sync above has already
        // committed, so a logging hiccup must never be reported back as a
        // failed apply when the products were actually assigned.
        try {
            ActivityLog::record('discount.products_assigned', "Assigned promo \"{$discount->PromoCode}\" to " . count($assigned) . ' product(s)');
        } catch (Throwable $e) {
            Log::error('Failed to record discount.products_assigned activity log', [
                'discount_id' => $discount->DiscountID,
                'exception' => $e->getMessage(),
            ]);
        }

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
    // product for any overlapping date window. Batched over every
    // candidate product ID in a single query (whereIn), not called once
    // per product — returns just the subset that's already covered.
    private function productIdsWithOverlappingActivePromo(array $productIds, ?string $startDate, ?string $endDate, ?int $excludeDiscountId = null): array
    {
        if (empty($productIds)) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table('DiscountProduct')
            ->join('Discount', 'Discount.DiscountID', '=', 'DiscountProduct.DiscountID')
            ->whereIn('DiscountProduct.ProductID', $productIds)
            ->whereNull('Discount.deleted_at')
            ->when($excludeDiscountId, fn ($q) => $q->where('Discount.DiscountID', '!=', $excludeDiscountId))
            ->where(function ($q) use ($startDate) {
                $q->whereNull('Discount.EndDate')->orWhereDate('Discount.EndDate', '>=', $startDate ?? now()->toDateString());
            })
            ->where(function ($q) use ($endDate) {
                $q->whereNull('Discount.StartDate')->orWhereDate('Discount.StartDate', '<=', $endDate ?? '9999-12-31');
            })
            ->pluck('DiscountProduct.ProductID')
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
