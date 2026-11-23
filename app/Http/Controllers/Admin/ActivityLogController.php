<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ActivityLogController extends Controller
{
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

    private const FILTER_KEYS = ['log_search', 'log_module', 'log_user', 'log_role', 'log_from', 'log_to'];

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

    public function index(Request $request): JsonResponse|RedirectResponse
    {
        if (! ($request->ajax() || $request->wantsJson())) {
            return redirect()->to(route('admin.dashboard').'#activity-log');
        }

        $validator = Validator::make($request->query(), [
            'log_search' => ['nullable', 'string', 'max:100'],
            'log_module' => ['nullable', 'string', 'max:50'],
            'log_user' => ['nullable', 'integer'],
            'log_role' => ['nullable', 'in:admin,cashier'],
            'log_from' => ['nullable', 'date'],
            'log_to' => ['nullable', 'date', 'after_or_equal:log_from'],
        ], [
            'log_to.after_or_equal' => 'The "To" date cannot be earlier than the "From" date.',
        ]);

        $filters = array_intersect_key($validator->valid(), array_flip(self::FILTER_KEYS));

        $logs = ActivityLog::with('user.role')
            ->when($filters['log_search'] ?? null, function ($query, $search) {
                $query->where('Description', 'like', "%{$search}%");
            })
            ->when($filters['log_module'] ?? null, function ($query, $module) {
                $query->where('Action', 'like', "{$module}.%");
            })
            ->when($filters['log_user'] ?? null, function ($query, $userId) {
                $query->where('UserID', $userId);
            })
            ->when($filters['log_role'] ?? null, function ($query, $role) {
                $query->whereHas('user.role', function ($roleQuery) use ($role) {
                    $roleQuery->where('role_name', $role);
                });
            })
            ->when($filters['log_from'] ?? null, function ($query, $dateFrom) {
                $query->whereDate('DateRecorded', '>=', $dateFrom);
            })
            ->when($filters['log_to'] ?? null, function ($query, $dateTo) {
                $query->whereDate('DateRecorded', '<=', $dateTo);
            })
            ->orderByDesc('DateRecorded')
            ->orderByDesc('ActivityLogID')
            ->paginate(10, ['*'], 'log_page')
            ->withPath(route('admin.activity-logs.index'))
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
            ->get()
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->full_name])
            ->values();

        return response()->json([
            'html' => view('admin.dashboard.partials.activity-log', [
                'logs' => $logs,
                'hasFilters' => collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty(),
            ])->render(),
            'modules' => $modules,
            'users' => $users,
            'filterError' => $validator->errors()->first(),
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
