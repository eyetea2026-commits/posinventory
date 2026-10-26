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
            // Newest supplier first.
            ->orderByDesc('SupplierID')
            ->paginate(15)
            ->withQueryString();

        return view('admin.suppliers.index', [
            'suppliers' => $suppliers,
            'search' => $search,
        ]);
    }

    public function create(Request $request)
    {
        // Add Supplier modal: return just the rendered form fields (the
        // same shared partial edit() already uses, with no $supplier)
        // instead of a full page, so the modal can inject it without
        // navigating. Direct navigation to this URL still gets the full
        // standalone page.
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'html' => view('admin.suppliers.partials.supplier-form-fields')->render(),
            ]);
        }

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

    /**
     * Contact Number: up to three numbers separated by " / ", each a mobile
     * (0912-345-6789) or a landline ((02) 8123-4567, (082) 295-7595).
     * Letters and other characters are dropped first (normalizeContactNumber()).
     */
    private const CONTACT_NUMBER_RULES = [
        'required', 'string', 'max:50',
        'regex:/^(09\d{2}-\d{3}-\d{4}|\(02\) \d{4}-\d{4}|\(0[1-8]\d\) \d{3}-\d{4})( \/ (09\d{2}-\d{3}-\d{4}|\(02\) \d{4}-\d{4}|\(0[1-8]\d\) \d{3}-\d{4})){0,2}$/',
    ];

    private const CONTACT_NUMBER_MESSAGES = [
        'ContactNumber.required' => 'Contact Number is required.',
        'ContactNumber.max' => 'Enter at most three contact numbers.',
        'ContactNumber.regex' => 'Enter a mobile number as 0912-345-6789 or a landline as (02) 8123-4567. Separate two numbers with "/".',
    ];

    /**
     * Each number (split on "/") keeps only its digits, then is written as
     * 0912-345-6789 (mobile; +63 becomes the leading 0), (02) 8123-4567
     * (Metro Manila landline) or (082) 295-7595 (provincial landline).
     */
    private static function normalizeContactNumber(?string $value): string
    {
        $numbers = [];
        foreach (preg_split('/[\/,;]/', (string) $value) as $part) {
            $digits = preg_replace('/\D/', '', $part);
            if ($digits === '') {
                continue;
            }
            if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
                $digits = '0'.substr($digits, 2);
            }

            $numbers[] = match (true) {
                strlen($digits) === 11 && str_starts_with($digits, '09') => substr($digits, 0, 4).'-'.substr($digits, 4, 3).'-'.substr($digits, 7),
                strlen($digits) === 10 && str_starts_with($digits, '02') => '(02) '.substr($digits, 2, 4).'-'.substr($digits, 6),
                strlen($digits) === 10 && str_starts_with($digits, '0') && ! str_starts_with($digits, '09') => '('.substr($digits, 0, 3).') '.substr($digits, 3, 3).'-'.substr($digits, 6),
                default => $digits,
            };
        }

        return implode(' / ', $numbers);
    }

    public function store(Request $request)
    {
        $request->merge(['ContactNumber' => self::normalizeContactNumber($request->input('ContactNumber'))]);

        $data = $request->validate([
            'SupplierName' => ['required', 'string', 'max:150', 'unique:Supplier,SupplierName'],
            'ContactPerson' => ['nullable', 'string', 'max:150'],
            'ContactNumber' => self::CONTACT_NUMBER_RULES,
            'Email' => ['nullable', 'email', 'max:100', 'unique:Supplier,Email'],
            'Address' => ['required', 'string', 'max:255'],
            'Status' => ['nullable', 'string', 'in:'.Supplier::STATUS_ACTIVE.','.Supplier::STATUS_INACTIVE],
        ], self::CONTACT_NUMBER_MESSAGES);

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
    // (Category/ordered/received/remaining/cost/subtotal), an order
    // summary, and a best-effort audit trail from ActivityLog.
    public function purchaseOrderDetails(Supplier $supplier, PurchaseOrder $purchaseOrder)
    {
        abort_unless((int) $purchaseOrder->SupplierID === (int) $supplier->SupplierID, 404);

        $purchaseOrder->load(['items.product.category', 'createdByUser', 'supplier', 'stockReceivings']);

        $items = $purchaseOrder->items->map(function ($item) {
            return [
                'ProductName' => $item->product?->ProductName ?? 'N/A',
                'Category' => $item->product?->category?->CategoryName ?? 'N/A',
                'OrderedQuantity' => $item->Quantity,
                'ReceivedQuantity' => $item->ReceivedQuantity,
                'RemainingQuantity' => $item->remaining_quantity,
                // The same in the unit it was ordered in ("2 rolls (10,000 meters)").
                'OrderedLabel' => $item->tableQuantity(),
                'ReceivedLabel' => $item->tableQuantity($item->ReceivedQuantity),
                'RemainingLabel' => $item->tableQuantity($item->remaining_quantity),
                'UnitCost' => $item->CostPriceAtOrder,
                'Subtotal' => $item->Quantity * $item->CostPriceAtOrder,
            ];
        });

        $lastReceipt = $purchaseOrder->stockReceivings->max('DateReceived');

        $auditHistory = ActivityLog::where('Description', 'like', "%{$purchaseOrder->PONumber}%")
            ->orderByDesc('DateRecorded')
            ->get(['Action', 'Description', 'DateRecorded'])
            // Shown as-is in the details modal, so send a readable MM/DD/YYYY time.
            ->map(fn (ActivityLog $log) => [
                'Action' => $log->Action,
                'Description' => $log->Description,
                'DateRecorded' => $log->DateRecorded?->format('m/d/Y h:i A'),
            ]);

        return response()->json([
            'purchaseOrder' => [
                'PONumber' => $purchaseOrder->PONumber,
                'PurchaseDate' => Carbon::parse($purchaseOrder->PurchaseDate)->format('m/d/Y'),
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
                'ExpectedDeliveryDate' => $purchaseOrder->ExpectedDeliveryDate ? Carbon::parse($purchaseOrder->ExpectedDeliveryDate)->format('m/d/Y') : null,
                'ReceivedDate' => $lastReceipt ? Carbon::parse($lastReceipt)->format('m/d/Y') : null,
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

        if ($po->Status === PurchaseOrder::STATUS_CLOSED) {
            return 'Closed';
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
        $request->merge(['ContactNumber' => self::normalizeContactNumber($request->input('ContactNumber'))]);

        $data = $request->validate([
            'SupplierName' => ['required', 'string', 'max:150', 'unique:Supplier,SupplierName,'.$supplier->SupplierID.',SupplierID'],
            'ContactPerson' => ['nullable', 'string', 'max:150'],
            'ContactNumber' => self::CONTACT_NUMBER_RULES,
            'Email' => ['nullable', 'email', 'max:100', 'unique:Supplier,Email,'.$supplier->SupplierID.',SupplierID'],
            'Address' => ['required', 'string', 'max:255'],
            'Status' => ['nullable', 'string', 'in:'.Supplier::STATUS_ACTIVE.','.Supplier::STATUS_INACTIVE],
        ], self::CONTACT_NUMBER_MESSAGES);

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
