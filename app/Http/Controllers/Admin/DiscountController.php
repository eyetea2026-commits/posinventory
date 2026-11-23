<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Discount;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Notifications\DiscountUpdated;
use App\Services\ActionNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    public function index(Request $request)
    {
        $search = $request->get('search');

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

        if ($request->boolean('ajax')) {
            $applyTabData = $this->applyTabData();

            return response()->json([
                'rows' => view('admin.discounts.partials.rows', ['discounts' => $discounts])->render(),
                'pagination' => view('admin.discounts.partials.pagination', ['discounts' => $discounts])->render(),
                'allDiscounts' => $applyTabData['allDiscounts']->map(fn ($d) => [
                    'id' => $d->DiscountID, 'name' => $d->Name, 'code' => $d->PromoCode,
                ])->values(),
                'discountMeta' => $applyTabData['discountMeta'],
                'discountProductMap' => $applyTabData['discountProductMap'],
            ]);
        }

        if ($request->boolean('ajax_products')) {
            $productSearch = $request->get('product_search');

            $products = Product::with('category')
                ->when($productSearch, function ($query) use ($productSearch) {
                    $query->where('ProductName', 'like', "%{$productSearch}%");
                })
                ->orderBy('ProductName')
                ->limit(20)
                ->get();

            return response()->json([
                'products' => $products->map(fn ($p) => [
                    'id' => $p->ProductID,
                    'name' => $p->ProductName,
                    'category' => $p->category?->CategoryName,
                    'price' => (float) $p->Price,
                ]),
            ]);
        }

        $today = now()->toDateString();

        $appliedAssignments = Discount::whereHas('products')
            ->where(function ($q) use ($today) {
                $q->whereNull('EndDate')->orWhereDate('EndDate', '>=', $today);
            })
            ->withCount('products')
            ->orderByDesc('DiscountID')
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
            'discountProductMap' => $applyTabData['discountProductMap'],
            'discountMeta' => $applyTabData['discountMeta'],
        ]);
    }

    private function applyTabData(): array
    {
        $allDiscounts = Discount::whereNotNull('PromoCode')->notExpired()->orderBy('Name')
            ->get(['DiscountID', 'Name', 'PromoCode', 'DiscountType', 'DiscountRate', 'StartDate', 'EndDate']);

        $allDiscountsForDetails = Discount::with('products.category')
            ->orderByDesc('DiscountID')
            ->get(['DiscountID', 'Name', 'PromoCode', 'DiscountType', 'DiscountRate', 'StartDate', 'EndDate', 'Description']);

        return [
            'allDiscounts' => $allDiscounts,
            'discountProductMap' => $allDiscountsForDetails->mapWithKeys(function ($d) {
                return [$d->DiscountID => $d->products->map(fn ($p) => [
                    'id' => $p->ProductID,
                    'name' => $p->ProductName,
                    'category' => $p->category?->CategoryName,
                ])];
            }),
            'discountMeta' => $allDiscountsForDetails->mapWithKeys(function ($d) {
                return [$d->DiscountID => [
                    'name' => $d->Name,
                    'description' => $d->Description,
                    'code' => $d->PromoCode ?? '—',
                    'typeLabel' => $d->DiscountType === Discount::TYPE_FIXED ? 'Fixed Amount' : 'Percentage',
                    'valueLabel' => $d->DiscountType === Discount::TYPE_FIXED
                        ? '₱'.number_format($d->DiscountRate, 2)
                        : number_format($d->DiscountRate, 2).'%',
                    'start' => $d->StartDate?->format('m/d/Y') ?? '—',
                    'end' => $d->EndDate?->format('m/d/Y') ?? '—',
                    'statusLabel' => $d->effective_status_label,
                    'statusClass' => $d->effective_status === Discount::STATUS_EXPIRED ? 'badge-secondary'
                        : ($d->effective_status === Discount::STATUS_INACTIVE ? 'badge-warning' : 'badge-success'),
                ]];
            }),
        ];
    }

    public function create()
    {
        return view('admin.discounts.create');
    }

    public function checkPromoCode(Request $request)
    {
        $code = strtoupper(trim((string) $request->input('PromoCode', '')));
        $name = (string) $request->input('Name', '');
        $excludeId = $request->input('exclude_id');

        $taken = $code !== '' && Discount::where('PromoCode', $code)
            ->when($excludeId, fn ($q) => $q->where('DiscountID', '!=', $excludeId))
            ->exists();

        return response()->json([
            'promo_code' => $taken,
            'promo_code_value' => $request->input('PromoCode', ''),
            'name' => self::promoNameTaken($name, $excludeId ? (int) $excludeId : null),
            'name_value' => $name,
        ]);
    }

    private static function promoNameTaken(string $name, ?int $excludeDiscountId = null): bool
    {
        $normalized = mb_strtolower(trim($name));
        if ($normalized === '') {
            return false;
        }

        return Discount::whereRaw('LOWER(TRIM(Name)) = ?', [$normalized])
            ->when($excludeDiscountId, fn ($q) => $q->where('DiscountID', '!=', $excludeDiscountId))
            ->exists();
    }

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

        try {
            ActivityLog::record('discount.created', "Created promo \"{$discount->PromoCode}\"");
        } catch (Throwable $e) {
            Log::error('Failed to record discount.created activity log', [
                'discount_id' => $discount->DiscountID,
                'exception' => $e->getMessage(),
            ]);
        }

        dispatch(function () use ($discount) {
            try {
                Notification::send(User::admins(), new DiscountUpdated($discount, 'Created'));
            } catch (Throwable $e) {
                Log::error('Failed to dispatch DiscountUpdated notification', [
                    'discount_id' => $discount->DiscountID,
                    'exception' => $e->getMessage(),
                ]);
            }
        })->afterResponse();

        return redirect()->route('admin.discounts.index')->with('success', 'Promo code created successfully.');
    }

    public function edit(Request $request, Discount $discount)
    {
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'html' => view('admin.discounts.partials.discount-form-fields', [
                    'discount' => $discount,
                ])->render(),
            ]);
        }

        return view('admin.discounts.edit', ['discount' => $discount]);
    }

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

        dispatch(function () use ($discount) {
            try {
                Notification::send(User::admins(), new DiscountUpdated($discount, 'Updated'));
            } catch (Throwable $e) {
                Log::error('Failed to dispatch DiscountUpdated notification', [
                    'discount_id' => $discount->DiscountID,
                    'exception' => $e->getMessage(),
                ]);
            }
        })->afterResponse();

        return redirect()->route('admin.discounts.index')->with('success', 'Promo code updated successfully.');
    }

    public function show(Discount $discount)
    {
        $discount->load(['products.category', 'createdByUser']);

        return view('admin.discounts.show', ['discount' => $discount]);
    }

    public function destroy(Discount $discount)
    {
        if ($discount->salesTransactions()->count() > 0) {
            return redirect()->route('admin.discounts.index')->with('error', 'Cannot delete a promo that is associated with past transactions.');
        }

        $discount->delete();

        ActivityLog::record('discount.deleted', "Deleted promo \"{$discount->PromoCode}\"");
        ActionNotifier::toAdmins('Promo Code Deleted', "The promo code \"{$discount->PromoCode}\" has been deleted.", route('admin.discounts.index'), 'percent', 'danger');

        return redirect()->route('admin.discounts.index')->with('success', 'Promo code deleted successfully.');
    }

    public function assignProducts(Request $request, Discount $discount)
    {
        if ($discount->effective_status === Discount::STATUS_EXPIRED) {
            return response()->json([
                'success' => false,
                'message' => 'This promo has already expired and can no longer be applied.',
            ], 422);
        }

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

        $alreadyAssigned = $discount->products()->pluck('Product.ProductID')->all();
        $candidateIds = array_values(array_diff($requestedIds, $alreadyAssigned));

        $overlapping = $this->productIdsWithOverlappingActivePromo($candidateIds, $startDate, $endDate, $discount->DiscountID);
        $assigned = array_values(array_diff($candidateIds, $overlapping));
        $rejected = array_values($overlapping);

        if (! empty($assigned)) {
            $now = now();
            DB::table('DiscountProduct')->insertOrIgnore(
                collect($assigned)->map(fn ($productId) => [
                    'DiscountID' => $discount->DiscountID,
                    'ProductID' => $productId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );
        }

        try {
            ActivityLog::record('discount.products_assigned', "Assigned promo \"{$discount->PromoCode}\" to ".count($assigned).' product(s)');
        } catch (Throwable $e) {
            Log::error('Failed to record discount.products_assigned activity log', [
                'discount_id' => $discount->DiscountID,
                'exception' => $e->getMessage(),
            ]);
        }

        $message = count($assigned).' product(s) assigned.';
        if (count($assigned) > 0) {
            ActionNotifier::toAdmins('Promo Products Assigned', count($assigned)." product(s) have been added to the promo code \"{$discount->PromoCode}\".", route('admin.discounts.index'), 'percent', 'success');
        }

        if (! empty($rejected)) {
            $message .= ' '.count($rejected).' skipped — already covered by another promo in an overlapping date range.';
        }

        return response()->json([
            'success' => true,
            'assigned' => $assigned,
            'rejected' => $rejected,
            'message' => $message,
        ]);
    }

    public function detachProduct(Discount $discount, Product $product)
    {
        if ($discount->salesTransactions()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove — this promo has already been used in past transactions.',
            ], 422);
        }

        $discount->products()->detach($product->ProductID);

        ActivityLog::record('discount.product_detached', "Removed product #{$product->ProductID} from promo \"{$discount->PromoCode}\"");
        ActionNotifier::toAdmins('Promo Product Removed', "\"{$product->ProductName}\" has been removed from the promo code \"{$discount->PromoCode}\".", route('admin.discounts.index'), 'percent', 'warning');

        return response()->json(['success' => true]);
    }

    public function history(Request $request)
    {
        $today = now()->toDateString();

        $expired = Discount::with('products')
            ->whereNotNull('PromoCode')
            ->whereNotNull('EndDate')
            ->whereDate('EndDate', '<', $today)
            ->orderByDesc('EndDate')
            ->paginate(10, ['*'], 'expired_page');

        $used = SalesTransaction::with(['discount.products'])
            ->whereNotNull('DiscountID')
            ->orderByDesc('SalesTransactionDate')
            ->paginate(10, ['*'], 'used_page');

        return response()->json([
            'expiredHtml' => view('admin.discounts.partials.history-expired', compact('expired'))->render(),
            'usedHtml' => view('admin.discounts.partials.history-used', compact('used'))->render(),
        ]);
    }

    private function validatePromo(Request $request, ?Discount $discount = null): array
    {
        $discountId = $discount?->DiscountID;

        $request->merge(['PromoCode' => strtoupper(trim((string) $request->input('PromoCode', '')))]);

        $savedStartDate = $discount?->StartDate?->format('Y-m-d');
        $startDateRules = ['required', 'date', function (string $attribute, mixed $value, \Closure $fail) use ($savedStartDate) {
            $date = Carbon::parse($value)->toDateString();
            if ($date < today()->toDateString() && $date !== $savedStartDate) {
                $fail('Start Date cannot be in the past.');
            }
        }];

        return $request->validate([
            'DiscountRate' => ['required', 'numeric', 'min:1', 'max:100'],
            'DiscountType' => ['required', Rule::in([Discount::TYPE_PERCENTAGE])],
            'Name' => [
                'required', 'string', 'max:100',
                function (string $attribute, mixed $value, \Closure $fail) use ($discountId) {
                    if (self::promoNameTaken((string) $value, $discountId)) {
                        $fail('This promo name is already in use.');
                    }
                },
            ],
            'PromoCode' => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('Discount', 'PromoCode')->ignore($discountId, 'DiscountID')->whereNull('deleted_at'),
            ],
            'Description' => ['nullable', 'string', 'max:1000'],
            'StartDate' => $startDateRules,
            'EndDate' => ['required', 'date', 'after_or_equal:StartDate', 'after_or_equal:today'],
        ], [
            'DiscountRate.min' => 'Discount percentage must be at least 1%.',
            'DiscountRate.max' => 'Discount percentage cannot exceed 100%.',
            'DiscountType.in' => 'Only Percentage promos are supported right now.',
            'PromoCode.regex' => 'Promo code may only contain letters, numbers, and hyphens.',
            'PromoCode.unique' => 'This promo code is already in use.',
            'StartDate.after_or_equal' => 'Start Date cannot be in the past.',
            'EndDate.after_or_equal' => 'End Date cannot be earlier than Start Date or in the past.',
        ]);
    }

    private function productIdsWithOverlappingActivePromo(array $productIds, ?string $startDate, ?string $endDate, ?int $excludeDiscountId = null): array
    {
        if (empty($productIds)) {
            return [];
        }

        return DB::table('DiscountProduct')
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
