<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /** Rolling window (days) used to compute sales velocity. */
    public const SALES_WINDOW_DAYS = 30;

    /** Minimum units sold in window to be classified as fast-moving. */
    public const FAST_MOVING_THRESHOLD = 30;

    /** Maximum units sold in window to be classified as slow-moving. */
    public const SLOW_MOVING_THRESHOLD = 5;

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
        $search = $request->query('search');
        $status = $request->query('status');
        $cutoff = now()->subDays(self::SALES_WINDOW_DAYS);

        $query = Product::query()
            ->with(['category', 'inventory'])
            ->withSum([
                'salesItems' => function ($q) use ($cutoff) {
                    $q->whereHas('transaction', function ($t) use ($cutoff) {
                        $t->where('SalesTransactionDate', '>=', $cutoff);
                    });
                },
            ], 'Quantity');

        // REQ044: search by name or keyword (name + model + SKU + barcode + category)
        if ($search) {
            $query->where(function ($inner) use ($search) {
                $inner->where('ProductName', 'like', "%{$search}%")
                    ->orWhere('Model', 'like', "%{$search}%")
                    ->orWhere('SKU', 'like', "%{$search}%")
                    ->orWhere('Barcode', 'like', "%{$search}%")
                    ->orWhereHas('category', function ($category) use ($search) {
                        $category->where('CategoryName', 'like', "%{$search}%");
                    });
            });
        }

        // REQ045: apply mutually-exclusive status filter at the SQL layer
        $this->applyStatusFilter($query, $status);

        $query->orderBy('ProductName');

        $products = $query->paginate(15)->withQueryString();

        // Recomputed on every request (not just the initial page load) so a
        // background poll can keep the pill counts current alongside the
        // table rows — see resources/views/admin/inventory/index.blade.php.
        $counts = $this->buildStatusCounts();

        // AJAX request — return just the table rows + pagination as partials
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'rows' => view('admin.inventory.partials.rows', ['products' => $products])->render(),
                'pagination' => view('admin.inventory.partials.pagination', ['products' => $products])->render(),
                'counts' => $counts,
            ]);
        }

        return view('admin.inventory.index', [
            'products' => $products,
            'search' => $search,
            'status' => $status,
            'counts' => $counts,
        ]);
    }

    public function show(Product $product)
    {
        $product->load(['category', 'brand', 'inventory']);

        $velocity = (int) $product->salesItems()
            ->whereHas('transaction', function ($t) {
                $t->where('SalesTransactionDate', '>=', now()->subDays(self::SALES_WINDOW_DAYS));
            })
            ->sum('Quantity');

        $quantity = (int) ($product->inventory?->Quantity ?? 0);
        $threshold = (int) ($product->inventory?->ReorderThreshold ?? 50);
        $stock = self::resolveStockStatus($quantity, $threshold);

        $velocityLabel = match (true) {
            $velocity >= self::FAST_MOVING_THRESHOLD => 'fast-moving',
            $velocity < self::SLOW_MOVING_THRESHOLD => 'slow-moving',
            default => 'normal',
        };

        return view('admin.inventory.show', [
            'product' => $product,
            'velocity' => $velocity,
            'velocityLabel' => $velocityLabel,
            'stock' => $stock,
        ]);
    }

    /**
     * Resolve stock status label and Bootstrap badge class based on
     * current quantity and reorder threshold. Mirrors the helper on
     * ProductController so the inventory table reuses the same badge
     * styling as the products table.
     */
    public static function resolveStockStatus(?int $quantity, ?int $reorderThreshold = null): array
    {
        $quantity = $quantity ?? 0;
        $reorderThreshold = $reorderThreshold ?? 50;

        if ($quantity <= 0) {
            return ['label' => 'Out of Stock', 'class' => 'badge-out-of-stock', 'icon' => 'fa-times-circle'];
        }
        if ($quantity <= max(1, (int) round($reorderThreshold * 0.25))) {
            return ['label' => 'Replenish', 'class' => 'badge-replenish', 'icon' => 'fa-exclamation-circle'];
        }
        if ($quantity <= $reorderThreshold) {
            return ['label' => 'Low Stock', 'class' => 'badge-low-stock', 'icon' => 'fa-exclamation-triangle'];
        }
        return ['label' => 'In Stock', 'class' => 'badge-in-stock', 'icon' => 'fa-check-circle'];
    }

    /**
     * Apply the mutually-exclusive status filter at the SQL layer.
     * Priority order: out-of-stock > low-stock > fast-moving > slow-moving > available.
     */
    private function applyStatusFilter($query, ?string $status): void
    {
        if (! $status) {
            return;
        }

        $velocitySub = DB::table('SalesItem')
            ->join('SalesTransaction', 'SalesTransaction.SalesTransactionID', '=', 'SalesItem.SalesTransactionID')
            ->whereColumn('SalesItem.ProductID', 'Product.ProductID')
            ->where('SalesTransaction.SalesTransactionDate', '>=', now()->subDays(self::SALES_WINDOW_DAYS))
            ->selectRaw('COALESCE(SUM(SalesItem.Quantity), 0)');

        $velocitySql = "({$velocitySub->toSql()})";
        $velocityBindings = $velocitySub->getBindings();

        $stockLevelSql = "COALESCE((SELECT Quantity FROM Inventory WHERE Inventory.ProductID = Product.ProductID LIMIT 1), 0)";
        $thresholdSql = "COALESCE((SELECT ReorderThreshold FROM Inventory WHERE Inventory.ProductID = Product.ProductID LIMIT 1), 50)";

        match ($status) {
            'out-of-stock' => $query->whereRaw("{$stockLevelSql} <= 0"),
            'low-stock' => $query
                ->whereRaw("{$stockLevelSql} > 0")
                ->whereRaw("{$stockLevelSql} <= {$thresholdSql}"),
            'fast-moving' => $query
                ->whereRaw("{$stockLevelSql} > {$thresholdSql}")
                ->whereRaw("{$velocitySql} >= ?", array_merge($velocityBindings, [self::FAST_MOVING_THRESHOLD])),
            'slow-moving' => $query
                ->whereRaw("{$stockLevelSql} > {$thresholdSql}")
                ->whereRaw("{$velocitySql} < ?", array_merge($velocityBindings, [self::SLOW_MOVING_THRESHOLD])),
            'available' => $query
                ->whereRaw("{$stockLevelSql} > {$thresholdSql}")
                ->whereRaw("{$velocitySql} >= ?", array_merge($velocityBindings, [self::SLOW_MOVING_THRESHOLD]))
                ->whereRaw("{$velocitySql} < ?", array_merge($velocityBindings, [self::FAST_MOVING_THRESHOLD])),
            default => null,
        };
    }

    /**
     * Compute the live count for each status pill. Runs on every request
     * (including AJAX pagination/search and the background poll) so the
     * pill numbers reflect current stock, not just the initial page load.
     */
    private function buildStatusCounts(): array
    {
        $base = Product::query();

        $count = function (string $status) {
            $clone = Product::query();
            $this->applyStatusFilter($clone, $status);
            return $clone->count();
        };

        return [
            'all' => (clone $base)->count(),
            'available' => $count('available'),
            'low-stock' => $count('low-stock'),
            'fast-moving' => $count('fast-moving'),
            'slow-moving' => $count('slow-moving'),
            'out-of-stock' => $count('out-of-stock'),
        ];
    }
}
