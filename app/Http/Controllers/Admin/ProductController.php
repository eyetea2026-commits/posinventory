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
use App\Services\ActionNotifier;
use App\Services\ProductImage;
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
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : null;

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
            ->when($status, fn ($query, $status) => $query->where('IsActive', $status === 'active'))
            // Active products first (newest first); deactivated ones go last.
            ->orderByDesc('IsActive')
            ->orderByDesc('ProductID')
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
            'status' => $status,
            'categories' => Category::orderBy('CategoryName')->get(),
            'brands' => Brand::orderBy('BrandName')->get(),
        ]);
    }

    /**
     * Resolve stock status label and Bootstrap badge class based on
     * current quantity and reorder threshold.
     */
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
        $product->load(['brand', 'category', 'inventory', 'serials' => fn ($query) => $query->with('salesTransaction')->orderByDesc('ProductSerialID')]);

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

        // View Details modal: return just the rendered detail rows instead
        // of a full page, so the modal can inject it without navigating.
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'html' => view('admin.products.partials.product-details', $viewData)->render(),
                'productName' => $product->ProductName,
            ]);
        }

        return view('admin.products.show', $viewData);
    }

    /**
     * Serial number stickers for A4 sticker paper, 4 x 10 per page (52.5 x
     * 29.7 mm each): the ticked serials, or every piece in stock.
     */
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

    /**
     * AJAX endpoint: check whether a product name, model number, and/or
     * barcode already exists. Returns { name: bool, model: bool, barcode: bool }.
     */
    public function checkName(Request $request)
    {
        $name = trim((string) $request->input('ProductName', ''));
        $model = trim((string) $request->input('Model', ''));
        $barcode = trim((string) $request->input('Barcode', ''));
        // When editing, the product's own unchanged name/model/barcode must
        // not be flagged as a duplicate of itself.
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
            'CostPrice' => ['required', 'numeric', 'min:0.01'],
            'Price' => ['nullable', 'numeric', 'min:0.01'],
            'BrandID' => ['nullable', 'integer', 'exists:Brand,BrandID'],
            'CategoryID' => ['required', 'integer', 'exists:Category,CategoryID'],
            'ReorderThreshold' => ['nullable', 'integer', 'min:0'],
            // Ticked: "Not returnable / refundable" (Product.IsReturnable = false).
            'NonReturnable' => ['nullable', 'boolean'],
            // Any size up to 5 MB is accepted; it's stored shrunk (ProductImage).
            'Image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            // A supplier can't be linked before the Product exists, so the
            // Add Product form's "Suppliers for this Product" panel just
            // collects the chosen suppliers client-side and submits them
            // alongside the product — linked here in the same request
            // instead of the immediate per-supplier AJAX calls the Edit form
            // uses (ProductSupplierController::store()). No per-supplier
            // cost is asked for: a link without one uses the product's own
            // Cost Price.
            'Suppliers' => ['nullable', 'array'],
            'Suppliers.*.SupplierID' => ['required', 'integer', 'exists:Supplier,SupplierID'],
            'Suppliers.*.CostPrice' => ['nullable', 'numeric', 'min:0'],
            ...self::unitOfMeasureRules(),
        ], [
            'Barcode.required' => 'Barcode is required. Please scan or type a barcode.',
            'Barcode.unique' => 'This barcode is already assigned to another product.',
            ...self::unitOfMeasureMessages(),
        ]);
        $units = self::validatedUnitsOfMeasure($data, Category::find($data['CategoryID']));

        // Brand is managed via Brand Management (Category module) and is
        // always scoped to one Category — the Add Product form's Brand
        // dropdown only ever offers brands belonging to the selected
        // Category, but a crafted request could still send a mismatched
        // pair, so re-verify server-side rather than trusting the client.
        $brandId = $data['BrandID'] ?? null;
        if ($brandId !== null && ! Brand::where('BrandID', $brandId)->where('CategoryID', $data['CategoryID'])->exists()) {
            throw ValidationException::withMessages([
                'BrandID' => 'The selected brand does not belong to the selected category.',
            ]);
        }

        // A deactivated category is no longer offered for new products.
        if (! Category::whereKey($data['CategoryID'])->value('IsActive')) {
            throw ValidationException::withMessages([
                'CategoryID' => 'This category is deactivated. Choose an active category.',
            ]);
        }

        // Block duplicate Product Name and Model (case-insensitive, whitespace-normalized).
        // Barcode is already covered by the `unique` validation rule above.
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

            // A 422 for the Add Product modal's AJAX submit; a normal form
            // post is still redirected back with these errors and its input.
            throw ValidationException::withMessages($messages);
        }

        // New products always start with zero stock — quantity is only ever
        // added afterward through Stock Receiving.
        $status = self::resolveStockStatus(
            0,
            (int) ($data['ReorderThreshold'] ?? 50)
        )['label'];

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

        // The Add Product modal needs a plain "created" answer — following the
        // redirect would hand it the index's AJAX rows instead.
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Product added successfully.',
                'productId' => $product->ProductID,
            ], 201);
        }

        return redirect()->route('admin.products.index')->with('status', 'Product added successfully.');
    }

    /**
     * Unit of Measurement fields of the Add/Edit Product form.
     *
     * @return array<string, array<int, mixed>>
     */
    private static function unitOfMeasureRules(): array
    {
        return [
            // Left out entirely (a request without the Unit of Measurement
            // section): the product keeps its units, a new one is a piece.
            'UnitOfMeasure' => ['sometimes', 'required', Rule::in([...UnitOfMeasure::UNITS, UnitOfMeasure::METER_AND_ROLL])],
            'PurchaseUnit' => ['nullable', Rule::in(UnitOfMeasure::UNITS), 'different:UnitOfMeasure'],
            // Normally not sent: the roll/box size is entered at Stock
            // Receiving (the last one received is kept on the product).
            'QuantityPerPurchaseUnit' => ['nullable', 'numeric', 'gt:0', 'max:9999999', 'decimal:0,2'],
            'SellsByPurchaseUnit' => ['nullable', 'boolean'],
            'AllowsFractionalQuantity' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
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

    /**
     * The checked unit settings to save: units fit the category, serial-
     * numbered products stay whole pieces, and a product's base unit can't
     * change while it has stock counted in the old one.
     *
     * @param  array<string, mixed>  $data
     * @return array{UnitOfMeasure: string, PurchaseUnit: ?string, QuantityPerPurchaseUnit: int|float, SellsByPurchaseUnit: bool, AllowsFractionalQuantity: bool}
     */
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
        // "Meter & Roll": counted and sold by the meter, bought by the roll.
        if ($baseUnit === UnitOfMeasure::METER_AND_ROLL) {
            $baseUnit = 'meter';
            $purchaseUnit = 'roll';
        }
        $allowsFractions = (bool) ($data['AllowsFractionalQuantity'] ?? false);
        // The roll/box size comes from Stock Receiving: keep the last one
        // received while the purchase unit stays the same (1 = not known yet).
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
            $errors['QuantityPerPurchaseUnit'] = "Use a whole number of {$baseUnit}s, or allow decimal quantities.";
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
            // Its price is worked out (price per meter x meters per roll).
            'SellsByPurchaseUnit' => (bool) $sellsByPurchaseUnit,
            'AllowsFractionalQuantity' => $allowsFractions,
        ];
    }

    /**
     * Inserts the product, its SKU, its Inventory row and its supplier links
     * in one transaction.
     *
     * @param  array<string, mixed>  $data
     */
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
                'Price' => isset($data['Price']) ? (float) $data['Price'] : Product::computeSellingPrice((float) $data['CostPrice']),
                'BrandID' => $brandId,
                'CategoryID' => $data['CategoryID'],
                'IsReturnable' => empty($data['NonReturnable']),
                'UnitOfMeasure' => $data['UnitOfMeasure'],
                'PurchaseUnit' => $data['PurchaseUnit'],
                'QuantityPerPurchaseUnit' => $data['QuantityPerPurchaseUnit'],
                'SellsByPurchaseUnit' => $data['SellsByPurchaseUnit'],
                'AllowsFractionalQuantity' => $data['AllowsFractionalQuantity'],
            ]);

            // SKU is derived from the product's own ProductID, which only
            // exists after the row above is inserted — set it in a follow-up
            // update rather than the create() call.
            $product->update(['SKU' => Product::generateSku($product->ProductID)]);

            Inventory::create([
                'ProductID' => $product->ProductID,
                'Quantity' => 0,
                'ReorderThreshold' => $data['ReorderThreshold'] ?? 50,
                'Status' => $status,
            ]);

            // De-duplicate by SupplierID — the client already blocks adding
            // the same supplier twice, but never trust that alone, and the
            // (ProductID, SupplierID) unique index would otherwise reject a
            // crafted duplicate mid-loop.
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

        // Edit Product modal: return just the rendered form fields instead
        // of a full page, so the modal can inject it without navigating.
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
            'CostPrice' => ['required', 'numeric', 'min:0.01'],
            'Price' => ['nullable', 'numeric', 'min:0.01'],
            'BrandID' => ['nullable', 'integer', 'exists:Brand,BrandID'],
            'CategoryID' => ['required', 'integer', 'exists:Category,CategoryID'],
            'ReorderThreshold' => ['nullable', 'integer', 'min:0'],
            // Ticked: "Not returnable / refundable" (Product.IsReturnable = false).
            'NonReturnable' => ['nullable', 'boolean'],
            'Image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'RemoveImage' => ['nullable', 'boolean'],
            ...self::unitOfMeasureRules(),
        ], [
            'Barcode.required' => 'Barcode is required. Please scan or type a barcode.',
            'Barcode.unique' => 'This barcode is already assigned to another product.',
            ...self::unitOfMeasureMessages(),
        ]);
        $units = self::validatedUnitsOfMeasure($data, Category::find($data['CategoryID']), $product);

        // See store() — re-verify the selected Brand actually belongs to the
        // selected Category rather than trusting the client.
        $brandId = $data['BrandID'] ?? null;
        if ($brandId !== null && ! Brand::where('BrandID', $brandId)->where('CategoryID', $data['CategoryID'])->exists()) {
            throw ValidationException::withMessages([
                'BrandID' => 'The selected brand does not belong to the selected category.',
            ]);
        }

        // Moving a product into a deactivated category isn't allowed; a product
        // already in one can keep it.
        if ((int) $data['CategoryID'] !== (int) $product->CategoryID && ! Category::whereKey($data['CategoryID'])->value('IsActive')) {
            throw ValidationException::withMessages([
                'CategoryID' => 'This category is deactivated. Choose an active category.',
            ]);
        }

        // Block duplicate Model against every OTHER product (case-insensitive,
        // whitespace-normalized) — the authoritative server-side check
        // backing the live AJAX one in the edit form/modal. Product Name is
        // deliberately NOT enforced unique here (unlike store()): the
        // Hikvision catalog import legitimately has multiple products
        // sharing a short name (e.g. three separate "Bullet 2MP" models),
        // and this endpoint has to remain editable for those rows.
        $normalize = function (string $value): string {
            return preg_replace('/\s+/', ' ', strtolower($value));
        };

        $others = Product::where('ProductID', '!=', $product->ProductID)->get();

        $modelTaken = $others->contains(function ($existing) use ($data, $normalize) {
            return $normalize((string) ($existing->Model ?? '')) === $normalize((string) $data['Model']);
        });

        if ($modelTaken) {
            // Throwing (rather than back()->withErrors()) lets Laravel's own
            // exception handler decide the response shape — a normal redirect
            // for a plain form post, or 422 JSON for the Edit modal's AJAX
            // submission — instead of always producing a redirect that an
            // AJAX caller can't distinguish from success.
            throw ValidationException::withMessages([
                'Model' => ['A product with this model number already exists. Duplicate model numbers are not allowed.'],
            ]);
        }

        $oldCostPrice = $product->CostPrice;

        // A new photo replaces the old one; "Remove photo" clears it. The old
        // file is deleted only after the product row is saved.
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
            'Price' => isset($data['Price']) ? (float) $data['Price'] : Product::computeSellingPrice((float) $data['CostPrice']),
            'BrandID' => $brandId,
            'CategoryID' => $data['CategoryID'],
            // Left as-is when a request doesn't carry the box at all.
            ...(array_key_exists('NonReturnable', $data) ? ['IsReturnable' => ! $data['NonReturnable']] : []),
            ...$units,
        ]);

        if ($oldImagePath !== $imagePath) {
            ProductImage::delete($oldImagePath);
        }

        ProductCostHistory::log($product, null, $oldCostPrice, (float) $data['CostPrice'], ProductCostHistory::SOURCE_PRODUCT_UPDATE);

        // Stock quantity is managed through the Inventory module, not here:
        // only the threshold (and the status it decides) is saved, against
        // the row locked as it is now, so a sale made while this form was
        // open is never written over with the quantity it was opened with.
        DB::transaction(function () use ($product, $data) {
            $inventory = Inventory::where('ProductID', $product->ProductID)->lockForUpdate()->first()
                ?? Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => 0, 'Status' => 'Out of Stock']);
            $inventory->ReorderThreshold = $data['ReorderThreshold'] ?? 50;
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

    /**
     * Products are retired instead of deleted: deleting would cascade away
     * their sales, stock and PO history. An inactive product is hidden from
     * the POS and from new Purchase Orders, and can be reactivated anytime.
     */
    public function toggleStatus(Product $product)
    {
        $product->update(['IsActive' => ! $product->IsActive]);

        if ($product->IsActive) {
            ActivityLog::record('product.activated', "Activated product \"{$product->ProductName}\"");
            ActionNotifier::toAdmins('Product Activated', "The product \"{$product->ProductName}\" is active again.", route('admin.products.index'), 'package', 'success');
            $message = "\"{$product->ProductName}\" is active again.";
        } else {
            ActivityLog::record('product.deactivated', "Deactivated product \"{$product->ProductName}\"");
            ActionNotifier::toAdmins('Product Deactivated', "The product \"{$product->ProductName}\" has been deactivated.", route('admin.products.index'), 'package', 'danger');
            $message = "\"{$product->ProductName}\" has been deactivated.";
        }

        // Back to the same list (search, filters and page kept).
        $previous = url()->previous();
        $list = route('admin.products.index');

        return redirect()->to(str_starts_with($previous, $list) ? $previous : $list)->with('status', $message);
    }
}
