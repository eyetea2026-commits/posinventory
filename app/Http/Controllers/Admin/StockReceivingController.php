<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockReceiving;
use App\Models\StockReceivingBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\ProductReceived;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class StockReceivingController extends Controller
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

    // The legacy per-item StockReceiving list/search (a flat manual-entry
    // audit log) no longer has a view to render it in — this page now shows
    // only the Purchase Order Deliveries tabs below. The StockReceiving
    // table, create()/store() (still reachable directly), and the "ad hoc"
    // manual-entry business logic are all untouched; only this now-unused
    // read query is gone.
    public function index()
    {
        $pendingBatches = StockReceivingBatch::with(['purchaseOrder.supplier', 'purchaseOrder.items.product'])
            ->where('Status', StockReceivingBatch::STATUS_PENDING)
            ->orderByDesc('created_at')
            ->get();

        $completedBatches = StockReceivingBatch::with(['purchaseOrder.supplier', 'purchaseOrder.items.product', 'receivedByUser'])
            ->where('Status', StockReceivingBatch::STATUS_COMPLETED)
            ->orderByDesc('CompletedAt')
            ->get();

        return view('admin.stock-receivings.index', [
            'pendingBatches' => $pendingBatches,
            'completedBatches' => $completedBatches,
        ]);
    }

    // AJAX View Details for a Pending/Completed batch — the PO's own items
    // (Product, ordered Quantity read-only, ReceivedQuantity and
    // ReceiptNumber editable) plus the "Add to Inventory" button's target.
    public function showBatch(StockReceivingBatch $stockReceivingBatch)
    {
        $stockReceivingBatch->load(['purchaseOrder.supplier', 'purchaseOrder.items.product']);

        return response()->json([
            'html' => view('admin.stock-receivings.partials.batch-details', [
                'batch' => $stockReceivingBatch,
            ])->render(),
            // Lets the modal shell's own static "Add to Inventory" button
            // (kept there, alongside Close, rather than rendered inside the
            // fetched partial) know whether to show itself.
            'isPending' => $stockReceivingBatch->Status === StockReceivingBatch::STATUS_PENDING,
        ]);
    }

    // The core of the Pending -> Completed workflow. Everything below runs
    // in one transaction: if any line fails validation or any write throws,
    // the whole thing rolls back — the batch stays exactly Pending, nothing
    // in Inventory is partially updated, and the record stays visible in
    // the Pending / Expected Delivery tab untouched. Only once every line
    // has been written does the batch flip to Completed and the linked
    // Purchase Order's own Status advance.
    public function addToInventory(Request $request, StockReceivingBatch $stockReceivingBatch)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'exists:PurchaseOrderItem,PurchaseOrderItemID'],
            'items.*.quantity_received' => ['required', 'integer', 'min:0'],
            'items.*.receipt_number' => ['nullable', 'string', 'max:50'],
        ]);

        // Receipt Number must be unique — both within this submission (two
        // lines can't share one) and against every delivery ever recorded.
        // Checked up front so a duplicate is reported before anything is
        // written, matching the "validate everything, then one transaction"
        // pattern used throughout this codebase.
        $receiptNumbers = collect($data['items'])
            ->pluck('receipt_number')
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => trim($value));

        $duplicateInSubmission = $receiptNumbers->duplicates()->first();
        if ($duplicateInSubmission !== null) {
            return back()->with('error', "Receipt Number \"{$duplicateInSubmission}\" is used more than once here — each line needs its own unique Receipt Number.");
        }

        if ($receiptNumbers->isNotEmpty()) {
            $alreadyUsed = PurchaseOrderItem::whereIn('ReceiptNumber', $receiptNumbers->unique())->first();
            if ($alreadyUsed) {
                return back()->with('error', "Receipt Number \"{$alreadyUsed->ReceiptNumber}\" has already been used for another delivery.");
            }
        }

        try {
            DB::transaction(function () use ($data, $stockReceivingBatch) {
                $lockedBatch = StockReceivingBatch::where('StockReceivingBatchID', $stockReceivingBatch->StockReceivingBatchID)
                    ->where('Status', StockReceivingBatch::STATUS_PENDING)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedBatch) {
                    throw new \RuntimeException('This delivery has already been completed or no longer exists.');
                }

                $purchaseOrder = PurchaseOrder::where('PurchaseOrderID', $lockedBatch->PurchaseOrderID)
                    ->lockForUpdate()
                    ->first();

                // A Purchase Order can be Cancelled after its batch was
                // already created (the batch has no status of its own tied
                // to the PO's) — re-verify under the same lock that the PO
                // is still receivable, or a Cancelled order could be
                // silently resurrected and stocked, mirroring the same
                // guard store() already has for the legacy manual flow.
                if (! $purchaseOrder || $purchaseOrder->Status !== PurchaseOrder::STATUS_PENDING) {
                    throw new \RuntimeException('This purchase order is no longer pending receipt — it may have been cancelled.');
                }

                foreach ($data['items'] as $row) {
                    $item = PurchaseOrderItem::where('PurchaseOrderItemID', $row['purchase_order_item_id'])
                        ->where('PurchaseOrderID', $purchaseOrder->PurchaseOrderID)
                        ->lockForUpdate()
                        ->first();

                    if (! $item) {
                        throw new \RuntimeException('That order line no longer belongs to this purchase order.');
                    }

                    $quantityReceived = (int) $row['quantity_received'];

                    if ($quantityReceived > $item->Quantity) {
                        throw new \RuntimeException("Quantity Received for \"{$item->product?->ProductName}\" cannot exceed the ordered quantity of {$item->Quantity}.");
                    }

                    $item->update([
                        'ReceivedQuantity' => $quantityReceived,
                        'ReceiptNumber' => $row['receipt_number'] ?? null,
                    ]);

                    if ($quantityReceived <= 0) {
                        continue;
                    }

                    // Locked, computed, and saved entirely inside the
                    // transaction — the same TOCTOU-safe pattern already
                    // used by the legacy store() below and Stock Adjustment.
                    $inventory = Inventory::where('ProductID', $item->ProductID)->lockForUpdate()->first();
                    if (! $inventory) {
                        Inventory::firstOrCreate(['ProductID' => $item->ProductID], ['Quantity' => 0, 'Status' => 'Out of Stock']);
                        $inventory = Inventory::where('ProductID', $item->ProductID)->lockForUpdate()->first();
                    }

                    // Only the ACTUAL received amount is added to Inventory
                    // — never the originally ordered Quantity, which stays
                    // untouched on the PurchaseOrderItem as the reference.
                    $inventory->Quantity += $quantityReceived;
                    $inventory->Status = Inventory::resolveStatus($inventory->Quantity, $inventory->ReorderThreshold);
                    $inventory->save();
                }

                $lockedBatch->update([
                    'Status' => StockReceivingBatch::STATUS_COMPLETED,
                    'ReceivedBy' => auth()->id(),
                    'CompletedAt' => now(),
                ]);

                $purchaseOrder->load('items');
                $purchaseOrder->update([
                    'Status' => $purchaseOrder->isFullyReceived()
                        ? PurchaseOrder::STATUS_FULLY_RECEIVED
                        : PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                ]);

                ActivityLog::record('stock_receiving_batch.completed', "Completed receiving for PO #{$purchaseOrder->PONumber}");
            });
        } catch (QueryException $e) {
            // DB-level backstop for the same duplicate-Receipt-Number case
            // already checked above — catches a genuine race between two
            // concurrent completions, not the normal path. Must be caught
            // before \RuntimeException below, since QueryException extends it.
            if ($e->getCode() === '23000') {
                return back()->with('error', 'That Receipt Number has already been used for another delivery.');
            }

            Log::error('Failed to complete stock receiving batch', [
                'batch_id' => $stockReceivingBatch->StockReceivingBatchID,
                'exception' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to add received stock to Inventory. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Failed to complete stock receiving batch', [
                'batch_id' => $stockReceivingBatch->StockReceivingBatchID,
                'exception' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to add received stock to Inventory. Please try again.');
        }

        return redirect()->route('admin.stock-receivings.index')->with('success', 'Stock added to Inventory. This delivery is now marked Completed.');
    }

    // Live duplicate-check used by both Receipt Number entry points: the
    // manual "Record Receipt" form (checks as the admin types) and the
    // "Add to Inventory" batch table (checks each line). Searches both
    // tables a Receipt Number can actually land in — StockReceiving (the
    // manual flow) and PurchaseOrderItem (the batch flow) — since those two
    // uniqueness domains aren't otherwise cross-checked against each other,
    // "already used" needs to mean used anywhere, not just in whichever
    // table the calling form itself writes to.
    public function checkReceiptNumber(Request $request)
    {
        $receiptNumber = trim((string) $request->input('receipt_number', ''));
        $excludeItemId = $request->input('exclude_purchase_order_item_id');

        $used = false;
        if ($receiptNumber !== '') {
            $used = StockReceiving::where('ReceiptNumber', $receiptNumber)->exists()
                || PurchaseOrderItem::where('ReceiptNumber', $receiptNumber)
                    ->when($excludeItemId, function ($query, $excludeItemId) {
                        return $query->where('PurchaseOrderItemID', '!=', $excludeItemId);
                    })
                    ->exists();
        }

        return response()->json([
            'used' => $used,
            'receipt_number' => $receiptNumber,
        ]);
    }

    public function create(Request $request)
    {
        $purchaseOrder = null;

        if ($request->query('purchase_order_id')) {
            $purchaseOrder = PurchaseOrder::with('items.product', 'supplier')
                ->whereIn('Status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])
                ->find($request->query('purchase_order_id'));

            if (! $purchaseOrder) {
                return redirect()->route('admin.purchase-orders.index')
                    ->with('error', 'That purchase order is not approved/receivable, or does not exist.');
            }
        }

        return view('admin.stock-receivings.create', [
            'products' => Product::orderBy('ProductName')->get(),
            'suppliers' => Supplier::orderBy('SupplierName')->get(),
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_order_id' => ['nullable', 'integer', 'exists:PurchaseOrder,PurchaseOrderID'],
            'purchase_order_item_id' => ['nullable', 'integer', 'exists:PurchaseOrderItem,PurchaseOrderItemID', 'required_with:purchase_order_id'],
            'ProductID' => ['required_without:purchase_order_id', 'nullable', 'integer', 'exists:Product,ProductID'],
            'SupplierID' => ['required_without:purchase_order_id', 'nullable', 'integer', 'exists:Supplier,SupplierID'],
            'Quantity' => ['required', 'integer', 'min:1'],
            'ReceiptNumber' => ['required', 'string', 'max:50', 'unique:StockReceiving,ReceiptNumber'],
            'DateReceived' => ['required', 'date'],
        ]);

        try {
            DB::transaction(function () use ($data) {
                $purchaseOrderItem = null;
                $productId = $data['ProductID'] ?? null;
                $supplierId = $data['SupplierID'] ?? null;

                if (! empty($data['purchase_order_item_id'])) {
                    $purchaseOrderItem = PurchaseOrderItem::where('PurchaseOrderItemID', $data['purchase_order_item_id'])
                        ->lockForUpdate()
                        ->first();

                    if (! $purchaseOrderItem || $purchaseOrderItem->PurchaseOrderID != $data['purchase_order_id']) {
                        throw new \RuntimeException('That order line no longer belongs to this purchase order.');
                    }

                    // The picker only ever offers approved/partially-received
                    // orders, but that was never re-checked server-side — a
                    // PO cancelled after the form was opened (or edited
                    // directly) could still be received against, silently
                    // resurrecting it to partially/fully received and
                    // violating "a cancelled order can never receive stock".
                    $purchaseOrder = PurchaseOrder::where('PurchaseOrderID', $data['purchase_order_id'])
                        ->lockForUpdate()
                        ->first();

                    if (! $purchaseOrder || ! in_array($purchaseOrder->Status, [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED], true)) {
                        throw new \RuntimeException('This purchase order is no longer approved/receivable — it may have been cancelled or already fully received.');
                    }

                    if ($purchaseOrderItem->ReceivedQuantity + $data['Quantity'] > $purchaseOrderItem->Quantity) {
                        throw new \RuntimeException("Cannot receive more than the remaining {$purchaseOrderItem->remaining_quantity} unit(s) for this line.");
                    }

                    $productId = $purchaseOrderItem->ProductID;
                    $supplierId = $purchaseOrder->SupplierID;
                }

                StockReceiving::create([
                    'ProductID' => $productId,
                    'SupplierID' => $supplierId,
                    'Quantity' => $data['Quantity'],
                    'ReceiptNumber' => $data['ReceiptNumber'],
                    'DateReceived' => $data['DateReceived'],
                    'PurchaseOrderID' => $data['purchase_order_id'] ?? null,
                    'PurchaseOrderItemID' => $data['purchase_order_item_id'] ?? null,
                ]);

                // Locked, computed, and saved entirely inside the
                // transaction — this used to read/write the Inventory row
                // without a lock, leaving the same TOCTOU race fixed
                // elsewhere in Stock Adjustment.
                $inventory = Inventory::where('ProductID', $productId)->lockForUpdate()->first();
                if (! $inventory) {
                    Inventory::firstOrCreate(['ProductID' => $productId], ['Quantity' => 0, 'Status' => 'Out of Stock']);
                    $inventory = Inventory::where('ProductID', $productId)->lockForUpdate()->first();
                }

                $inventory->Quantity += $data['Quantity'];
                $inventory->Status = Inventory::resolveStatus($inventory->Quantity, $inventory->ReorderThreshold);
                $inventory->save();

                if ($purchaseOrderItem) {
                    $purchaseOrderItem->increment('ReceivedQuantity', $data['Quantity']);

                    // Reuse the same locked $purchaseOrder from the status
                    // guard above rather than re-querying it.
                    $purchaseOrder->load('items');
                    $purchaseOrder->update([
                        'Status' => $purchaseOrder->isFullyReceived() ? PurchaseOrder::STATUS_FULLY_RECEIVED : PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        $product = Product::find($data['ProductID'] ?? PurchaseOrderItem::find($data['purchase_order_item_id'] ?? 0)?->ProductID);
        $supplier = Supplier::find($data['SupplierID'] ?? PurchaseOrder::find($data['purchase_order_id'] ?? 0)?->SupplierID);
        $productName = $product?->ProductName ?? 'Unknown product';
        $supplierName = $supplier?->SupplierName ?? 'Unknown supplier';
        $poSuffix = ! empty($data['purchase_order_id']) ? " (PO {$data['purchase_order_id']})" : '';
        ActivityLog::record('stock.received', "Received {$data['Quantity']} x \"{$productName}\" from \"{$supplierName}\"{$poSuffix}");

        // The receiving record itself already committed above — a
        // notification failure (broken mail transport, queue connection
        // down) must not turn a successful receipt into a 500 response.
        if ($product && $supplier) {
            try {
                Notification::send(User::admins(), new ProductReceived($product, $supplier, (int) $data['Quantity']));
            } catch (Throwable $e) {
                Log::error('Failed to dispatch ProductReceived notification', [
                    'product_id' => $product->ProductID,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('admin.stock-receivings.index')->with('success', 'Stock receiving recorded successfully.');
    }
}
