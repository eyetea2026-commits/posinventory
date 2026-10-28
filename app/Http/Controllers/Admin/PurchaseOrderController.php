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
use Illuminate\Support\Collection;
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
        // "history" = every PO that has been printed (sent to Stock
        // Receiving), kept here read-only so it can still be viewed and
        // reprinted. Anything else is the normal active list.
        $isHistory = $request->query('view') === 'history';

        $purchaseOrders = PurchaseOrder::with(['supplier', 'items.product', 'stockReceivingBatch'])
            // Once a PO has been printed it's been sent into the Stock
            // Receiving queue (see printPreview()) and leaves the active
            // list for the History tab.
            ->when($isHistory, fn ($q) => $q->whereHas('stockReceivingBatch'), fn ($q) => $q->whereDoesntHave('stockReceivingBatch'))
            ->when($search, function ($query, $search) {
                // Grouped so the OR conditions can't escape the active/history
                // scope above.
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
            // PurchaseDate is a plain date the admin can freely edit as the
            // order's business date, so two orders placed the same day (or
            // deliberately backdated) can't be told apart by it — sorted by
            // the actual row creation timestamp instead, newest first.
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

        // View Details modal: return just the rendered detail rows (no
        // Status badge, no Approve/Cancel/Submit/Export PDF) instead of a
        // full page, so the index page can inject it without navigating.
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

    // Dedicated "Create Purchase Order" entry point reached only from a
    // low-stock Inventory row. Unlike the general create() form, the
    // product is fixed by the URL (no re-searching it) and every product
    // field is read-only. Supplier resolution has three states the view
    // renders differently: a single known/preferred supplier is shown
    // read-only, multiple known suppliers with none preferred force a
    // dropdown scoped to just this product's own suppliers, and zero known
    // suppliers force a dropdown of every supplier system-wide (picking one
    // assigns it to the product as a side effect of submitting).
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

        // Ordered in the purchase unit (rolls, boxes).
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

        // The Inventory module's "Create Purchase Order" button opens a
        // glassmorphism modal instead of navigating here — it fetches just
        // the field markup over AJAX. Direct navigation to this URL (a
        // bookmark, a typed address) still gets the full standalone page.
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'html' => view('admin.purchase-orders.partials.reorder-form-fields', $viewData)->render(),
                'productName' => $product->ProductName,
            ]);
        }

        return view('admin.purchase-orders.create-from-reorder', $viewData);
    }

    /**
     * Rows for the Create Purchase Order "Order Items" table: every active
     * product, low-stock ones first (at or below their threshold, ticked by
     * default), each with its threshold, previous price, a starting current
     * price and quantity, and a Supplier dropdown with the preferred (or
     * only) supplier already picked; it can be changed for this order.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function orderItemCandidates()
    {
        $activeSuppliers = Supplier::where('Status', Supplier::STATUS_ACTIVE)->orderBy('SupplierName')->get();

        return Product::active()
            ->with(['inventory', 'suppliers.supplier'])
            ->orderBy('ProductName')
            ->get()
            ->map(function (Product $product) use ($activeSuppliers) {
                $quantity = $product->inventory?->Quantity ?? 0;
                $threshold = (int) ($product->inventory?->ReorderThreshold ?? 50);
                $link = $product->resolveReorderSupplier();
                // Prices and quantity are per purchase unit (a roll's price,
                // how many rolls); stock and threshold are in the base unit.
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
                    // The Meter column: how long each roll of this order is,
                    // typed in (rolls come 100, 250, 1,000 meters...); starts
                    // at the last roll received. Only for wire ordered by the roll.
                    'ordersRollMeters' => self::ordersRollMeters($product),
                    'metersPerRoll' => $product->purchaseUnitSize(),
                    // Picked by default: the preferred supplier, or the only one.
                    'supplier' => $link?->supplier,
                    // The Supplier dropdown: this product's own suppliers
                    // first (preferred marked), then every other active one.
                    'ownSuppliers' => $product->suppliers
                        ->filter(fn ($ps) => $ps->supplier)
                        ->sortBy(fn ($ps) => [$ps->IsPreferred ? 0 : 1, $ps->supplier->SupplierName])
                        ->map(fn ($ps) => ['supplier' => $ps->supplier, 'preferred' => (bool) $ps->IsPreferred])
                        ->values(),
                    'otherSuppliers' => $activeSuppliers->whereNotIn('SupplierID', $product->suppliers->pluck('SupplierID'))->values(),
                ];
            })
            ->sortBy(fn (array $row) => [$row['lowStock'] ? 0 : 1, strtolower($row['product']->ProductName)])
            ->values();
    }

    /**
     * Saves the ticked products as Draft Purchase Orders, one per supplier:
     * products from the same supplier go on the same order.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'PurchaseDate' => ['required', 'date'],
            'Notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('Product', 'ProductID')->where('IsActive', true)],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.PurchaseOrderItem::MAX_QUANTITY],
            'items.*.cost_price' => ['required', 'numeric', 'min:0.01', 'max:'.PurchaseOrderItem::MAX_COST_PRICE],
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
            'items.*.cost_price.min' => 'Current Price must be greater than ₱0.00.',
            'items.*.cost_price.max' => 'Current Price can be at most 6 digits (₱'.number_format(PurchaseOrderItem::MAX_COST_PRICE, 2).').',
            'items.*.meters_per_roll.integer' => 'Meter must be a whole number of meters per roll.',
            'items.*.meters_per_roll.min' => 'Meter must be at least 1 meter per roll.',
            'items.*.meters_per_roll.max' => 'Meter can be at most 4 digits ('.number_format(PurchaseOrderItem::MAX_METERS_PER_ROLL).' meters per roll).',
        ]);

        // The supplier picked in the row is used; a row sent without one
        // falls back to the product's preferred (or only) supplier.
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
                // Status is never taken from the request: every new Purchase
                // Order starts as a Draft.
                $purchaseOrder = PurchaseOrder::create([
                    'PurchaseDate' => $data['PurchaseDate'],
                    'Notes' => $data['Notes'] ?? null,
                    'Status' => PurchaseOrder::STATUS_DRAFT,
                    'SupplierID' => $supplierId,
                    'CreatedBy' => auth()->id(),
                ]);

                // The PO number is derived from the auto-increment key, which
                // is already unique under concurrency.
                $year = Carbon::parse($data['PurchaseDate'])->format('Y');
                $purchaseOrder->update([
                    'PONumber' => "PO-{$year}-".str_pad((string) $purchaseOrder->PurchaseOrderID, 6, '0', STR_PAD_LEFT),
                ]);

                foreach ($items as $item) {
                    $product = $products->get($item['product_id']);
                    $unit = PurchaseOrderItem::unitFor($product);
                    // A wire roll's length as typed in the Meter column;
                    // Stock Receiving starts from it.
                    if (self::ordersRollMeters($product) && ! empty($item['meters_per_roll'])) {
                        $unit['QuantityPerUnit'] = (int) $item['meters_per_roll'];
                    }

                    PurchaseOrderItem::create([
                        'PurchaseOrderID' => $purchaseOrder->PurchaseOrderID,
                        'ProductID' => $item['product_id'],
                        'Quantity' => $item['quantity'],
                        // In the product's purchase unit (rolls, boxes).
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

        // The Create Purchase Order popup's fetch() sends Accept:
        // application/json and gets just the new rows back; the standalone
        // create page's plain form POST is redirected.
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

    // Submission for the dedicated reorder form: only Order Quantity and
    // Remarks are administrator input, everything else (product, supplier,
    // cost) is re-derived server-side from what the create-from-reorder
    // page had already resolved — never trusted from hidden form fields.
    public function storeFromReorder(Request $request, Product $product)
    {
        if (! $product->IsActive) {
            throw ValidationException::withMessages([
                'OrderQuantity' => 'This product is inactive and can no longer be ordered.',
            ]);
        }

        // A product with known suppliers must order from one of them; a
        // product with none can be assigned any supplier system-wide, which
        // also creates the missing ProductSupplier link below.
        $knownSupplierIds = $product->suppliers()->pluck('SupplierID')->all();

        $data = $request->validate([
            'OrderQuantity' => ['required', 'integer', 'min:1', 'max:'.PurchaseOrderItem::MAX_QUANTITY],
            'CostPrice' => ['required', 'numeric', 'min:0.01', 'max:'.PurchaseOrderItem::MAX_COST_PRICE],
            'Remarks' => ['nullable', 'string', 'max:1000'],
            'SupplierID' => $knownSupplierIds
                ? ['required', 'integer', Rule::in($knownSupplierIds)]
                : ['required', 'integer', 'exists:Supplier,SupplierID'],
        ], [
            'CostPrice.required' => 'Current Price is required.',
            'CostPrice.numeric' => 'Current Price must be a valid number.',
            'CostPrice.min' => 'Current Price must be greater than ₱0.00.',
            'CostPrice.max' => 'Current Price can be at most 6 digits (₱'.number_format(PurchaseOrderItem::MAX_COST_PRICE, 2).').',
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

            // Every new Purchase Order starts as a Draft, no matter how it
            // was created — this one used to jump straight to Pending,
            // which skipped the review step the Draft status exists for.
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

            // The admin's entered Current Price — never re-derived from the
            // product/supplier's own stored cost, which is shown here only
            // as the read-only "Previous Price" for reference. Existing POs
            // are untouched; this only sets THIS item's own frozen cost.
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

    // Suggested reorder quantity shared by the manual reorder form above and
    // the automatic low-stock draft below: brings stock up to 2x the reorder
    // threshold — replenish whatever deficit exists below the threshold,
    // then add one more threshold's worth as a buffer so the item doesn't
    // immediately re-enter "Replenish" the moment this order arrives.
    public static function suggestedReorderQuantity(int|float $quantity, int $threshold): int|float
    {
        return Quantity::normalize(max($threshold - $quantity, 0) + $threshold);
    }

    /**
     * Counted in meters and ordered by the roll (wire): the order says how
     * many meters each roll is.
     */
    public static function ordersRollMeters(Product $product): bool
    {
        return $product->baseUnit() === 'meter' && $product->hasPurchaseUnit();
    }

    /**
     * The same suggestion in whole purchase units: 7,000 meters short of
     * 5,000-meter rolls is 2 rolls.
     */
    public static function suggestedOrderQuantity(Product $product, int|float $quantity, int $threshold): int
    {
        // No roll/box received yet, so its size isn't known: suggest one.
        if ($product->hasPurchaseUnit() && $product->purchaseUnitSize() === null) {
            return 1;
        }

        // Capped at what the Quantity box accepts (3 digits).
        return min(
            (int) ceil(round(self::suggestedReorderQuantity($quantity, $threshold) / (float) $product->quantityPerOrderUnit(), 4)),
            PurchaseOrderItem::MAX_QUANTITY
        );
    }

    /**
     * A per-base-unit cost (Product/ProductSupplier CostPrice, per meter or
     * piece) as the price of one purchase unit (a roll, a box). Before the
     * first roll is received its size isn't known: the last price it was
     * ordered at per roll, or 0 (the admin types it in).
     */
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

    // Scans every product and drafts exactly one automatic Purchase Order
    // (Status = draft) per low-stock CYCLE — never more than one for the
    // same unresolved drop, and never a second one until the product is
    // restocked above its threshold first. Reuses the same stock-status
    // detection, reorder-quantity formula and supplier-resolution logic as
    // the existing manual "Create Purchase Order" reorder flow above; it
    // only ever creates DRAFT orders, so an admin must still review and
    // approve/send them like any other draft — nothing is auto-approved,
    // auto-sent, or auto-purchased.
    //
    // Called from InventoryController::index() — this hosting has no
    // cron/task scheduler available, so this piggybacks on real
    // Inventory-page traffic (including its existing live poll) instead of
    // a scheduled job. Inventory.AutoReorderTriggered is the per-product
    // cycle flag: it's set once a cycle is handled (a PO was auto-drafted,
    // or one already existed) and cleared again the moment the product is
    // back "In Stock", so a later drop can trigger exactly one new
    // automatic draft.
    public static function autoGenerateDraftPurchaseOrders(): void
    {
        $inventories = Inventory::with('product.suppliers')->get();

        foreach ($inventories as $inventory) {
            $product = $inventory->product;
            // An inactive product is never auto-reordered, however low its
            // stock gets.
            if (! $product || ! $product->IsActive) {
                continue;
            }

            $status = InventoryController::resolveStockStatus($inventory->Quantity, $inventory->ReorderThreshold);

            if ($status['label'] === 'In Stock') {
                if ($inventory->AutoReorderTriggered) {
                    // Closes out this cycle and watermarks the highest PO
                    // ID that exists right now, so a later drop's duplicate
                    // check (below) only looks at POs from the NEW cycle,
                    // not a stale one left over from before this restock. An
                    // ID watermark (not a timestamp) avoids any same-second
                    // ordering ambiguity between the restock and a PO row.
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

            // Everything from here through the PO insert and the
            // AutoReorderTriggered flag runs under one lock on THIS
            // product's Inventory row. Without it, two overlapping calls
            // to this method — plausible since it's triggered by ordinary
            // Inventory-page traffic/polling rather than a single scheduled
            // job — could both read AutoReorderTriggered=false and "no open
            // PO" before either commits, and both draft a duplicate PO for
            // the same low-stock cycle. Re-fetching under lockForUpdate()
            // and re-checking the flag closes that race: the second call
            // blocks until the first commits, then sees the flag already
            // set and does nothing.
            $purchaseOrder = DB::transaction(function () use ($inventory, $product, $status) {
                $locked = Inventory::where('InventoryID', $inventory->InventoryID)->lockForUpdate()->first();

                if (! $locked || $locked->AutoReorderTriggered) {
                    return null;
                }

                // An open PO (not yet fully received or cancelled) created
                // during THIS cycle already covers this product — whether
                // auto- or manually-created — so this cycle is already
                // handled; don't create a duplicate. Scoped to IDs above
                // the last-restock watermark (if any) so an old,
                // unactioned PO from a cycle already closed out by a
                // restock can't block a brand new automatic draft here.
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
                    // Ambiguous or unknown supplier — can't fabricate one, so
                    // leave the flag false and retry on the next check (e.g.
                    // once an admin assigns a supplier to the product).
                    return null;
                }

                // In the purchase unit, priced per that unit.
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

        // Locked and re-checked the same way cancel() already is, so this
        // can't race a concurrent approve()/cancel() on the same PO from
        // another tab and leave a lost update on Status.
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

        // Locked and re-checked the same way cancel() already is, so this
        // can't race a concurrent submit()/cancel() on the same PO from
        // another tab and leave a lost update on Status.
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

        // Both the status check and the "has anything been received yet"
        // check used to read unlocked, outside any transaction — a Stock
        // Receiving submission racing this request could commit in between,
        // leaving the order cancelled while its lines already show received
        // stock (exactly the state this guard exists to prevent). Locking
        // the order and its items here mirrors the lock StockReceiving
        // already takes when it receives against a line.
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

    /**
     * The supplier won't deliver the rest of a short-delivered order: the
     * owner cancels what's still owed on the chosen lines, with a reason.
     * Once nothing is owed the order is Closed and leaves the receiving
     * queue; until then it stays open for the other lines.
     */
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

                // Nothing more to receive: the delivery leaves Pending /
                // Expected Delivery.
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

    // In-browser Print Preview (window.print()), as distinct from export()'s
    // dompdf download — reviewed on-screen before printing, matching the
    // admin.damages.print / cashier receipt convention already used
    // elsewhere for printable documents.
    // Clicking Print inside View Details is what actually sends a Draft PO
    // into the receiving queue: Draft -> Pending, plus a StockReceivingBatch
    // is created so it shows up in Stock Receiving -> Pending/Expected
    // Delivery. This route is a GET (opens in a new tab), so it isn't
    // idempotent by default — the transition is guarded to only fire when
    // the PO is still Draft, locked the same way submit()/approve()/
    // cancel() already guard their own transitions above, and the batch's
    // PurchaseOrderID column is UNIQUE as a hard backstop against ever
    // creating two batches for the same PO even under a race. Printing an
    // already-Pending (or later-stage) PO just re-renders the print view.
    public function printPreview(PurchaseOrder $purchaseOrder)
    {
        try {
            DB::transaction(function () use ($purchaseOrder) {
                // Only a still-Draft PO can start this transition — once it's
                // Pending (already sent to receiving), Approved/Partially/
                // Fully Received (progressed via the legacy path), or
                // Cancelled, this lock simply matches nothing and the method
                // falls through to just rendering the print view, no matter
                // how many times Print is clicked.
                $locked = PurchaseOrder::where('PurchaseOrderID', $purchaseOrder->PurchaseOrderID)
                    ->where('Status', PurchaseOrder::STATUS_DRAFT)
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    return;
                }

                // Belt-and-suspenders: even though a batch can only exist
                // once this PO has already left Draft, explicitly re-check
                // for one under the same lock before creating anything —
                // this is the "always check before creating" guarantee,
                // made explicit rather than left implicit in firstOrCreate()
                // below, and it's what makes a future edge case (e.g. a
                // Draft status somehow restored on a PO that already has a
                // batch) fail safe instead of silently duplicating.
                $existingBatch = StockReceivingBatch::where('PurchaseOrderID', $locked->PurchaseOrderID)->first();
                if ($existingBatch) {
                    ActivityLog::record('purchase_order.print_duplicate_prevented', "Print re-triggered for PO #{$locked->PONumber} but a Stock Receiving record already exists — skipped.");

                    return;
                }

                $locked->update(['Status' => PurchaseOrder::STATUS_PENDING]);

                // The StockReceivingBatch.PurchaseOrderID UNIQUE constraint
                // is the hard backstop underneath this: even if two requests
                // somehow got past the checks above at the same time, only
                // one insert can ever succeed.
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
            // Printing must still work even if the status transition fails
            // for some reason — the admin still needs the document.
        }

        $purchaseOrder->refresh()->load(['supplier', 'items.product', 'createdByUser', 'approvedByUser']);

        return view('admin.purchase-orders.print', ['purchaseOrder' => $purchaseOrder]);
    }
}
