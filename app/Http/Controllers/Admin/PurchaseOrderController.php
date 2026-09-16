<?php

namespace App\Http\Controllers\Admin;

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
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
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
        $categoryId = $request->query('category_id');

        $purchaseOrders = PurchaseOrder::with(['supplier', 'items.product'])
            // Once a PO has been printed it's been sent into the Stock
            // Receiving queue (see printPreview()) and belongs in that
            // module's Pending/Completed tabs instead of here.
            ->whereDoesntHave('stockReceivingBatch')
            ->when($search, function ($query, $search) {
                $query->where('PONumber', 'like', "%{$search}%")
                    ->orWhere('Status', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($supplier) use ($search) {
                        $supplier->where('SupplierName', 'like', "%{$search}%");
                    });
            })
            ->when($dateFrom, fn ($q) => $q->whereDate('PurchaseDate', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('PurchaseDate', '<=', $dateTo))
            ->when($categoryId, function ($q) use ($categoryId) {
                $q->whereHas('items.product', function ($product) use ($categoryId) {
                    $product->where('CategoryID', $categoryId);
                });
            })
            // PurchaseDate is a plain date the admin can freely edit as the
            // order's business date, so two orders placed the same day (or
            // deliberately backdated) can't be told apart by it — sorted by
            // the actual row creation timestamp instead, newest first.
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.purchase-orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'categoryId' => $categoryId,
            'categories' => Category::orderBy('CategoryName')->get(),
            'suppliers' => Supplier::orderBy('SupplierName')->get(),
            'products' => Product::with('category')->orderBy('ProductName')->get(),
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
            'products' => Product::with('category')->orderBy('ProductName')->get(),
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
        $product->load(['category', 'brand', 'inventory', 'suppliers.supplier']);

        $quantity = (int) ($product->inventory?->Quantity ?? 0);
        $threshold = (int) ($product->inventory?->ReorderThreshold ?? 50);

        $suggestedQuantity = self::suggestedReorderQuantity($quantity, $threshold);

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

    public function store(Request $request)
    {
        $data = $request->validate([
            'SupplierID' => ['required', 'integer', 'exists:Supplier,SupplierID'],
            'PurchaseDate' => ['required', 'date'],
            'ExpectedDeliveryDate' => ['nullable', 'date', 'after_or_equal:PurchaseDate'],
            'Notes' => ['nullable', 'string', 'max:1000'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'integer', 'exists:Product,ProductID'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
            'products.*.cost_price' => ['required', 'numeric', 'min:0.01'],
        ], [
            'products.*.cost_price.required' => 'Current Price is required for every product.',
            'products.*.cost_price.numeric' => 'Current Price must be a valid number.',
            'products.*.cost_price.min' => 'Current Price must be greater than ₱0.00.',
        ]);

        $purchaseOrder = DB::transaction(function () use ($data) {
            // Status is never taken from the request — every new Purchase
            // Order starts life as a Draft, full stop, regardless of what a
            // client sends. The old Status <select> is gone from the form
            // for the same reason.
            $purchaseOrder = PurchaseOrder::create([
                'PurchaseDate' => $data['PurchaseDate'],
                'ExpectedDeliveryDate' => $data['ExpectedDeliveryDate'] ?? null,
                'Notes' => $data['Notes'] ?? null,
                'Status' => PurchaseOrder::STATUS_DRAFT,
                'SupplierID' => $data['SupplierID'],
                'CreatedBy' => auth()->id(),
            ]);

            // The PO number is derived from the now-known auto-increment PK,
            // which already guarantees uniqueness under concurrency — no
            // separate counter/locking scheme needed.
            $year = \Illuminate\Support\Carbon::parse($data['PurchaseDate'])->format('Y');
            $purchaseOrder->update([
                'PONumber' => "PO-{$year}-" . str_pad((string) $purchaseOrder->PurchaseOrderID, 6, '0', STR_PAD_LEFT),
            ]);

            foreach ($data['products'] as $item) {
                if (empty($item['product_id']) || empty($item['quantity'])) {
                    continue;
                }

                // Current Price is required and validated above (> ₱0.00) —
                // always the admin's own entered value now, never silently
                // re-derived from the product's stored cost.
                PurchaseOrderItem::create([
                    'PurchaseOrderID' => $purchaseOrder->PurchaseOrderID,
                    'ProductID' => $item['product_id'],
                    'Quantity' => $item['quantity'],
                    'CostPriceAtOrder' => $item['cost_price'],
                ]);
            }

            return $purchaseOrder;
        });

        $supplier = Supplier::find($data['SupplierID']);
        ActivityLog::record('purchase_order.created', "Created PO #{$purchaseOrder->PONumber} for \"{$supplier?->SupplierName}\"");

        $message = "Purchase order {$purchaseOrder->PONumber} created successfully.";

        // The "Create Purchase Order" modal's fetch() sends Accept:
        // application/json — respond with just the new row markup instead
        // of redirecting to index() and making it re-fetch/re-render the
        // whole page (every product/supplier/category dropdown, full page
        // chrome, ...) only to scrape the table back out of it, which was
        // the actual cause of "saving is slow". The standalone, non-AJAX
        // create.blade.php page's plain form POST doesn't send that header,
        // so it keeps getting the original redirect untouched.
        if ($request->wantsJson()) {
            $purchaseOrders = PurchaseOrder::with(['supplier', 'items.product'])
                ->whereDoesntHave('stockReceivingBatch')
                ->orderByDesc('created_at')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'message' => $message,
                'html' => view('admin.purchase-orders.partials.purchase-order-rows', [
                    'purchaseOrders' => $purchaseOrders,
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
        // A product with known suppliers must order from one of them; a
        // product with none can be assigned any supplier system-wide, which
        // also creates the missing ProductSupplier link below.
        $knownSupplierIds = $product->suppliers()->pluck('SupplierID')->all();

        $data = $request->validate([
            'OrderQuantity' => ['required', 'integer', 'min:1'],
            'CostPrice' => ['required', 'numeric', 'min:0.01'],
            'Remarks' => ['nullable', 'string', 'max:1000'],
            'SupplierID' => $knownSupplierIds
                ? ['required', 'integer', Rule::in($knownSupplierIds)]
                : ['required', 'integer', 'exists:Supplier,SupplierID'],
        ], [
            'CostPrice.required' => 'Current Price is required.',
            'CostPrice.numeric' => 'Current Price must be a valid number.',
            'CostPrice.min' => 'Current Price must be greater than ₱0.00.',
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
                'PONumber' => 'PO-' . now()->format('Y') . '-' . str_pad((string) $purchaseOrder->PurchaseOrderID, 6, '0', STR_PAD_LEFT),
            ]);

            // The admin's entered Current Price — never re-derived from the
            // product/supplier's own stored cost, which is shown here only
            // as the read-only "Previous Price" for reference. Existing POs
            // are untouched; this only sets THIS item's own frozen cost.
            PurchaseOrderItem::create([
                'PurchaseOrderID' => $purchaseOrder->PurchaseOrderID,
                'ProductID' => $product->ProductID,
                'Quantity' => $data['OrderQuantity'],
                'CostPriceAtOrder' => $data['CostPrice'],
            ]);

            return $purchaseOrder;
        });

        $supplier = Supplier::find($data['SupplierID']);
        ActivityLog::record('purchase_order.created', "Created PO #{$purchaseOrder->PONumber} for \"{$supplier?->SupplierName}\" from a low-stock reorder");

        return redirect()->route('admin.purchase-orders.index')->with('success', "Purchase order {$purchaseOrder->PONumber} created successfully.");
    }

    // Suggested reorder quantity shared by the manual reorder form above and
    // the automatic low-stock draft below: brings stock up to 2x the reorder
    // threshold — replenish whatever deficit exists below the threshold,
    // then add one more threshold's worth as a buffer so the item doesn't
    // immediately re-enter "Replenish" the moment this order arrives.
    public static function suggestedReorderQuantity(int $quantity, int $threshold): int
    {
        return max($threshold - $quantity, 0) + $threshold;
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
            if (! $product) {
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
                        $query->whereNotIn('Status', [PurchaseOrder::STATUS_FULLY_RECEIVED, PurchaseOrder::STATUS_CANCELLED]);
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

                $suggestedQuantity = self::suggestedReorderQuantity((int) $locked->Quantity, (int) ($locked->ReorderThreshold ?? 50));
                $costPrice = $resolvedSupplier->CostPrice ?? $product->CostPrice;

                $purchaseOrder = PurchaseOrder::create([
                    'PurchaseDate' => now()->toDateString(),
                    'Notes' => "Automatically drafted \u{2014} stock status is \"{$status['label']}\".",
                    'Status' => PurchaseOrder::STATUS_DRAFT,
                    'SupplierID' => $resolvedSupplier->SupplierID,
                    'CreatedBy' => null,
                ]);

                $purchaseOrder->update([
                    'PONumber' => 'PO-' . now()->format('Y') . '-' . str_pad((string) $purchaseOrder->PurchaseOrderID, 6, '0', STR_PAD_LEFT),
                ]);

                PurchaseOrderItem::create([
                    'PurchaseOrderID' => $purchaseOrder->PurchaseOrderID,
                    'ProductID' => $product->ProductID,
                    'Quantity' => $suggestedQuantity,
                    'CostPriceAtOrder' => $costPrice,
                ]);

                $locked->update(['AutoReorderTriggered' => true]);

                return $purchaseOrder;
            });

            if ($purchaseOrder) {
                ActivityLog::record('purchase_order.auto_drafted', "Automatically drafted PO #{$purchaseOrder->PONumber} for \"{$product->ProductName}\" (Low Stock)");
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
                $locked = PurchaseOrder::where('PurchaseOrderID', $purchaseOrder->PurchaseOrderID)
                    ->where('Status', PurchaseOrder::STATUS_DRAFT)
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    return;
                }

                $locked->update(['Status' => PurchaseOrder::STATUS_PENDING]);

                StockReceivingBatch::firstOrCreate(
                    ['PurchaseOrderID' => $locked->PurchaseOrderID],
                    ['Status' => StockReceivingBatch::STATUS_PENDING]
                );

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
