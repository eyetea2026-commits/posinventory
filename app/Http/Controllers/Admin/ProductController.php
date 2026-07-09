<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Inventory;
use Illuminate\Http\Request;

class ProductController extends Controller
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
        $search = $request->query('search');
        $categoryId = $request->query('category_id');

        $products = Product::with(['brand', 'category', 'inventory'])
            ->when($search, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('ProductName', 'like', "%{$search}%")
                        ->orWhere('Model', 'like', "%{$search}%")
                        ->orWhere('SKU', 'like', "%{$search}%")
                        ->orWhere('Barcode', 'like', "%{$search}%")
                        ->orWhereHas('category', function ($category) use ($search) {
                            $category->where('CategoryName', 'like', "%{$search}%");
                        });
                });
            })
            ->when($categoryId, function ($query, $categoryId) {
                $query->where('CategoryID', $categoryId);
            })
            ->orderBy('ProductName')
            ->paginate(15)
            ->withQueryString();

        // AJAX request — return just the table rows + pagination as partials
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'rows' => view('admin.products.partials.rows', ['products' => $products])->render(),
                'pagination' => view('admin.products.partials.pagination', ['products' => $products])->render(),
            ]);
        }

        return view('admin.products.index', [
            'products' => $products,
            'search' => $search,
            'categoryId' => $categoryId,
            'categories' => Category::orderBy('CategoryName')->get(),
        ]);
    }

    /**
     * Resolve stock status label and Bootstrap badge class based on
     * current quantity and reorder threshold.
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

    public function show(Product $product)
    {
        $product->load(['brand', 'category', 'inventory']);

        $quantity = $product->inventory?->Quantity ?? 0;
        $threshold = $product->inventory?->ReorderThreshold ?? 50;
        $cost = (float) ($product->CostPrice ?? 0);
        $price = (float) $product->Price;
        $profit = $price - $cost;
        $margin = $price > 0 ? ($profit / $price) * 100 : 0;

        $status = self::resolveStockStatus($quantity, $threshold);

        return view('admin.products.show', [
            'product' => $product,
            'profit' => $profit,
            'margin' => $margin,
            'status' => $status,
        ]);
    }

    public function create()
    {
        return view('admin.products.create', [
            'categories' => Category::orderBy('CategoryName')->get(),
        ]);
    }

    /**
     * AJAX endpoint: check whether a product name, model number, and/or
     * barcode already exists. Returns { name: bool, model: bool, barcode: bool }.
     */
    public function checkName(Request $request)
    {
        $name = trim((string) $request->input('ProductName', ''));
        $model = trim((string) $request->input('Model', ''));
        $barcode = trim((string) $request->input('Barcode', ''));

        $normalize = function (string $value): string {
            return preg_replace('/\s+/', ' ', strtolower($value));
        };

        $nameTaken = false;
        if ($name !== '') {
            $normalized = $normalize($name);
            $nameTaken = Product::get()->contains(function ($existing) use ($normalize, $normalized) {
                return $normalize((string) ($existing->ProductName ?? '')) === $normalized;
            });
        }

        $modelTaken = false;
        if ($model !== '') {
            $normalized = $normalize($model);
            $modelTaken = Product::get()->contains(function ($existing) use ($normalize, $normalized) {
                return $normalize((string) ($existing->Model ?? '')) === $normalized;
            });
        }

        // Barcode is an exact match (not fuzzy-normalized like name/model),
        // so this can go straight through the unique index instead of
        // loading every product into memory.
        $barcodeTaken = $barcode !== '' && Product::where('Barcode', $barcode)->exists();

        return response()->json([
            'name' => $nameTaken,
            'model' => $modelTaken,
            'barcode' => $barcodeTaken,
            'name_value' => $name,
            'model_value' => $model,
            'barcode_value' => $barcode,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'ProductName' => ['required', 'string', 'max:100'],
            'Model' => ['required', 'string', 'max:100'],
            'Description' => ['nullable', 'string', 'max:500'],
            'SKU' => ['nullable', 'string', 'max:100', 'unique:Product,SKU'],
            'Barcode' => ['required', 'string', 'max:100', 'unique:Product,Barcode'],
            'CostPrice' => ['nullable', 'numeric', 'min:0'],
            'Price' => ['required', 'numeric', 'min:0'],
            'BrandID' => ['nullable', 'integer', 'exists:Brand,BrandID'],
            'CategoryID' => ['required', 'integer', 'exists:Category,CategoryID'],
            'ReorderThreshold' => ['nullable', 'integer', 'min:0'],
        ], [
            'SKU.unique' => 'This SKU is already in use.',
            'Barcode.required' => 'Barcode is required. Please scan or type a barcode.',
            'Barcode.unique' => 'This barcode is already assigned to another product.',
        ]);

        // Block duplicate Product Name and Model (case-insensitive, whitespace-normalized).
        // SKU and Barcode are already covered by the `unique` validation rules above.
        // This is the authoritative server-side check — anything the client-side
        // duplicate-name AJAX misses is caught here before a product is created.
        $normalize = function (string $value): string {
            return preg_replace('/\s+/', ' ', strtolower($value));
        };

        $nameTaken = Product::get()->contains(function ($existing) use ($data, $normalize) {
            return $normalize((string) ($existing->ProductName ?? '')) === $normalize((string) $data['ProductName']);
        });

        $modelTaken = Product::get()->contains(function ($existing) use ($data, $normalize) {
            return $normalize((string) ($existing->Model ?? '')) === $normalize((string) $data['Model']);
        });

        if ($nameTaken || $modelTaken) {
            $messages = [];
            if ($nameTaken) {
                $messages['ProductName'] = 'A product with this name already exists. Duplicate product names are not allowed.';
            }
            if ($modelTaken) {
                $messages['Model'] = 'A product with this model number already exists. Duplicate model numbers are not allowed.';
            }
            return back()
                ->withErrors($messages)
                ->withInput();
        }

        // New products always start with zero stock — quantity is only ever
        // added afterward through Stock Receiving.
        $status = self::resolveStockStatus(
            0,
            (int) ($data['ReorderThreshold'] ?? 50)
        )['label'];

        $product = Product::create([
            'ProductName' => $data['ProductName'],
            'Model' => $data['Model'],
            'Description' => $data['Description'] ?? null,
            'SKU' => $data['SKU'] ?? null,
            'Barcode' => $data['Barcode'] ?? null,
            'CostPrice' => $data['CostPrice'] ?? 0,
            'Price' => $data['Price'],
            'BrandID' => $data['BrandID'] ?? null,
            'CategoryID' => $data['CategoryID'],
        ]);

        Inventory::create([
            'ProductID' => $product->ProductID,
            'Quantity' => 0,
            'ReorderThreshold' => $data['ReorderThreshold'] ?? 50,
            'Status' => $status,
        ]);

        ActivityLog::record('product.created', "Added product \"{$product->ProductName}\"");

        return redirect()->route('admin.products.index')->with('status', 'Product added successfully.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.edit', [
            'product' => $product->load('inventory'),
            'categories' => Category::orderBy('CategoryName')->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'ProductName' => ['required', 'string', 'max:100'],
            'Model' => ['required', 'string', 'max:100'],
            'Description' => ['nullable', 'string', 'max:500'],
            'SKU' => ['nullable', 'string', 'max:100', 'unique:Product,SKU,' . $product->ProductID . ',ProductID'],
            'Barcode' => ['nullable', 'string', 'max:100', 'unique:Product,Barcode,' . $product->ProductID . ',ProductID'],
            'CostPrice' => ['nullable', 'numeric', 'min:0'],
            'Price' => ['required', 'numeric', 'min:0'],
            'BrandID' => ['nullable', 'integer', 'exists:Brand,BrandID'],
            'CategoryID' => ['required', 'integer', 'exists:Category,CategoryID'],
            'Quantity' => ['required', 'integer', 'min:0'],
            'ReorderThreshold' => ['nullable', 'integer', 'min:0'],
        ], [
            'SKU.unique' => 'This SKU is already in use.',
            'Barcode.unique' => 'This barcode is already in use.',
        ]);

        // Determine status based on quantity and reorder threshold
        $status = self::resolveStockStatus(
            (int) $data['Quantity'],
            (int) ($data['ReorderThreshold'] ?? 50)
        )['label'];

        $product->update([
            'ProductName' => $data['ProductName'],
            'Model' => $data['Model'],
            'Description' => $data['Description'] ?? null,
            'SKU' => $data['SKU'] ?? null,
            'Barcode' => $data['Barcode'] ?? null,
            'CostPrice' => $data['CostPrice'] ?? 0,
            'Price' => $data['Price'],
            'BrandID' => $data['BrandID'] ?? null,
            'CategoryID' => $data['CategoryID'],
        ]);

        $product->inventory()->updateOrCreate(
            ['ProductID' => $product->ProductID],
            [
                'Quantity' => $data['Quantity'],
                'ReorderThreshold' => $data['ReorderThreshold'] ?? 50,
                'Status' => $status
            ]
        );

        ActivityLog::record('product.updated', "Updated product \"{$product->ProductName}\"");

        return redirect()->route('admin.products.index')->with('status', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        // Check if product has related records. All of these tables have an
        // ON DELETE CASCADE foreign key to Product, so skipping any of them
        // here would let a "delete" silently wipe that history instead of
        // blocking it with a friendly message.
        if ($product->salesItems()->count() > 0
            || $product->stockReceivings()->count() > 0
            || $product->stockAdjustments()->count() > 0
            || $product->purchaseItems()->count() > 0
            || $product->damagedProducts()->count() > 0
            || $product->salesReturns()->count() > 0) {
            return redirect()->route('admin.products.index')->with('error', 'Cannot delete product with existing sales, stock, purchase order, damage, or return records.');
        }

        // Delete inventory first
        if ($product->inventory) {
            $product->inventory->delete();
        }

        $productName = $product->ProductName;
        $product->delete();

        ActivityLog::record('product.deleted', "Deleted product \"{$productName}\"");

        return redirect()->route('admin.products.index')->with('status', 'Product deleted successfully.');
    }
}
