<?php

namespace App\Http\Controllers\Admin;

use App\Casts\Quantity;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\ProductSerial;
use App\Models\ProductSupplier;
use App\Models\User;
use App\Notifications\ProductUpdated;
use App\Services\ProductImage;
use App\Services\ReorderPoint;
use App\Services\UnitOfMeasure;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductController extends Controller
{
    public const MAX_PROFIT_MARGIN = 95;

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
            ->orderByDesc('ProductID')
            ->paginate(15)
            ->withQueryString();

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
            'brands' => Brand::orderBy('BrandName')->get(),
        ]);
    }

    public static function resolveStockStatus(int|float|null $quantity, ?int $reorderThreshold = null): array
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

    public function show(Request $request, Product $product)
    {
        $product->load(['brand', 'category', 'inventory', 'suppliers.supplier', 'serials' => fn ($query) => $query->with('salesTransaction')->orderByDesc('ProductSerialID')]);

        $quantity = $product->inventory?->Quantity ?? 0;
        $threshold = $product->inventory?->ReorderThreshold ?? 50;
        $cost = (float) ($product->CostPrice ?? 0);
        $price = (float) $product->Price;
        $profit = $price - $cost;
        $margin = $price > 0 ? ($profit / $price) * 100 : 0;

        $status = self::resolveStockStatus($quantity, $threshold);

        $viewData = [
            'product' => $product,
            'profit' => $profit,
            'margin' => $margin,
            'status' => $status,
        ];

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'html' => view('admin.products.partials.product-details', $viewData)->render(),
                'productName' => $product->ProductName,
            ]);
        }

        return view('admin.products.show', $viewData);
    }

    public function serialStickers(Request $request, Product $product)
    {
        $data = $request->validate([
            'serials' => ['nullable', 'array'],
            'serials.*' => ['integer'],
        ]);

        $serials = $product->serials()
            ->where('Status', ProductSerial::STATUS_IN_STOCK)
            ->when(! empty($data['serials']), fn ($query) => $query->whereIn('ProductSerialID', $data['serials']))
            ->orderBy('ProductSerialID')
            ->get();

        if ($serials->isEmpty()) {
            return back()->with('error', 'There are no in-stock serial numbers to print for this product.');
        }

        $pages = $serials->chunk(40);
        $fileName = 'Serial-Stickers-'.preg_replace('/[^A-Za-z0-9]+/', '-', $product->ProductName).'.pdf';

        return Pdf::loadView('admin.products.serial-stickers-pdf', ['product' => $product, 'pages' => $pages])
            ->setPaper('a4')
            ->stream($fileName);
    }

    public function create()
    {
        return view('admin.products.create', [
            'categories' => Category::orderBy('CategoryName')->get(),
            'brands' => Brand::orderBy('BrandName')->get(),
        ]);
    }

    public function checkName(Request $request)
    {
        $name = trim((string) $request->input('ProductName', ''));
        $model = trim((string) $request->input('Model', ''));
        $barcode = trim((string) $request->input('Barcode', ''));
        $excludeId = $request->input('exclude_id');

        $normalize = function (string $value): string {
            return preg_replace('/\s+/', ' ', strtolower($value));
        };

        $candidates = Product::when($excludeId, function ($query, $excludeId) {
            $query->where('ProductID', '!=', $excludeId);
        })->get();

        $nameTaken = false;
        if ($name !== '') {
            $normalized = $normalize($name);
            $nameTaken = $candidates->contains(function ($existing) use ($normalize, $normalized) {
                return $normalize((string) ($existing->ProductName ?? '')) === $normalized;
            });
        }

        $modelTaken = false;
        if ($model !== '') {
            $normalized = $normalize($model);
            $modelTaken = $candidates->contains(function ($existing) use ($normalize, $normalized) {
                return $normalize((string) ($existing->Model ?? '')) === $normalized;
            });
        }

        $barcodeTaken = $barcode !== '' && $candidates->contains(function ($existing) use ($barcode) {
            return (string) ($existing->Barcode ?? '') === $barcode;
        });

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
            'Barcode' => ['required', 'string', 'max:100', 'unique:Product,Barcode'],
            ...self::priceRules(),
            'BrandID' => ['nullable', 'integer', 'exists:Brand,BrandID'],
            'CategoryID' => ['required', 'integer', 'exists:Category,CategoryID'],
            'NonReturnable' => ['nullable', 'boolean'],
            'Image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'Suppliers' => ['nullable', 'array'],
            'Suppliers.*.SupplierID' => ['required', 'integer', 'exists:Supplier,SupplierID'],
            'Suppliers.*.CostPrice' => ['nullable', 'numeric', 'min:0', 'max:'.Product::MAX_PRICE],
            ...self::unitOfMeasureRules(),
        ], [
            'Barcode.required' => 'Barcode is required. Please scan or type a barcode.',
            'Barcode.unique' => 'This barcode is already assigned to another product.',
            ...self::priceMessages(),
            ...self::unitOfMeasureMessages(),
        ]);
        $units = self::validatedUnitsOfMeasure($data, Category::find($data['CategoryID']));
        $data['ProfitMargin'] = self::profitMarginFrom($data);

        $brandId = $data['BrandID'] ?? null;
        if ($brandId !== null && ! Brand::where('BrandID', $brandId)->where('CategoryID', $data['CategoryID'])->exists()) {
            throw ValidationException::withMessages([
                'BrandID' => 'The selected brand does not belong to the selected category.',
            ]);
        }

        if (! Category::whereKey($data['CategoryID'])->value('IsActive')) {
            throw ValidationException::withMessages([
                'CategoryID' => 'This category is deactivated. Choose an active category.',
            ]);
        }

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

            throw ValidationException::withMessages($messages);
        }

        $status = self::resolveStockStatus(0, ReorderPoint::MINIMUM)['label'];

        $imagePath = $request->hasFile('Image') ? ProductImage::store($request->file('Image')) : null;

        try {
            $product = $this->createProduct([...$data, ...$units], $brandId, $status, $imagePath);
        } catch (Throwable $e) {
            ProductImage::delete($imagePath);

            throw $e;
        }

        ActivityLog::record('product.created', "Added product \"{$product->ProductName}\"");

        try {
            Notification::send(User::admins(), new ProductUpdated($product, 'Created'));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch ProductUpdated notification', [
                'product_id' => $product->ProductID,
                'exception' => $e->getMessage(),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Product added successfully.',
                'productId' => $product->ProductID,
            ], 201);
        }

        return redirect()->route('admin.products.index')->with('status', 'Product added successfully.');
    }

    private static function priceRules(): array
    {
        return [
            'CostPrice' => ['required', 'numeric', 'min:0.01', 'max:'.Product::MAX_PRICE],
            'ProfitMargin' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_PROFIT_MARGIN],
            'Price' => ['nullable', 'numeric', 'min:0.01', 'max:'.Product::MAX_PRICE],
        ];
    }

    private static function priceMessages(): array
    {
        return [
            'CostPrice.max' => 'The Cost Price can only have up to 5 digits (₱99,999.99).',
            'Price.max' => 'The Selling Price can only have up to 5 digits (₱99,999.99).',
            'ProfitMargin.max' => 'The Profit Margin can be at most '.self::MAX_PROFIT_MARGIN.'%.',
            'ProfitMargin.min' => 'The Profit Margin cannot be negative.',
        ];
    }

    private static function profitMarginFrom(array $data): float
    {
        $margin = isset($data['ProfitMargin'])
            ? round((float) $data['ProfitMargin'], 4)
            : Product::marginFor((float) ($data['Price'] ?? 0), (float) $data['CostPrice']);

        if (Product::computeSellingPrice((float) $data['CostPrice'], $margin) > Product::MAX_PRICE) {
            throw ValidationException::withMessages(['ProfitMargin' => 'At this Profit Margin the Selling Price would be over 5 digits (₱99,999.99). Enter a lower Profit Margin.']);
        }

        return $margin;
    }

    private static function unitOfMeasureRules(): array
    {
        return [
            'UnitOfMeasure' => ['sometimes', 'required', Rule::in([...UnitOfMeasure::UNITS, UnitOfMeasure::METER_AND_ROLL])],
            'PurchaseUnit' => ['nullable', Rule::in(UnitOfMeasure::UNITS), 'different:UnitOfMeasure'],
            'QuantityPerPurchaseUnit' => ['nullable', 'numeric', 'gt:0', 'max:9999999', 'decimal:0,2'],
            'SellsByPurchaseUnit' => ['nullable', 'boolean'],
            'AllowsFractionalQuantity' => ['nullable', 'boolean'],
        ];
    }

    private static function unitOfMeasureMessages(): array
    {
        return [
            'UnitOfMeasure.required' => 'Choose the unit stock is counted in.',
            'UnitOfMeasure.in' => 'Choose the unit stock is counted in.',
            'PurchaseUnit.different' => 'The purchase unit must be different from the inventory base unit.',
            'QuantityPerPurchaseUnit.gt' => 'The quantity per purchase unit must be greater than 0.',
            'QuantityPerPurchaseUnit.decimal' => 'The quantity per purchase unit can have up to 2 decimals.',
        ];
    }

    private static function validatedUnitsOfMeasure(array $data, ?Category $category, ?Product $product = null): array
    {
        if (! array_key_exists('UnitOfMeasure', $data)) {
            return [
                'UnitOfMeasure' => $product?->baseUnit() ?? UnitOfMeasure::DEFAULT,
                'PurchaseUnit' => $product?->PurchaseUnit,
                'QuantityPerPurchaseUnit' => $product?->QuantityPerPurchaseUnit ?? 1,
                'SellsByPurchaseUnit' => (bool) ($product?->SellsByPurchaseUnit ?? false),
                'AllowsFractionalQuantity' => (bool) ($product?->AllowsFractionalQuantity ?? false),
            ];
        }

        $baseUnit = $data['UnitOfMeasure'];
        $purchaseUnit = filled($data['PurchaseUnit'] ?? null) ? $data['PurchaseUnit'] : null;
        if ($baseUnit === UnitOfMeasure::METER_AND_ROLL) {
            $baseUnit = 'meter';
            $purchaseUnit = 'roll';
        }
        $allowsFractions = array_key_exists('AllowsFractionalQuantity', $data)
            ? (bool) $data['AllowsFractionalQuantity']
            : (bool) ($product?->AllowsFractionalQuantity ?? false);
        $keptSize = $product && $product->PurchaseUnit === $purchaseUnit && $product->baseUnit() === $baseUnit ? (float) $product->QuantityPerPurchaseUnit : 1;
        $perPurchaseUnit = ! $purchaseUnit ? 1 : (filled($data['QuantityPerPurchaseUnit'] ?? null) ? (float) $data['QuantityPerPurchaseUnit'] : $keptSize);
        $sellsByPurchaseUnit = $purchaseUnit && ! empty($data['SellsByPurchaseUnit']);
        $errors = [];

        $allowedUnits = UnitOfMeasure::forCategory($category?->CategoryName);
        $unitNames = implode(' or ', array_map('ucfirst', $allowedUnits));
        $kind = $allowedUnits === UnitOfMeasure::WIRE_UNITS ? 'Wire products' : 'Products outside WIRE';
        if (! in_array($baseUnit, $allowedUnits, true)) {
            $errors['UnitOfMeasure'] = "{$kind} use {$unitNames}.";
        }
        if ($purchaseUnit && ! in_array($purchaseUnit, $allowedUnits, true)) {
            $errors['PurchaseUnit'] = "{$kind} use {$unitNames}.";
        }
        if ($purchaseUnit && ! $allowsFractions && floor($perPurchaseUnit) != $perPurchaseUnit) {
            $errors['QuantityPerPurchaseUnit'] = "Use a whole number of {$baseUnit}s.";
        }
        if ($category?->TracksSerialNumbers) {
            if ($baseUnit !== UnitOfMeasure::DEFAULT || $allowsFractions) {
                $errors['UnitOfMeasure'] = 'Products with serial numbers are counted and sold by the whole piece.';
            } elseif ($sellsByPurchaseUnit) {
                $errors['SellsByPurchaseUnit'] = 'Products with serial numbers are sold by the piece (each piece is scanned).';
            }
        }
        $stock = (float) ($product?->inventory?->Quantity ?? 0);
        if ($product && $product->baseUnit() !== $baseUnit && $stock != 0) {
            $errors['UnitOfMeasure'] = "This product still has {$product->formatQuantity($stock)} in stock. Its inventory unit can only change while its stock is 0: record that stock again in the new unit with a Stock Adjustment.";
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'UnitOfMeasure' => $baseUnit,
            'PurchaseUnit' => $purchaseUnit,
            'QuantityPerPurchaseUnit' => Quantity::normalize($perPurchaseUnit),
            'SellsByPurchaseUnit' => (bool) $sellsByPurchaseUnit,
            'AllowsFractionalQuantity' => $allowsFractions,
        ];
    }

    private function createProduct(array $data, ?int $brandId, string $status, ?string $imagePath): Product
    {
        return DB::transaction(function () use ($data, $brandId, $status, $imagePath) {
            $product = Product::create([
                'ProductName' => $data['ProductName'],
                'Model' => $data['Model'],
                'Description' => $data['Description'] ?? null,
                'ImagePath' => $imagePath,
                'Barcode' => $data['Barcode'] ?? null,
                'CostPrice' => $data['CostPrice'],
                'ProfitMargin' => $data['ProfitMargin'],
                'Price' => Product::computeSellingPrice((float) $data['CostPrice'], $data['ProfitMargin']),
                'BrandID' => $brandId,
                'CategoryID' => $data['CategoryID'],
                'IsReturnable' => empty($data['NonReturnable']),
                'UnitOfMeasure' => $data['UnitOfMeasure'],
                'PurchaseUnit' => $data['PurchaseUnit'],
                'QuantityPerPurchaseUnit' => $data['QuantityPerPurchaseUnit'],
                'SellsByPurchaseUnit' => $data['SellsByPurchaseUnit'],
                'AllowsFractionalQuantity' => $data['AllowsFractionalQuantity'],
            ]);

            $product->update(['SKU' => Product::generateSku($product->ProductID)]);

            Inventory::create([
                'ProductID' => $product->ProductID,
                'Quantity' => 0,
                'ReorderThreshold' => ReorderPoint::MINIMUM,
                'Status' => $status,
            ]);

            $seenSuppliers = [];
            foreach ($data['Suppliers'] ?? [] as $supplierEntry) {
                $supplierId = $supplierEntry['SupplierID'];
                if (isset($seenSuppliers[$supplierId])) {
                    continue;
                }
                $seenSuppliers[$supplierId] = true;

                $supplierCost = $supplierEntry['CostPrice'] ?? null;

                $productSupplier = ProductSupplier::create([
                    'ProductID' => $product->ProductID,
                    'SupplierID' => $supplierId,
                    'CostPrice' => $supplierCost,
                ]);

                if ($supplierCost !== null) {
                    ProductCostHistory::log($product, $productSupplier->supplier, null, (float) $supplierCost, ProductCostHistory::SOURCE_SUPPLIER_PIVOT_UPDATE);
                }
            }

            return $product;
        });
    }

    public function edit(Request $request, Product $product)
    {
        $product->load('inventory');
        $categories = Category::orderBy('CategoryName')->get();
        $brands = Brand::orderBy('BrandName')->get();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'html' => view('admin.products.partials.product-form-fields', [
                    'categories' => $categories,
                    'brands' => $brands,
                    'product' => $product,
                ])->render(),
            ]);
        }

        return view('admin.products.edit', [
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'ProductName' => ['required', 'string', 'max:100'],
            'Model' => ['required', 'string', 'max:100'],
            'Description' => ['nullable', 'string', 'max:500'],
            'Barcode' => ['required', 'string', 'max:100', 'unique:Product,Barcode,'.$product->ProductID.',ProductID'],
            ...self::priceRules(),
            'BrandID' => ['nullable', 'integer', 'exists:Brand,BrandID'],
            'CategoryID' => ['required', 'integer', 'exists:Category,CategoryID'],
            'NonReturnable' => ['nullable', 'boolean'],
            'Image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'RemoveImage' => ['nullable', 'boolean'],
            ...self::unitOfMeasureRules(),
        ], [
            'Barcode.required' => 'Barcode is required. Please scan or type a barcode.',
            'Barcode.unique' => 'This barcode is already assigned to another product.',
            ...self::priceMessages(),
            ...self::unitOfMeasureMessages(),
        ]);
        $units = self::validatedUnitsOfMeasure($data, Category::find($data['CategoryID']), $product);
        $data['ProfitMargin'] = self::profitMarginFrom($data);

        $brandId = $data['BrandID'] ?? null;
        if ($brandId !== null && ! Brand::where('BrandID', $brandId)->where('CategoryID', $data['CategoryID'])->exists()) {
            throw ValidationException::withMessages([
                'BrandID' => 'The selected brand does not belong to the selected category.',
            ]);
        }

        if ((int) $data['CategoryID'] !== (int) $product->CategoryID && ! Category::whereKey($data['CategoryID'])->value('IsActive')) {
            throw ValidationException::withMessages([
                'CategoryID' => 'This category is deactivated. Choose an active category.',
            ]);
        }

        $normalize = function (string $value): string {
            return preg_replace('/\s+/', ' ', strtolower($value));
        };

        $others = Product::where('ProductID', '!=', $product->ProductID)->get();

        $modelTaken = $others->contains(function ($existing) use ($data, $normalize) {
            return $normalize((string) ($existing->Model ?? '')) === $normalize((string) $data['Model']);
        });

        if ($modelTaken) {
            throw ValidationException::withMessages([
                'Model' => ['A product with this model number already exists. Duplicate model numbers are not allowed.'],
            ]);
        }

        $oldCostPrice = $product->CostPrice;

        $oldImagePath = $product->ImagePath;
        $imagePath = $oldImagePath;
        if ($request->hasFile('Image')) {
            $imagePath = ProductImage::store($request->file('Image'));
        } elseif ($request->boolean('RemoveImage')) {
            $imagePath = null;
        }

        $product->update([
            'ImagePath' => $imagePath,
            'ProductName' => $data['ProductName'],
            'Model' => $data['Model'],
            'Description' => $data['Description'] ?? null,
            'Barcode' => $data['Barcode'] ?? null,
            'CostPrice' => $data['CostPrice'],
            'ProfitMargin' => $data['ProfitMargin'],
            'BrandID' => $brandId,
            'CategoryID' => $data['CategoryID'],
            ...(array_key_exists('NonReturnable', $data) ? ['IsReturnable' => ! $data['NonReturnable']] : []),
            ...$units,
        ]);

        if ($oldImagePath !== $imagePath) {
            ProductImage::delete($oldImagePath);
        }

        ProductCostHistory::log($product, null, $oldCostPrice, (float) $data['CostPrice'], ProductCostHistory::SOURCE_PRODUCT_UPDATE);

        $product->repriceStockBatches();

        DB::transaction(function () use ($product) {
            $inventory = Inventory::where('ProductID', $product->ProductID)->lockForUpdate()->first()
                ?? Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => 0, 'Status' => 'Out of Stock']);
            $inventory->ReorderThreshold = ReorderPoint::forProduct($product->ProductID)['reorder_point'];
            $inventory->Status = self::resolveStockStatus($inventory->Quantity, $inventory->ReorderThreshold)['label'];
            $inventory->save();
        });

        ActivityLog::record('product.updated', "Updated product \"{$product->ProductName}\"");

        try {
            Notification::send(User::admins(), new ProductUpdated($product, 'Updated'));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch ProductUpdated notification', [
                'product_id' => $product->ProductID,
                'exception' => $e->getMessage(),
            ]);
        }

        return redirect()->route('admin.products.index')->with('status', 'Product updated successfully.');
    }
}
