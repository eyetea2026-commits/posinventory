<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    /**
     * Friendly names for each Action prefix (the part before the dot) that
     * ActivityLog::record() is called with across the app. Unknown prefixes
     * fall back to a headline-cased version of the prefix itself.
     *
     * @var array<string, string>
     */
    public const MODULE_LABELS = [
        'auth' => 'Authentication',
        'user' => 'User Management',
        'product' => 'Products',
        'product_supplier' => 'Product Suppliers',
        'discount' => 'Discounts',
        'supplier' => 'Suppliers',
        'purchase_order' => 'Purchase Orders',
        'stock' => 'Stock',
        'stock_receiving_batch' => 'Stock Receiving',
        'damage' => 'Damage',
        'return' => 'Returns',
    ];

    /** @var list<string> */
    private const FILTER_KEYS = ['search', 'module', 'user_id', 'role', 'date_from', 'date_to'];

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

    /**
     * Read-only audit trail — deliberately no edit/delete actions, so the
     * log can't be tampered with from the UI it's meant to monitor.
     */
    public function index(Request $request): View
    {
        // Not $request->validate(): on a GET page its redirect-back on
        // failure can loop when the bad query string is the page itself.
        // Invalid filters are simply dropped and the message shown instead.
        $validator = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', 'string', 'max:50'],
            'user_id' => ['nullable', 'integer'],
            'role' => ['nullable', 'in:admin,cashier'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ], [
            'date_to.after_or_equal' => 'The "To" date cannot be earlier than the "From" date.',
        ]);

        $filters = array_intersect_key($validator->valid(), array_flip(self::FILTER_KEYS));
        $filterError = $validator->errors()->first();

        $logs = ActivityLog::with('user.role')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('Description', 'like', "%{$search}%");
            })
            ->when($filters['module'] ?? null, function ($query, $module) {
                $query->where('Action', 'like', "{$module}.%");
            })
            ->when($filters['user_id'] ?? null, function ($query, $userId) {
                $query->where('UserID', $userId);
            })
            ->when($filters['role'] ?? null, function ($query, $role) {
                $query->whereHas('user.role', function ($roleQuery) use ($role) {
                    $roleQuery->where('role_name', $role);
                });
            })
            ->when($filters['date_from'] ?? null, function ($query, $dateFrom) {
                $query->whereDate('DateRecorded', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function ($query, $dateTo) {
                $query->whereDate('DateRecorded', '<=', $dateTo);
            })
            ->orderByDesc('DateRecorded')
            ->orderByDesc('ActivityLogID')
            ->paginate(25)
            ->withQueryString();

        $modules = ActivityLog::query()
            ->pluck('Action')
            ->map(fn (string $action) => Str::before($action, '.'))
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $prefix) => [$prefix => self::moduleLabel($prefix)])
            ->all();

        $users = User::whereIn('id', ActivityLog::query()->whereNotNull('UserID')->distinct()->pluck('UserID'))
            ->orderBy('first_name')
            ->get();

        return view('admin.activity-logs.index', [
            'logs' => $logs,
            'modules' => $modules,
            'users' => $users,
            'filters' => $filters,
            'filterError' => $filterError,
        ]);
    }

    public static function moduleLabel(string $prefix): string
    {
        return self::MODULE_LABELS[$prefix] ?? Str::headline($prefix);
    }

    public static function actionLabel(string $action): string
    {
        return Str::headline(Str::after($action, '.'));
    }
}
