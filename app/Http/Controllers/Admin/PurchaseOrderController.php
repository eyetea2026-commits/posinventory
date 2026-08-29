<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
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

        // Bring stock up to 2x the reorder threshold: replenish whatever
        // deficit exists below the threshold, then add one more threshold's
        // worth as a buffer so the item doesn't immediately re-enter
        // "Replenish" the moment this order arrives.
        $suggestedQuantity = max($threshold - $quantity, 0) + $threshold;

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
            'Status' => ['required', 'string', 'in:' . implode(',', PurchaseOrder::EDITABLE_STATUSES)],
            'Notes' => ['nullable', 'string', 'max:1000'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'integer', 'exists:Product,ProductID'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
            'products.*.cost_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $purchaseOrder = DB::transaction(function () use ($data) {
            $purchaseOrder = PurchaseOrder::create([
                'PurchaseDate' => $data['PurchaseDate'],
                'ExpectedDeliveryDate' => $data['ExpectedDeliveryDate'] ?? null,
                'Notes' => $data['Notes'] ?? null,
                'Status' => $data['Status'],
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

                $product = Product::find($item['product_id']);
                $costPrice = $item['cost_price'] ?? $product?->CostPrice ?? 0;

                PurchaseOrderItem::create([
                    'PurchaseOrderID' => $purchaseOrder->PurchaseOrderID,
                    'ProductID' => $item['product_id'],
                    'Quantity' => $item['quantity'],
                    'CostPriceAtOrder' => $costPrice,
                ]);
            }

            return $purchaseOrder;
        });

        $supplier = Supplier::find($data['SupplierID']);
        ActivityLog::record('purchase_order.created', "Created PO #{$purchaseOrder->PONumber} for \"{$supplier?->SupplierName}\"");

        return redirect()->route('admin.purchase-orders.index')->with('success', "Purchase order {$purchaseOrder->PONumber} created successfully.");
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
            'Remarks' => ['nullable', 'string', 'max:1000'],
            'SupplierID' => $knownSupplierIds
                ? ['required', 'integer', Rule::in($knownSupplierIds)]
                : ['required', 'integer', 'exists:Supplier,SupplierID'],
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
                'Status' => PurchaseOrder::STATUS_PENDING,
                'SupplierID' => $data['SupplierID'],
                'CreatedBy' => auth()->id(),
            ]);

            $purchaseOrder->update([
                'PONumber' => 'PO-' . now()->format('Y') . '-' . str_pad((string) $purchaseOrder->PurchaseOrderID, 6, '0', STR_PAD_LEFT),
            ]);

            PurchaseOrderItem::create([
                'PurchaseOrderID' => $purchaseOrder->PurchaseOrderID,
                'ProductID' => $product->ProductID,
                'Quantity' => $data['OrderQuantity'],
                'CostPriceAtOrder' => $productSupplier->CostPrice ?? $product->CostPrice,
            ]);

            return $purchaseOrder;
        });

        $supplier = Supplier::find($data['SupplierID']);
        ActivityLog::record('purchase_order.created', "Created PO #{$purchaseOrder->PONumber} for \"{$supplier?->SupplierName}\" from a low-stock reorder");

        return redirect()->route('admin.purchase-orders.index')->with('success', "Purchase order {$purchaseOrder->PONumber} created successfully.");
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

        try {
            $purchaseOrder->update(['Status' => PurchaseOrder::STATUS_PENDING]);
            ActivityLog::record('purchase_order.submitted', "Submitted PO #{$purchaseOrder->PONumber}");
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
            $purchaseOrder->update(['Status' => PurchaseOrder::STATUS_APPROVED, 'ApprovedBy' => auth()->id()]);
            ActivityLog::record('purchase_order.approved', "Approved PO #{$purchaseOrder->PONumber}");
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
    public function printPreview(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'items.product', 'createdByUser', 'approvedByUser']);

        return view('admin.purchase-orders.print', ['purchaseOrder' => $purchaseOrder]);
    }
}
