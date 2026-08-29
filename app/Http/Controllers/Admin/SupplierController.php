<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\SupplierUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SupplierController extends Controller
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

        $suppliers = Supplier::when($search, function ($query, $search) {
                $query->where('SupplierName', 'like', "%{$search}%")
                    ->orWhere('Email', 'like', "%{$search}%")
                    ->orWhere('ContactNumber', 'like', "%{$search}%");
            })
            ->orderBy('SupplierName')
            ->paginate(15)
            ->withQueryString();

        return view('admin.suppliers.index', [
            'suppliers' => $suppliers,
            'search' => $search,
        ]);
    }

    public function create()
    {
        return view('admin.suppliers.create');
    }

    // Live duplicate-name/email check (mirrors ProductController::checkName)
    // — SupplierName and Email are both unique constraints on store/update,
    // so both get checked here rather than only surfacing the conflict
    // after a full submit.
    public function checkName(Request $request)
    {
        $name = trim((string) $request->input('SupplierName', ''));
        $email = trim((string) $request->input('Email', ''));
        $excludeId = $request->input('exclude_id');

        $candidates = Supplier::when($excludeId, function ($query, $excludeId) {
            $query->where('SupplierID', '!=', $excludeId);
        })->get();

        $normalize = function (string $value): string {
            return preg_replace('/\s+/', ' ', strtolower($value));
        };

        $nameTaken = false;
        if ($name !== '') {
            $normalized = $normalize($name);
            $nameTaken = $candidates->contains(function ($existing) use ($normalize, $normalized) {
                return $normalize((string) ($existing->SupplierName ?? '')) === $normalized;
            });
        }

        $emailTaken = false;
        if ($email !== '') {
            $normalized = strtolower($email);
            $emailTaken = $candidates->contains(function ($existing) use ($normalized) {
                return strtolower((string) ($existing->Email ?? '')) === $normalized;
            });
        }

        return response()->json([
            'name' => $nameTaken,
            'email' => $emailTaken,
            'name_value' => $name,
            'email_value' => $email,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'SupplierName' => ['required', 'string', 'max:150', 'unique:Supplier,SupplierName'],
            'ContactPerson' => ['nullable', 'string', 'max:150'],
            'ContactNumber' => ['required', 'string', 'max:50', 'regex:/^[0-9+\-\s()]{7,50}$/'],
            'Email' => ['required', 'email', 'max:150', 'unique:Supplier,Email'],
            'Address' => ['required', 'string', 'max:255'],
            'Status' => ['nullable', 'string', 'in:' . Supplier::STATUS_ACTIVE . ',' . Supplier::STATUS_INACTIVE],
        ]);

        $supplier = Supplier::create($data);

        ActivityLog::record('supplier.created', "Added supplier \"{$supplier->SupplierName}\"");

        try {
            Notification::send(User::admins(), new SupplierUpdated($supplier, 'Created'));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch SupplierUpdated notification', [
                'supplier_id' => $supplier->SupplierID,
                'exception' => $e->getMessage(),
            ]);
        }

        return redirect()->route('admin.suppliers.index')->with('status', 'Supplier added successfully.');
    }

    // "View History" — displays ONLY this supplier's Purchase Order
    // transaction history (searchable, paginated). Deliberately does not
    // show supplier profile info (name/contact/address/status) — that's
    // already visible in the Supplier List this action is reached from.
    public function show(Request $request, Supplier $supplier)
    {
        $search = $request->query('search');

        $purchaseOrders = $supplier->purchaseOrders()
            ->with(['items', 'stockReceivings'])
            ->when($search, fn ($q) => $q->where('PONumber', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $history = $purchaseOrders->getCollection()->map(function (PurchaseOrder $po) {
            $lastReceipt = $po->stockReceivings->max('DateReceived');

            return (object) [
                'purchaseOrder' => $po,
                'productsOrderedCount' => $po->items->count(),
                'totalQuantityOrdered' => $po->items->sum('Quantity'),
                'totalPurchaseAmount' => $po->items->sum(fn ($item) => $item->Quantity * $item->CostPriceAtOrder),
                'deliveryStatus' => $this->resolveDeliveryStatus($po, $lastReceipt),
                'receivedDate' => $lastReceipt,
            ];
        });
        $purchaseOrders->setCollection($history);

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'rows' => view('admin.suppliers.partials.history-rows', ['history' => $purchaseOrders, 'supplier' => $supplier])->render(),
                'pagination' => view('admin.suppliers.partials.history-pagination', ['history' => $purchaseOrders])->render(),
            ]);
        }

        return view('admin.suppliers.show', [
            'supplier' => $supplier,
            'search' => $search,
            'history' => $purchaseOrders,
        ]);
    }

    // Full breakdown of one Purchase Order for the History page's "View
    // Details" modal — Purchase Order info, every ordered product
    // (Category/SKU/ordered/received/remaining/cost/subtotal), an order
    // summary, and a best-effort audit trail from ActivityLog.
    public function purchaseOrderDetails(Supplier $supplier, PurchaseOrder $purchaseOrder)
    {
        abort_unless((int) $purchaseOrder->SupplierID === (int) $supplier->SupplierID, 404);

        $purchaseOrder->load(['items.product.category', 'createdByUser', 'supplier', 'stockReceivings']);

        $items = $purchaseOrder->items->map(function ($item) {
            return [
                'ProductName' => $item->product?->ProductName ?? 'N/A',
                'Category' => $item->product?->category?->CategoryName ?? 'N/A',
                'SKU' => $item->product?->SKU ?? 'N/A',
                'OrderedQuantity' => $item->Quantity,
                'ReceivedQuantity' => $item->ReceivedQuantity,
                'RemainingQuantity' => $item->remaining_quantity,
                'UnitCost' => $item->CostPriceAtOrder,
                'Subtotal' => $item->Quantity * $item->CostPriceAtOrder,
            ];
        });

        $lastReceipt = $purchaseOrder->stockReceivings->max('DateReceived');

        $auditHistory = ActivityLog::where('Description', 'like', "%{$purchaseOrder->PONumber}%")
            ->orderByDesc('DateRecorded')
            ->get(['Action', 'Description', 'DateRecorded']);

        return response()->json([
            'purchaseOrder' => [
                'PONumber' => $purchaseOrder->PONumber,
                'PurchaseDate' => Carbon::parse($purchaseOrder->PurchaseDate)->format('Y-m-d'),
                'Status' => PurchaseOrder::STATUS_LABELS[$purchaseOrder->Status] ?? $purchaseOrder->Status,
                'CreatedBy' => $purchaseOrder->createdByUser?->full_name ?? 'N/A',
                'Notes' => $purchaseOrder->Notes,
            ],
            'supplier' => [
                'SupplierName' => $purchaseOrder->supplier?->SupplierName,
                'ContactNumber' => $purchaseOrder->supplier?->ContactNumber,
                'Email' => $purchaseOrder->supplier?->Email,
            ],
            'items' => $items,
            'summary' => [
                'TotalProducts' => $items->count(),
                'TotalQuantityOrdered' => $items->sum('OrderedQuantity'),
                'TotalPurchaseAmount' => $items->sum('Subtotal'),
            ],
            'delivery' => [
                'ExpectedDeliveryDate' => $purchaseOrder->ExpectedDeliveryDate ? Carbon::parse($purchaseOrder->ExpectedDeliveryDate)->format('Y-m-d') : null,
                'ReceivedDate' => $lastReceipt ? Carbon::parse($lastReceipt)->format('Y-m-d') : null,
                'DeliveryStatus' => $this->resolveDeliveryStatus($purchaseOrder, $lastReceipt),
            ],
            'auditHistory' => $auditHistory,
        ]);
    }

    private function resolveDeliveryStatus(PurchaseOrder $po, $lastReceipt): string
    {
        if ($po->Status === PurchaseOrder::STATUS_CANCELLED) {
            return 'Cancelled';
        }

        if ($po->Status === PurchaseOrder::STATUS_FULLY_RECEIVED) {
            if ($po->ExpectedDeliveryDate && $lastReceipt && Carbon::parse($lastReceipt)->gt(Carbon::parse($po->ExpectedDeliveryDate))) {
                return 'Late';
            }

            return 'On Time';
        }

        if ($po->Status === PurchaseOrder::STATUS_PARTIALLY_RECEIVED) {
            return 'Partial';
        }

        return 'Awaiting Delivery';
    }

    public function edit(Request $request, Supplier $supplier)
    {
        // Edit Supplier modal: return just the rendered form fields instead
        // of a full page, so the modal can inject it without navigating.
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'html' => view('admin.suppliers.partials.supplier-form-fields', [
                    'supplier' => $supplier,
                ])->render(),
            ]);
        }

        return view('admin.suppliers.edit', [
            'supplier' => $supplier,
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'SupplierName' => ['required', 'string', 'max:150', 'unique:Supplier,SupplierName,' . $supplier->SupplierID . ',SupplierID'],
            'ContactPerson' => ['nullable', 'string', 'max:150'],
            'ContactNumber' => ['required', 'string', 'max:50', 'regex:/^[0-9+\-\s()]{7,50}$/'],
            'Email' => ['required', 'email', 'max:150', 'unique:Supplier,Email,' . $supplier->SupplierID . ',SupplierID'],
            'Address' => ['required', 'string', 'max:255'],
            'Status' => ['nullable', 'string', 'in:' . Supplier::STATUS_ACTIVE . ',' . Supplier::STATUS_INACTIVE],
        ]);

        $supplier->update($data);

        ActivityLog::record('supplier.updated', "Updated supplier \"{$supplier->SupplierName}\"");

        try {
            Notification::send(User::admins(), new SupplierUpdated($supplier, 'Updated'));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch SupplierUpdated notification', [
                'supplier_id' => $supplier->SupplierID,
                'exception' => $e->getMessage(),
            ]);
        }

        return redirect()->route('admin.suppliers.index')->with('status', 'Supplier updated successfully.');
    }
}
