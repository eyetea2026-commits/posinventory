<?php

namespace App\Http\Controllers\Admin;

use App\Casts\Quantity;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockReceiving;
use App\Models\StockReceivingBatch;
use App\Models\StockReceivingDelivery;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\DeliveryDue;
use App\Notifications\DeliveryReceived;
use App\Notifications\ProductReceived;
use App\Services\UnitOfMeasure;
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
            // Both tables render every batch up front (no pagination), so
            // search/supplier filtering is applied client-side over this
            // same data rather than round-tripping to the server — see the
            // JS in the view for the actual filter logic.
            'suppliers' => Supplier::orderBy('SupplierName')->get(),
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
            // For a line ordered by the roll/box: how many meters/pieces
            // each one holds in this delivery (1 roll = 1,000 meters).
            'items.*.quantity_per_unit' => ['nullable', 'numeric', 'gt:0', 'max:9999999', 'decimal:0,2'],
            // One Receipt Number for the whole delivery (every product on
            // this Purchase Order), not one per product line. Required.
            'receipt_number' => ['required', 'string', 'max:50'],
        ], [
            'receipt_number.required' => 'Enter the Receipt Number of this delivery.',
        ]);

        // Must not match any other delivery. Checked up front so a duplicate
        // is reported before anything is written.
        $receiptNumber = filled($data['receipt_number'] ?? null) ? trim($data['receipt_number']) : null;

        if ($receiptNumber !== null && self::receiptNumberIsUsed($receiptNumber, $stockReceivingBatch->StockReceivingBatchID)) {
            return back()->with('error', "Receipt Number \"{$receiptNumber}\" has already been used for another delivery.");
        }

        $orderStillOpen = false;

        try {
            DB::transaction(function () use ($data, $stockReceivingBatch, $receiptNumber, &$orderStillOpen) {
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
                // Pending (nothing received yet) or Partially Received (an
                // earlier delivery was short): both still expect stock.
                if (! $purchaseOrder || ! in_array($purchaseOrder->Status, [PurchaseOrder::STATUS_PENDING, PurchaseOrder::STATUS_PARTIALLY_RECEIVED], true)) {
                    throw new \RuntimeException('This purchase order is no longer expecting a delivery — it may have been cancelled or closed.');
                }

                $delivery = StockReceivingDelivery::create([
                    'StockReceivingBatchID' => $lockedBatch->StockReceivingBatchID,
                    'PurchaseOrderID' => $purchaseOrder->PurchaseOrderID,
                    'ReceiptNumber' => $receiptNumber,
                    'ReceivedBy' => auth()->id(),
                    'ReceivedAt' => now(),
                ]);
                $receivedNow = 0;

                foreach ($data['items'] as $row) {
                    $item = PurchaseOrderItem::where('PurchaseOrderItemID', $row['purchase_order_item_id'])
                        ->where('PurchaseOrderID', $purchaseOrder->PurchaseOrderID)
                        ->lockForUpdate()
                        ->first();

                    if (! $item) {
                        throw new \RuntimeException('That order line no longer belongs to this purchase order.');
                    }

                    // What arrived in THIS delivery; added to what earlier
                    // deliveries brought, never more than is still owed.
                    $quantityReceived = (int) $row['quantity_received'];

                    if ($quantityReceived > $item->remaining_quantity) {
                        throw new \RuntimeException("Only {$item->remaining_quantity} more \"{$item->product?->ProductName}\" can be received on this order (ordered {$item->Quantity}, received {$item->ReceivedQuantity} so far".((int) $item->CancelledQuantity > 0 ? ", cancelled {$item->CancelledQuantity}" : '').').');
                    }

                    if ($quantityReceived <= 0) {
                        continue;
                    }

                    // Ordered by the roll/box: its size is entered here, for
                    // this delivery, and kept as the product's last roll size.
                    $product = $item->product;
                    if ($product && ($item->Unit ?: UnitOfMeasure::DEFAULT) !== $product->baseUnit()) {
                        $size = Quantity::normalize($row['quantity_per_unit'] ?? 0);
                        $unitName = $item->Unit ?: UnitOfMeasure::DEFAULT;
                        if (! $size) {
                            throw new \RuntimeException('Enter how many '.UnitOfMeasure::plural($product->baseUnit())." each {$unitName} of \"{$product->ProductName}\" holds.");
                        }
                        if (is_float($size) && ! $product->AllowsFractionalQuantity) {
                            throw new \RuntimeException("\"{$product->ProductName}\" is counted in whole ".UnitOfMeasure::plural($product->baseUnit()).'.');
                        }
                        $item->QuantityPerUnit = $size;
                        $product->update(['QuantityPerPurchaseUnit' => $size]);
                    }

                    $item->ReceivedQuantity += $quantityReceived;
                    $item->save();
                    $delivery->items()->create([
                        'PurchaseOrderItemID' => $item->PurchaseOrderItemID,
                        'ProductID' => $item->ProductID,
                        'Quantity' => $quantityReceived,
                        'BaseQuantity' => $item->toBaseQuantity($quantityReceived),
                    ]);
                    $receivedNow += $quantityReceived;

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
                    // untouched on the PurchaseOrderItem as the reference —
                    // converted to the base unit (2 rolls = 10,000 meters).
                    $inventory->Quantity = Quantity::normalize($inventory->Quantity + $item->toBaseQuantity($quantityReceived));
                    $inventory->Status = Inventory::resolveStatus($inventory->Quantity, $inventory->ReorderThreshold);
                    $inventory->save();
                }

                if ($receivedNow === 0) {
                    throw new \RuntimeException('Enter how many of each product arrived in this delivery.');
                }

                // The order stays open (Partially Received, still in the
                // Pending / Expected Delivery list) until nothing more is
                // owed: every line is in, or the owner cancelled what was
                // left (PurchaseOrderController::cancelRemaining()).
                $purchaseOrder->load('items');
                $settled = $purchaseOrder->isSettled();

                $lockedBatch->update([
                    'Status' => $settled ? StockReceivingBatch::STATUS_COMPLETED : StockReceivingBatch::STATUS_PENDING,
                    'ReceiptNumber' => $receiptNumber ?? $lockedBatch->ReceiptNumber,
                    'ReceivedBy' => auth()->id(),
                    'CompletedAt' => $settled ? now() : null,
                ]);

                $purchaseOrder->update([
                    'Status' => match (true) {
                        $purchaseOrder->isFullyReceived() => PurchaseOrder::STATUS_FULLY_RECEIVED,
                        $settled => PurchaseOrder::STATUS_CLOSED,
                        default => PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                    },
                ]);
                $orderStillOpen = ! $settled;

                ActivityLog::record('stock_receiving_batch.completed', "Received {$receivedNow} item(s) for PO #{$purchaseOrder->PONumber}".($settled ? '' : ' — order stays open for the rest'));
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

        // Dispatched only after the transaction has committed — a
        // notification failure must never roll back a completed delivery.
        try {
            $purchaseOrder = PurchaseOrder::with(['supplier', 'items'])->find($stockReceivingBatch->PurchaseOrderID);
            if ($purchaseOrder) {
                Notification::send(User::admins(), new DeliveryReceived(
                    $purchaseOrder,
                    (int) $purchaseOrder->items->sum('ReceivedQuantity'),
                    (int) $purchaseOrder->items->sum('Quantity'),
                ));
            }
        } catch (Throwable $e) {
            Log::error('Failed to dispatch DeliveryReceived notification', [
                'batch_id' => $stockReceivingBatch->StockReceivingBatchID,
                'exception' => $e->getMessage(),
            ]);
        }

        return redirect()->route('admin.stock-receivings.index')->with('success', $orderStillOpen
            ? 'Stock added to Inventory. The rest of this order is still expected, so it stays open in Pending / Expected Delivery.'
            : 'Stock added to Inventory. This delivery is now marked Completed.');
    }

    /**
     * Sends a one-time "delivery due / overdue" notification for every
     * pending delivery whose Expected Delivery Date is today or earlier.
     * This hosting has no cron/scheduler, so it's triggered from admin page
     * loads (throttled in AppServiceProvider) instead of a scheduled job —
     * the same approach the automatic reorder drafts already use.
     */
    public static function notifyDueDeliveries(): void
    {
        $dueBatches = StockReceivingBatch::with('purchaseOrder.supplier')
            ->where('Status', StockReceivingBatch::STATUS_PENDING)
            ->whereNull('DueNotifiedAt')
            ->whereHas('purchaseOrder', function ($query) {
                $query->whereNotNull('ExpectedDeliveryDate')
                    ->whereDate('ExpectedDeliveryDate', '<=', today())
                    ->where('Status', PurchaseOrder::STATUS_PENDING);
            })
            ->get();

        if ($dueBatches->isEmpty()) {
            return;
        }

        $admins = User::admins();

        foreach ($dueBatches as $batch) {
            // Claim the batch first (conditional update) so two overlapping
            // page loads can't both send the same notice.
            $claimed = StockReceivingBatch::where('StockReceivingBatchID', $batch->StockReceivingBatchID)
                ->whereNull('DueNotifiedAt')
                ->update(['DueNotifiedAt' => now()]);

            if (! $claimed) {
                continue;
            }

            try {
                Notification::send($admins, new DeliveryDue($batch->purchaseOrder));
            } catch (Throwable $e) {
                Log::error('Failed to dispatch DeliveryDue notification', [
                    'batch_id' => $batch->StockReceivingBatchID,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    // Live "already used" check for both Receipt Number entry points: the
    // manual "Record Receipt" form and the delivery's Receipt Number in
    // "Add to Inventory". Pass exclude_batch_id so a delivery's own saved
    // number isn't reported as a duplicate of itself.
    public function checkReceiptNumber(Request $request)
    {
        $receiptNumber = trim((string) $request->input('receipt_number', ''));
        $excludeBatchId = $request->filled('exclude_batch_id') ? (int) $request->input('exclude_batch_id') : null;

        return response()->json([
            'used' => $receiptNumber !== '' && self::receiptNumberIsUsed($receiptNumber, $excludeBatchId),
            'receipt_number' => $receiptNumber,
        ]);
    }

    /**
     * Whether a Receipt Number is already on record anywhere: another
     * delivery, the manual Record Receipt flow, or a product line from
     * before Receipt Numbers moved to the delivery as a whole.
     */
    private static function receiptNumberIsUsed(string $receiptNumber, ?int $excludeBatchId = null): bool
    {
        return StockReceivingBatch::where('ReceiptNumber', $receiptNumber)
            ->when($excludeBatchId, fn ($query) => $query->where('StockReceivingBatchID', '!=', $excludeBatchId))
            ->exists()
            || StockReceivingDelivery::where('ReceiptNumber', $receiptNumber)->exists()
            || StockReceiving::where('ReceiptNumber', $receiptNumber)->exists()
            || PurchaseOrderItem::where('ReceiptNumber', $receiptNumber)->exists();
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

        $receivedBaseQuantity = 0;

        try {
            DB::transaction(function () use ($data, &$receivedBaseQuantity) {
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

                // Received in the order line's unit (or, without an order,
                // the product's purchase unit), stocked in the base unit.
                // (Without an order the product's last roll size is used; a
                // roll whose size was never received has to come in on a PO.)
                $receivedProduct = Product::find($productId);
                if (! $purchaseOrderItem && $receivedProduct->hasPurchaseUnit() && $receivedProduct->purchaseUnitSize() === null) {
                    throw new \RuntimeException("Receive \"{$receivedProduct->ProductName}\" through its Purchase Order, where the size of each {$receivedProduct->PurchaseUnit} is entered.");
                }
                $baseQuantity = $receivedBaseQuantity = $purchaseOrderItem
                    ? $purchaseOrderItem->toBaseQuantity($data['Quantity'])
                    : Quantity::normalize($data['Quantity'] * (float) $receivedProduct->quantityPerOrderUnit());
                $inventory->Quantity = Quantity::normalize($inventory->Quantity + $baseQuantity);
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
                Notification::send(User::admins(), new ProductReceived($product, $supplier, $receivedBaseQuantity));
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
