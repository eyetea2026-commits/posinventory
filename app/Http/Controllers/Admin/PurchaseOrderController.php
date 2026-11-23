<?php

namespace App\Http\Controllers\Admin;

use App\Casts\Quantity;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockReceivingBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\PurchaseOrderApproved;
use App\Notifications\PurchaseOrderCancelled;
use App\Services\ActionNotifier;
use App\Services\UnitOfMeasure;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PurchaseOrderController extends Controller
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
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $supplierId = $request->query('supplier_id');
        $isHistory = $request->query('view') === 'history';

        $purchaseOrders = PurchaseOrder::with(['supplier', 'items.product', 'stockReceivingBatch'])
            ->when($isHistory, fn ($q) => $q->whereHas('stockReceivingBatch'), fn ($q) => $q->whereDoesntHave('stockReceivingBatch'))
            ->when($search, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('PONumber', 'like', "%{$search}%")
                        ->orWhere('Status', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($supplier) use ($search) {
                            $supplier->where('SupplierName', 'like', "%{$search}%");
                        });
                });
            })
            ->when($dateFrom, fn ($q) => $q->whereDate('PurchaseDate', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('PurchaseDate', '<=', $dateTo))
            ->when($supplierId, fn ($q) => $q->where('SupplierID', $supplierId))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.purchase-orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'isHistory' => $isHistory,
            'activeCount' => PurchaseOrder::whereDoesntHave('stockReceivingBatch')->count(),
            'historyCount' => PurchaseOrder::whereHas('stockReceivingBatch')->count(),
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'supplierId' => $supplierId,
            'suppliers' => Supplier::orderBy('SupplierName')->get(),
            'products' => Product::active()->with('category')->orderBy('ProductName')->get(),
        ]);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'items.product', 'stockReceivings']);

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'html' => view('admin.purchase-orders.partials.purchase-order-details', [
                    'purchaseOrder' => $purchaseOrder,
                    'showStatus' => false,
                ])->render(),
            ]);
        }

        return view('admin.purchase-orders.show', [
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    public function create()
    {
        return view('admin.purchase-orders.create', [
            'suppliers' => Supplier::orderBy('SupplierName')->get(),
            'products' => Product::active()->with('category')->orderBy('ProductName')->get(),
            'categories' => Category::orderBy('CategoryName')->get(),
        ]);
    }

    public function createFromReorder(Request $request, Product $product)
    {
        if (! $product->IsActive) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['message' => 'This product is inactive and can no longer be ordered.'], 422);
            }

            return redirect()->route('admin.inventory.index')->with('error', 'This product is inactive and can no longer be ordered.');
        }

        $product->load(['category', 'brand', 'inventory', 'suppliers.supplier']);

        $quantity = $product->inventory?->Quantity ?? 0;
        $threshold = (int) ($product->inventory?->ReorderThreshold ?? 50);

        $suggestedQuantity = self::suggestedOrderQuantity($product, $quantity, $threshold);

        $knownSuppliers = $product->suppliers;
        $resolvedSupplier = $product->resolveReorderSupplier();

        if ($resolvedSupplier) {
            $supplierState = 'resolved';
        } elseif ($knownSuppliers->isNotEmpty()) {
            $supplierState = 'ambiguous';
        } else {
            $supplierState = 'none';
        }

        $viewData = [
            'product' => $product,
            'quantity' => $quantity,
            'threshold' => $threshold,
            'suggestedQuantity' => $suggestedQuantity,
            'resolvedSupplier' => $resolvedSupplier,
            'knownSuppliers' => $knownSuppliers,
            'supplierState' => $supplierState,
            'allSuppliers' => $supplierState === 'none' ? Supplier::orderBy('SupplierName')->get() : collect(),
        ];

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'html' => view('admin.purchase-orders.partials.reorder-form-fields', $viewData)->render(),
                'productName' => $product->ProductName,
            ]);
        }

        return view('admin.purchase-orders.create-from-reorder', $viewData);
    }

    public static function orderItemCandidates()
    {
        $activeSuppliers = Supplier::where('Status', Supplier::STATUS_ACTIVE)->orderBy('SupplierName')->get();

        $products = Product::active()
            ->with(['inventory', 'suppliers.supplier'])
            ->orderBy('ProductName')
            ->get();
        Product::preloadLastDeliveredSuppliers($products);

        return $products
            ->map(function (Product $product) use ($activeSuppliers) {
                $quantity = $product->inventory?->Quantity ?? 0;
                $threshold = (int) ($product->inventory?->ReorderThreshold ?? 50);
                $link = $product->resolveReorderSupplier();
                $previousPrice = self::costPerPurchaseUnit($product, $product->CostPrice);
                $currentPrice = $link && $link->CostPrice !== null ? self::costPerPurchaseUnit($product, $link->CostPrice) : $previousPrice;

                return [
                    'product' => $product,
                    'stock' => $quantity,
                    'stockLabel' => $product->formatQuantity($quantity),
                    'threshold' => $threshold,
                    'thresholdLabel' => $product->formatQuantity($threshold),
                    'lowStock' => $quantity <= $threshold,
                    'previousPrice' => $previousPrice,
                    'currentPrice' => $currentPrice,
                    'unit' => $product->orderUnit(),
                    'unitPlural' => UnitOfMeasure::plural($product->orderUnit()),
                    'unitHolds' => $product->purchaseUnitSize() ? $product->formatQuantity($product->purchaseUnitSize()) : null,
                    'suggestedQuantity' => max(1, self::suggestedOrderQuantity($product, $quantity, $threshold)),
                    'ordersRollMeters' => self::ordersRollMeters($product),
                    'metersPerRoll' => $product->purchaseUnitSize(),
                    'supplier' => $link?->supplier,
                    'ownSuppliers' => $product->suppliers
                        ->filter(fn ($ps) => $ps->supplier)
                        ->sortBy(fn ($ps) => [$ps->IsPreferred ? 0 : 1, $ps->supplier->SupplierName])
                        ->map(fn ($ps) => ['supplier' => $ps->supplier, 'preferred' => (bool) $ps->IsPreferred, 'price' => self::costPerPurchaseUnit($product, $product->supplierPrice($ps))])
                        ->values(),
                    'otherSuppliers' => $activeSuppliers->whereNotIn('SupplierID', $product->suppliers->pluck('SupplierID'))->values(),
                ];
            })
            ->sortBy(fn (array $row) => [$row['lowStock'] ? 0 : 1, strtolower($row['product']->ProductName)])
            ->values();
    }

    public function store(Request $request)
    {
        $this->stripPriceCommas($request);

        $data = $request->validate([
            'PurchaseDate' => ['required', 'date'],
            'Notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('Product', 'ProductID')->where('IsActive', true)],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.PurchaseOrderItem::MAX_QUANTITY],
            'items.*.cost_price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:'.PurchaseOrderItem::MAX_COST_PRICE],
            'items.*.supplier_id' => ['nullable', 'integer', 'exists:Supplier,SupplierID'],
            'items.*.meters_per_roll' => ['nullable', 'integer', 'min:1', 'max:'.PurchaseOrderItem::MAX_METERS_PER_ROLL],
        ], [
            'items.required' => 'Tick at least one product to order.',
            'items.min' => 'Tick at least one product to order.',
            'items.*.product_id.exists' => 'One of the selected products is inactive and can no longer be ordered.',
            'items.*.quantity.required' => 'Quantity is required for every ticked product.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'items.*.quantity.max' => 'Quantity can be at most 3 digits ('.PurchaseOrderItem::MAX_QUANTITY.').',
            'items.*.cost_price.required' => 'Current Price is required for every ticked product.',
            'items.*.cost_price.numeric' => 'Current Price must be a valid number.',
            'items.*.cost_price.decimal' => 'Current Price can have at most 2 decimal places.',
            'items.*.cost_price.min' => 'Current Price must be greater than ₱0.00.',
            'items.*.cost_price.max' => 'Current Price can be at most 5 digits (₱'.number_format(PurchaseOrderItem::MAX_COST_PRICE, 2).').',
            'items.*.meters_per_roll.integer' => 'Meter must be a whole number of meters per roll.',
            'items.*.meters_per_roll.min' => 'Meter must be at least 1 meter per roll.',
            'items.*.meters_per_roll.max' => 'Meter can be at most 4 digits ('.number_format(PurchaseOrderItem::MAX_METERS_PER_ROLL).' meters per roll).',
        ]);

        $products = Product::with('suppliers')->whereIn('ProductID', collect($data['items'])->pluck('product_id'))->get()->keyBy('ProductID');
        $groups = [];
        foreach ($data['items'] as $item) {
            $product = $products->get($item['product_id']);
            $supplierId = ($item['supplier_id'] ?? null) ?: $product->resolveReorderSupplier()?->SupplierID;
            if (! $supplierId) {
                throw ValidationException::withMessages([
                    'items' => "Choose a supplier for \"{$product->ProductName}\".",
                ]);
            }
            $groups[$supplierId][] = $item;
        }

        $purchaseOrders = DB::transaction(function () use ($data, $groups, $products) {
            $created = [];
            foreach ($groups as $supplierId => $items) {
                $purchaseOrder = PurchaseOrder::create([
                    'PurchaseDate' => $data['PurchaseDate'],
                    'Notes' => $data['Notes'] ?? null,
                    'Status' => PurchaseOrder::STATUS_DRAFT,
                    'SupplierID' => $supplierId,
                    'CreatedBy' => auth()->id(),
                ]);

                $year = Carbon::parse($data['PurchaseDate'])->format('Y');
                $purchaseOrder->update([
                    'PONumber' => "PO-{$year}-".str_pad((string) $purchaseOrder->PurchaseOrderID, 6, '0', STR_PAD_LEFT),
                ]);

                foreach ($items as $item) {
                    $product = $products->get($item['product_id']);
                    $unit = PurchaseOrderItem::unitFor($product);
                    if (self::ordersRollMeters($product) && ! empty($item['meters_per_roll'])) {
                        $unit['QuantityPerUnit'] = (int) $item['meters_per_roll'];
                    }

                    PurchaseOrderItem::create([
                        'PurchaseOrderID' => $purchaseOrder->PurchaseOrderID,
                        'ProductID' => $item['product_id'],
                        'Quantity' => $item['quantity'],
                        ...$unit,
                        'CostPriceAtOrder' => $item['cost_price'],
                    ]);
                }

                $created[] = $purchaseOrder->load('supplier');
            }

            return $created;
        });

        foreach ($purchaseOrders as $purchaseOrder) {
            ActivityLog::record('purchase_order.created', "Created PO #{$purchaseOrder->PONumber} for \"{$purchaseOrder->supplier?->SupplierName}\"");
            ActionNotifier::toAdmins('Purchase Order Created', "Purchase order {$purchaseOrder->PONumber} for \"{$purchaseOrder->supplier?->SupplierName}\" has been created as a Draft.", route('admin.purchase-orders.index'), 'clipboard-check', 'success');
        }

        $numbers = collect($purchaseOrders)->pluck('PONumber')->implode(', ');
        $message = count($purchaseOrders) === 1
            ? "Purchase order {$numbers} created successfully."
            : count($purchaseOrders)." purchase orders created, one per supplier: {$numbers}.";

        if ($request->wantsJson()) {
            $listed = PurchaseOrder::with(['supplier', 'items.product'])
                ->whereDoesntHave('stockReceivingBatch')
                ->orderByDesc('created_at')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'message' => $message,
                'purchase_orders' => collect($purchaseOrders)->pluck('PONumber'),
                'html' => view('admin.purchase-orders.partials.purchase-order-rows', [
                    'purchaseOrders' => $listed,
                ])->render(),
            ]);
        }

        return redirect()->route('admin.purchase-orders.index')->with('success', $message);
    }

    public function storeFromReorder(Request $request, Product $product)
    {
        if (! $product->IsActive) {
            throw ValidationException::withMessages([
                'OrderQuantity' => 'This product is inactive and can no longer be ordered.',
            ]);
        }

        $knownSupplierIds = $product->suppliers()->pluck('SupplierID')->all();

        $this->stripPriceCommas($request);

        $data = $request->validate([
            'OrderQuantity' => ['required', 'integer', 'min:1', 'max:'.PurchaseOrderItem::MAX_QUANTITY],
            'CostPrice' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:'.PurchaseOrderItem::MAX_COST_PRICE],
            'Remarks' => ['nullable', 'string', 'max:1000'],
            'SupplierID' => $knownSupplierIds
                ? ['required', 'integer', Rule::in($knownSupplierIds)]
                : ['required', 'integer', 'exists:Supplier,SupplierID'],
        ], [
            'CostPrice.required' => 'Current Price is required.',
            'CostPrice.numeric' => 'Current Price must be a valid number.',
            'CostPrice.decimal' => 'Current Price can have at most 2 decimal places.',
            'CostPrice.min' => 'Current Price must be greater than ₱0.00.',
            'CostPrice.max' => 'Current Price can be at most 5 digits (₱'.number_format(PurchaseOrderItem::MAX_COST_PRICE, 2).').',
            'OrderQuantity.max' => 'Quantity can be at most 3 digits ('.PurchaseOrderItem::MAX_QUANTITY.').',
        ]);

        $purchaseOrder = DB::transaction(function () use ($data, $product) {
            $productSupplier = ProductSupplier::where('ProductID', $product->ProductID)
                ->where('SupplierID', $data['SupplierID'])
                ->first();

            if (! $productSupplier) {
                $productSupplier = ProductSupplier::create([
                    'ProductID' => $product->ProductID,
                    'SupplierID' => $data['SupplierID'],
                    'CostPrice' => $product->CostPrice,
                ]);

                ActivityLog::record('product_supplier.assigned', "Assigned supplier for \"{$product->ProductName}\" while creating a purchase order");
            }

            $purchaseOrder = PurchaseOrder::create([
                'PurchaseDate' => now()->toDateString(),
                'Notes' => $data['Remarks'] ?? null,
                'Status' => PurchaseOrder::STATUS_DRAFT,
                'SupplierID' => $data['SupplierID'],
                'CreatedBy' => auth()->id(),
            ]);

            $purchaseOrder->update([
                'PONumber' => 'PO-'.now()->format('Y').'-'.str_pad((string) $purchaseOrder->PurchaseOrderID, 6, '0', STR_PAD_LEFT),
            ]);

            PurchaseOrderItem::create([
                'PurchaseOrderID' => $purchaseOrder->PurchaseOrderID,
                'ProductID' => $product->ProductID,
                'Quantity' => $data['OrderQuantity'],
                ...PurchaseOrderItem::unitFor($product),
                'CostPriceAtOrder' => $data['CostPrice'],
            ]);

            return $purchaseOrder;
        });

        $supplier = Supplier::find($data['SupplierID']);
        ActivityLog::record('purchase_order.created', "Created PO #{$purchaseOrder->PONumber} for \"{$supplier?->SupplierName}\" from a low-stock reorder");
        ActionNotifier::toAdmins('Purchase Order Created', "Purchase order {$purchaseOrder->PONumber} for \"{$supplier?->SupplierName}\" has been created from a low-stock reorder.", route('admin.purchase-orders.index'), 'clipboard-check', 'success');

        return redirect()->route('admin.purchase-orders.index')->with('success', "Purchase order {$purchaseOrder->PONumber} created successfully.");
    }

    public static function suggestedReorderQuantity(int|float $quantity, int $threshold): int|float
    {
        return Quantity::normalize(max($threshold - $quantity, 0) + $threshold);
    }

    public static function ordersRollMeters(Product $product): bool
    {
        return $product->baseUnit() === 'meter' && $product->hasPurchaseUnit();
    }

    public static function suggestedOrderQuantity(Product $product, int|float $quantity, int $threshold): int
    {
        if ($product->hasPurchaseUnit() && $product->purchaseUnitSize() === null) {
            return 1;
        }

        return min(
            (int) ceil(round(self::suggestedReorderQuantity($quantity, $threshold) / (float) $product->quantityPerOrderUnit(), 4)),
            PurchaseOrderItem::MAX_QUANTITY
        );
    }

    public static function costPerPurchaseUnit(Product $product, $costPrice): float
    {
        if ($product->hasPurchaseUnit() && $product->purchaseUnitSize() === null) {
            return round((float) PurchaseOrderItem::where('ProductID', $product->ProductID)
                ->where('Unit', $product->PurchaseUnit)
                ->orderByDesc('PurchaseOrderItemID')
                ->value('CostPriceAtOrder'), 2);
        }

        return round((float) ($costPrice ?? 0) * (float) $product->quantityPerOrderUnit(), 2);
    }

    private function stripPriceCommas(Request $request): void
    {
        $withoutCommas = fn ($price) => is_string($price) ? str_replace(',', '', trim($price)) : $price;

        if ($request->has('CostPrice')) {
            $request->merge(['CostPrice' => $withoutCommas($request->input('CostPrice'))]);
        }

        $items = $request->input('items');
        if (is_array($items)) {
            foreach ($items as $key => $item) {
                if (is_array($item) && array_key_exists('cost_price', $item)) {
                    $items[$key]['cost_price'] = $withoutCommas($item['cost_price']);
                }
            }
            $request->merge(['items' => $items]);
        }
    }

    public static function autoGenerateDraftPurchaseOrders(): void
    {
        $inventories = Inventory::with('product.suppliers.supplier')->get();
        Product::preloadLastDeliveredSuppliers($inventories->pluck('product')->filter());

        foreach ($inventories as $inventory) {
            $product = $inventory->product;
            if (! $product || ! $product->IsActive) {
                continue;
            }

            $status = InventoryController::resolveStockStatus($inventory->Quantity, $inventory->ReorderThreshold);

            if ($status['label'] === 'In Stock') {
                if ($inventory->AutoReorderTriggered) {
                    $inventory->update([
                        'AutoReorderTriggered' => false,
                        'LastRestockPurchaseOrderId' => PurchaseOrder::max('PurchaseOrderID') ?? 0,
                    ]);
                }

                continue;
            }

            if ($inventory->AutoReorderTriggered) {
                continue;
            }

            $purchaseOrder = DB::transaction(function () use ($inventory, $product, $status) {
                $locked = Inventory::where('InventoryID', $inventory->InventoryID)->lockForUpdate()->first();

                if (! $locked || $locked->AutoReorderTriggered) {
                    return null;
                }

                $hasOpenPurchaseOrder = PurchaseOrderItem::where('ProductID', $product->ProductID)
                    ->where('PurchaseOrderID', '>', $locked->LastRestockPurchaseOrderId ?? 0)
                    ->whereHas('purchaseOrder', function ($query) {
                        $query->whereNotIn('Status', [PurchaseOrder::STATUS_FULLY_RECEIVED, PurchaseOrder::STATUS_CANCELLED, PurchaseOrder::STATUS_CLOSED]);
                    })
                    ->exists();

                if ($hasOpenPurchaseOrder) {
                    $locked->update(['AutoReorderTriggered' => true]);

                    return null;
                }

                $resolvedSupplier = $product->resolveReorderSupplier();
                if (! $resolvedSupplier) {
                    return null;
                }

                $suggestedQuantity = self::suggestedOrderQuantity($product, $locked->Quantity, (int) ($locked->ReorderThreshold ?? 50));
                $costPrice = self::costPerPurchaseUnit($product, $resolvedSupplier->CostPrice ?? $product->CostPrice);

                $purchaseOrder = PurchaseOrder::create([
                    'PurchaseDate' => now()->toDateString(),
                    'Notes' => "Automatically drafted \u{2014} stock status is \"{$status['label']}\".",
                    'Status' => PurchaseOrder::STATUS_DRAFT,
                    'SupplierID' => $resolvedSupplier->SupplierID,
                    'CreatedBy' => null,
                ]);

                $purchaseOrder->update([
                    'PONumber' => 'PO-'.now()->format('Y').'-'.str_pad((string) $purchaseOrder->PurchaseOrderID, 6, '0', STR_PAD_LEFT),
                ]);

                PurchaseOrderItem::create([
                    'PurchaseOrderID' => $purchaseOrder->PurchaseOrderID,
                    'ProductID' => $product->ProductID,
                    'Quantity' => $suggestedQuantity,
                    ...PurchaseOrderItem::unitFor($product),
                    'CostPriceAtOrder' => $costPrice,
                ]);

                $locked->update(['AutoReorderTriggered' => true]);

                return $purchaseOrder;
            });

            if ($purchaseOrder) {
                ActivityLog::record('purchase_order.auto_drafted', "Automatically drafted PO #{$purchaseOrder->PONumber} for \"{$product->ProductName}\" (Low Stock)");
                ActionNotifier::toAdmins('Purchase Order Drafted', "Purchase order {$purchaseOrder->PONumber} was drafted automatically for \"{$product->ProductName}\" (Low Stock).", route('admin.purchase-orders.index'), 'clipboard-check', 'warning');
            }
        }
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        if (! in_array($purchaseOrder->Status, PurchaseOrder::EDITABLE_STATUSES, true)) {
            return back()->with('error', 'Only draft or pending purchase orders can be edited.');
        }

        $purchaseOrder->load('items.product');

        return response()->json([
            'html' => view('admin.purchase-orders.partials.purchase-order-edit-form', [
                'purchaseOrder' => $purchaseOrder,
                'suppliers' => Supplier::orderBy('SupplierName')->get(),
            ])->render(),
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (! in_array($purchaseOrder->Status, PurchaseOrder::EDITABLE_STATUSES, true)) {
            return back()->with('error', 'Only draft or pending purchase orders can be edited.');
        }

        $data = $request->validate([
            'SupplierID' => ['required', 'integer', 'exists:Supplier,SupplierID'],
            'PurchaseDate' => ['required', 'date'],
            'ExpectedDeliveryDate' => ['nullable', 'date', 'after_or_equal:PurchaseDate'],
            'Notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $purchaseOrder->update($data);

        ActivityLog::record('purchase_order.updated', "Updated PO #{$purchaseOrder->PONumber}");
        ActionNotifier::toAdmins('Purchase Order Updated', "Purchase order {$purchaseOrder->PONumber} has been updated.", route('admin.purchase-orders.index'), 'clipboard-check');

        return redirect()->route('admin.purchase-orders.index')->with('success', 'Purchase order updated successfully.');
    }

    public function submit(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->Status !== PurchaseOrder::STATUS_DRAFT) {
            return back()->with('error', 'Only draft purchase orders can be submitted.');
        }

        try {
            DB::transaction(function () use ($purchaseOrder) {
                $locked = PurchaseOrder::where('PurchaseOrderID', $purchaseOrder->PurchaseOrderID)
                    ->where('Status', PurchaseOrder::STATUS_DRAFT)
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    throw new \RuntimeException('Only draft purchase orders can be submitted.');
                }

                $locked->update(['Status' => PurchaseOrder::STATUS_PENDING]);
            });
            ActivityLog::record('purchase_order.submitted', "Submitted PO #{$purchaseOrder->PONumber}");
            ActionNotifier::toAdmins('Purchase Order Submitted', "Purchase order {$purchaseOrder->PONumber} has been submitted and is waiting for approval.", route('admin.purchase-orders.index'), 'clipboard-check', 'warning');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Failed to submit purchase order', ['purchase_order_id' => $purchaseOrder->PurchaseOrderID, 'exception' => $e->getMessage()]);

            return back()->with('error', 'Failed to submit the purchase order. Please try again.');
        }

        return back()->with('success', 'Purchase order submitted.');
    }

    public function approve(PurchaseOrder $purchaseOrder)
    {
        if (! in_array($purchaseOrder->Status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_PENDING], true)) {
            return back()->with('error', 'Only draft or pending purchase orders can be approved.');
        }

        try {
            DB::transaction(function () use ($purchaseOrder) {
                $locked = PurchaseOrder::where('PurchaseOrderID', $purchaseOrder->PurchaseOrderID)
                    ->whereIn('Status', [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_PENDING])
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    throw new \RuntimeException('Only draft or pending purchase orders can be approved.');
                }

                $locked->update(['Status' => PurchaseOrder::STATUS_APPROVED, 'ApprovedBy' => auth()->id()]);
            });
            ActivityLog::record('purchase_order.approved', "Approved PO #{$purchaseOrder->PONumber}");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Failed to approve purchase order', ['purchase_order_id' => $purchaseOrder->PurchaseOrderID, 'exception' => $e->getMessage()]);

            return back()->with('error', 'Failed to approve the purchase order. Please try again.');
        }

        try {
            Notification::send(User::admins(), new PurchaseOrderApproved($purchaseOrder));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch PurchaseOrderApproved notification', [
                'purchase_order_id' => $purchaseOrder->PurchaseOrderID,
                'exception' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Purchase order approved and is now ready to receive against.');
    }

    public function cancel(PurchaseOrder $purchaseOrder)
    {
        if (! in_array($purchaseOrder->Status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_PENDING, PurchaseOrder::STATUS_APPROVED], true)) {
            return back()->with('error', 'This purchase order can no longer be cancelled.');
        }

        try {
            DB::transaction(function () use ($purchaseOrder) {
                $locked = PurchaseOrder::where('PurchaseOrderID', $purchaseOrder->PurchaseOrderID)
                    ->whereIn('Status', [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_PENDING, PurchaseOrder::STATUS_APPROVED])
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    throw new \RuntimeException('This purchase order can no longer be cancelled.');
                }

                $locked->load(['items' => fn ($q) => $q->lockForUpdate()]);

                if ($locked->hasAnyReceivedQuantity()) {
                    throw new \RuntimeException('This purchase order already has received stock and can no longer be cancelled.');
                }

                $locked->update(['Status' => PurchaseOrder::STATUS_CANCELLED]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLog::record('purchase_order.cancelled', "Cancelled PO #{$purchaseOrder->PONumber}");

        try {
            Notification::send(User::admins(), new PurchaseOrderCancelled($purchaseOrder));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch PurchaseOrderCancelled notification', [
                'purchase_order_id' => $purchaseOrder->PurchaseOrderID,
                'exception' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Purchase order cancelled.');
    }

    public function cancelRemaining(Request $request, PurchaseOrder $purchaseOrder)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'items.required' => 'Tick at least one product whose remaining quantity should be cancelled.',
            'reason.required' => 'Enter why the remaining items are being cancelled.',
        ]);

        $cancelled = [];
        $closed = false;

        try {
            DB::transaction(function () use ($data, $purchaseOrder, &$cancelled, &$closed) {
                $locked = PurchaseOrder::where('PurchaseOrderID', $purchaseOrder->PurchaseOrderID)
                    ->where('Status', PurchaseOrder::STATUS_PARTIALLY_RECEIVED)
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    throw new \RuntimeException('Only a partially received purchase order can have its remaining items cancelled.');
                }

                $items = PurchaseOrderItem::with('product')
                    ->where('PurchaseOrderID', $locked->PurchaseOrderID)
                    ->whereIn('PurchaseOrderItemID', $data['items'])
                    ->lockForUpdate()
                    ->get();

                foreach ($items as $item) {
                    $remaining = $item->remaining_quantity;
                    if ($remaining <= 0) {
                        continue;
                    }
                    $item->update(['CancelledQuantity' => (int) $item->CancelledQuantity + $remaining]);
                    $cancelled[] = "{$remaining} x \"{$item->product?->ProductName}\"";
                }

                if ($cancelled === []) {
                    throw new \RuntimeException('Those products have nothing left to cancel.');
                }

                $locked->load('items');
                $closed = $locked->isSettled();
                $locked->update([
                    'Status' => $closed ? PurchaseOrder::STATUS_CLOSED : PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                    'RemainderCancelReason' => $data['reason'],
                    'RemainderCancelledBy' => auth()->id(),
                    'RemainderCancelledAt' => now(),
                ]);

                if ($closed) {
                    StockReceivingBatch::where('PurchaseOrderID', $locked->PurchaseOrderID)
                        ->update(['Status' => StockReceivingBatch::STATUS_COMPLETED, 'CompletedAt' => now()]);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $summary = implode(', ', $cancelled);
        ActivityLog::record('purchase_order.remainder_cancelled', "Cancelled the remaining {$summary} on PO #{$purchaseOrder->PONumber}: {$data['reason']}");
        ActionNotifier::toAdmins(
            'Remaining Items Cancelled',
            "Purchase order {$purchaseOrder->PONumber}: {$summary} will not be delivered ({$data['reason']}).".($closed ? ' The order is now Closed.' : ''),
            route('admin.purchase-orders.index'),
            'clipboard-check',
            'warning'
        );

        return back()->with('success', $closed
            ? 'Remaining items cancelled. This purchase order is now Closed.'
            : 'Remaining items cancelled. The order stays open for the other products.');
    }

    public function export(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'items.product']);

        return Pdf::loadView('admin.purchase-orders.pdf', ['purchaseOrder' => $purchaseOrder])
            ->download("{$purchaseOrder->PONumber}.pdf");
    }

    public function printPreview(PurchaseOrder $purchaseOrder)
    {
        try {
            DB::transaction(function () use ($purchaseOrder) {
                $locked = PurchaseOrder::where('PurchaseOrderID', $purchaseOrder->PurchaseOrderID)
                    ->where('Status', PurchaseOrder::STATUS_DRAFT)
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    return;
                }

                $existingBatch = StockReceivingBatch::where('PurchaseOrderID', $locked->PurchaseOrderID)->first();
                if ($existingBatch) {
                    ActivityLog::record('purchase_order.print_duplicate_prevented', "Print re-triggered for PO #{$locked->PONumber} but a Stock Receiving record already exists — skipped.");

                    return;
                }

                $locked->update(['Status' => PurchaseOrder::STATUS_PENDING]);

                StockReceivingBatch::create([
                    'PurchaseOrderID' => $locked->PurchaseOrderID,
                    'Status' => StockReceivingBatch::STATUS_PENDING,
                ]);

                ActivityLog::record('purchase_order.printed', "Printed PO #{$locked->PONumber} — sent to Stock Receiving");
            });
        } catch (Throwable $e) {
            Log::error('Failed to transition purchase order to pending on print', [
                'purchase_order_id' => $purchaseOrder->PurchaseOrderID,
                'exception' => $e->getMessage(),
            ]);
        }

        $purchaseOrder->refresh()->load(['supplier', 'items.product', 'createdByUser']);

        return view('admin.purchase-orders.print', ['purchaseOrder' => $purchaseOrder]);
    }
}
