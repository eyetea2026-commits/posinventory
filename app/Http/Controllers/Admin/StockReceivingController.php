<?php

namespace App\Http\Controllers\Admin;

use App\Casts\Quantity;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DamagedProduct;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Models\StockReceiving;
use App\Models\StockReceivingBatch;
use App\Models\StockReceivingDelivery;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\DeliveryDue;
use App\Notifications\DeliveryReceived;
use App\Notifications\ProductReceived;
use App\Services\ActionNotifier;
use App\Services\ReorderPoint;
use App\Services\StockBatches;
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
            'suppliers' => Supplier::orderBy('SupplierName')->get(),
        ]);
    }

    public function showBatch(StockReceivingBatch $stockReceivingBatch)
    {
        $stockReceivingBatch->load(['purchaseOrder.supplier', 'purchaseOrder.items.product']);

        $isPending = $stockReceivingBatch->Status === StockReceivingBatch::STATUS_PENDING;
        $canReorder = $isPending && $stockReceivingBatch->purchaseOrder->items->contains(fn (PurchaseOrderItem $item) => $item->remaining_quantity > 0);

        return response()->json([
            'html' => view('admin.stock-receivings.partials.batch-details', [
                'batch' => $stockReceivingBatch,
                'canReorder' => $canReorder,
                'reorderSuppliers' => $canReorder ? self::reorderSuppliersFor($stockReceivingBatch->purchaseOrder) : collect(),
            ])->render(),
            'isPending' => $isPending,
            'canReorder' => $canReorder,
        ]);
    }

    private static function reorderSuppliersFor(PurchaseOrder $purchaseOrder)
    {
        $productIds = $purchaseOrder->items->pluck('ProductID');
        $linked = DB::table('ProductSupplier')->whereIn('ProductID', $productIds)->pluck('SupplierID')->unique()->all();

        return Supplier::where('Status', Supplier::STATUS_ACTIVE)
            ->where('SupplierID', '!=', $purchaseOrder->SupplierID)
            ->orderBy('SupplierName')
            ->get()
            ->map(fn (Supplier $supplier) => ['supplier' => $supplier, 'suppliesThese' => in_array($supplier->SupplierID, $linked)])
            ->sortByDesc('suppliesThese')
            ->values();
    }

    public function reorderFromAnotherSupplier(Request $request, StockReceivingBatch $stockReceivingBatch)
    {
        $validator = validator($request->all(), [
            'supplier_id' => ['required', 'integer', 'exists:Supplier,SupplierID'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'supplier_id.required' => 'Choose the supplier to reorder from.',
            'items.required' => 'Tick at least one product to reorder.',
            'reason.required' => 'Enter why the products are being reordered (e.g. the supplier has no stock).',
        ]);
        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first());
        }
        $data = $validator->validated();

        $newSupplier = Supplier::find($data['supplier_id']);
        if ($newSupplier->Status !== Supplier::STATUS_ACTIVE) {
            return back()->with('error', "\"{$newSupplier->SupplierName}\" is inactive. Choose an active supplier.");
        }

        $original = null;
        $newOrder = null;
        $reordered = [];

        try {
            DB::transaction(function () use ($data, $stockReceivingBatch, $newSupplier, &$original, &$newOrder, &$reordered) {
                $batch = StockReceivingBatch::where('StockReceivingBatchID', $stockReceivingBatch->StockReceivingBatchID)
                    ->where('Status', StockReceivingBatch::STATUS_PENDING)
                    ->lockForUpdate()
                    ->first();
                $original = $batch ? PurchaseOrder::with('supplier')
                    ->where('PurchaseOrderID', $batch->PurchaseOrderID)
                    ->whereIn('Status', [PurchaseOrder::STATUS_PENDING, PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])
                    ->lockForUpdate()
                    ->first() : null;
                if (! $original) {
                    throw new \RuntimeException('This delivery is no longer waiting to be received.');
                }
                if ((int) $original->SupplierID === (int) $newSupplier->SupplierID) {
                    throw new \RuntimeException('Choose a different supplier from the one on this order.');
                }

                $items = PurchaseOrderItem::with('product.suppliers')
                    ->where('PurchaseOrderID', $original->PurchaseOrderID)
                    ->whereIn('PurchaseOrderItemID', $data['items'])
                    ->lockForUpdate()
                    ->get();

                $lines = [];
                foreach ($items as $item) {
                    $remaining = $item->remaining_quantity;
                    if ($remaining <= 0 || ! $item->product) {
                        continue;
                    }
                    $item->update(['CancelledQuantity' => (int) $item->CancelledQuantity + $remaining]);

                    $supplierCost = $item->product->suppliers->firstWhere('SupplierID', $newSupplier->SupplierID)?->CostPrice;
                    $lines[] = [
                        'ProductID' => $item->ProductID,
                        'Quantity' => $remaining,
                        ...PurchaseOrderItem::unitFor($item->product),
                        'CostPriceAtOrder' => $supplierCost !== null
                            ? PurchaseOrderController::costPerPurchaseUnit($item->product, $supplierCost)
                            : $item->CostPriceAtOrder,
                    ];
                    $reordered[] = "{$remaining} x \"{$item->product->ProductName}\"";
                }
                if ($lines === []) {
                    throw new \RuntimeException('Those products have nothing left to reorder.');
                }

                $newOrder = PurchaseOrder::create([
                    'PurchaseDate' => now()->toDateString(),
                    'Notes' => "Reorder of {$original->PONumber} (".($original->supplier?->SupplierName ?? 'previous supplier')."): {$data['reason']}",
                    'Status' => PurchaseOrder::STATUS_DRAFT,
                    'SupplierID' => $newSupplier->SupplierID,
                    'CreatedBy' => auth()->id(),
                ]);
                $newOrder->update(['PONumber' => 'PO-'.now()->format('Y').'-'.str_pad((string) $newOrder->PurchaseOrderID, 6, '0', STR_PAD_LEFT)]);
                foreach ($lines as $line) {
                    PurchaseOrderItem::create(['PurchaseOrderID' => $newOrder->PurchaseOrderID, ...$line]);
                }

                $original->load('items');
                $settled = $original->isSettled();
                $original->update([
                    'Status' => ! $settled ? $original->Status : ($original->hasAnyReceivedQuantity() ? PurchaseOrder::STATUS_CLOSED : PurchaseOrder::STATUS_CANCELLED),
                    'RemainderCancelReason' => "Reordered from {$newSupplier->SupplierName} as {$newOrder->PONumber}: {$data['reason']}",
                    'RemainderCancelledBy' => auth()->id(),
                    'RemainderCancelledAt' => now(),
                ]);
                if ($settled) {
                    $batch->update(['Status' => StockReceivingBatch::STATUS_COMPLETED, 'CompletedAt' => now()]);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $summary = implode(', ', $reordered);
        ActivityLog::record('purchase_order.reordered_elsewhere', "Reordered {$summary} from \"{$newSupplier->SupplierName}\" as PO #{$newOrder->PONumber} instead of PO #{$original->PONumber}: {$data['reason']}");
        ActionNotifier::toAdmins(
            'Reordered from Another Supplier',
            "{$summary} on {$original->PONumber} will come from \"{$newSupplier->SupplierName}\" instead ({$data['reason']}). Draft {$newOrder->PONumber} is ready to review and print.",
            route('admin.purchase-orders.index'),
            'clipboard-check',
            'warning'
        );

        return redirect()->route('admin.stock-receivings.index')->with('success', "Draft purchase order {$newOrder->PONumber} created for {$newSupplier->SupplierName}. Review and print it in Purchase Orders to send it.");
    }

    public function addToInventory(Request $request, StockReceivingBatch $stockReceivingBatch)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'exists:PurchaseOrderItem,PurchaseOrderItemID'],
            'items.*.quantity_received' => ['required', 'integer', 'min:0'],
            'items.*.quantity_damaged' => ['nullable', 'integer', 'min:0'],
            'items.*.quantity_per_unit' => ['nullable', 'numeric', 'gt:0', 'max:9999999', 'decimal:0,2'],
            'receipt_number' => ['required', 'string', 'max:50'],
        ], [
            'receipt_number.required' => 'Enter the Receipt Number of this delivery.',
        ]);

        $receiptNumber = filled($data['receipt_number'] ?? null) ? trim($data['receipt_number']) : null;

        if ($receiptNumber !== null && StockReceivingDelivery::where('StockReceivingBatchID', $stockReceivingBatch->StockReceivingBatchID)->where('ReceiptNumber', $receiptNumber)->exists()) {
            return redirect()->route('admin.stock-receivings.index')
                ->with('success', "This delivery (Receipt Number \"{$receiptNumber}\") was already added to Inventory.");
        }

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

                    $quantityReceived = (int) $row['quantity_received'];
                    $quantityDamaged = (int) ($row['quantity_damaged'] ?? 0);

                    if ($quantityReceived + $quantityDamaged > $item->remaining_quantity && $quantityDamaged > 0) {
                        throw new \RuntimeException("Good and damaged \"{$item->product?->ProductName}\" together can be at most {$item->remaining_quantity} (still owed on this order).");
                    }

                    if ($quantityReceived > $item->remaining_quantity) {
                        throw new \RuntimeException("Only {$item->remaining_quantity} more \"{$item->product?->ProductName}\" can be received on this order (ordered {$item->Quantity}, received {$item->ReceivedQuantity} so far".((int) $item->CancelledQuantity > 0 ? ", cancelled {$item->CancelledQuantity}" : '').').');
                    }

                    if ($quantityReceived <= 0 && $quantityDamaged <= 0) {
                        continue;
                    }

                    $product = $item->product;
                    if ($product && ($item->Unit ?: UnitOfMeasure::DEFAULT) !== $product->baseUnit()) {
                        $size = $item->orderedMetersPerRoll() ?? Quantity::normalize($row['quantity_per_unit'] ?? 0);
                        $unitName = $item->Unit ?: UnitOfMeasure::DEFAULT;
                        if (! $size) {
                            throw new \RuntimeException('Enter how many '.UnitOfMeasure::plural($product->baseUnit())." each {$unitName} of \"{$product->ProductName}\" holds.");
                        }
                        if ($product->baseUnit() === 'meter' && $size > PurchaseOrderItem::MAX_METERS_PER_ROLL) {
                            throw new \RuntimeException("Meters per {$unitName} of \"{$product->ProductName}\" can be at most 4 digits (".number_format(PurchaseOrderItem::MAX_METERS_PER_ROLL).').');
                        }
                        if (is_float($size) && ! $product->AllowsFractionalQuantity) {
                            throw new \RuntimeException("\"{$product->ProductName}\" is counted in whole ".UnitOfMeasure::plural($product->baseUnit()).'.');
                        }
                        $item->QuantityPerUnit = $size;
                        $product->update(['QuantityPerPurchaseUnit' => $size]);
                    }

                    $item->ReceivedQuantity += $quantityReceived + $quantityDamaged;
                    $item->save();
                    $deliveryItem = $delivery->items()->create([
                        'PurchaseOrderItemID' => $item->PurchaseOrderItemID,
                        'ProductID' => $item->ProductID,
                        'Quantity' => $quantityReceived,
                        'BaseQuantity' => $item->toBaseQuantity($quantityReceived),
                        'DamagedQuantity' => $quantityDamaged,
                        'UnitCost' => $item->costPerBaseUnit(),
                        'SellingPrice' => $product?->sellingPriceForCost($item->costPerBaseUnit()) ?? $product?->Price,
                    ]);
                    $receivedNow += $quantityReceived + $quantityDamaged;

                    if ($product) {
                        self::recordSupplierPrice($product, $purchaseOrder, $item);
                    }

                    if ($quantityDamaged > 0) {
                        $damage = DamagedProduct::create([
                            'ProductID' => $item->ProductID,
                            'PurchaseOrderID' => $purchaseOrder->PurchaseOrderID,
                            'SupplierID' => $purchaseOrder->SupplierID,
                            'SourceModule' => DamagedProduct::SOURCE_STOCK_RECEIVING,
                            'Quantity' => $item->toBaseQuantity($quantityDamaged),
                            'Description' => "Damaged on arrival — PO {$purchaseOrder->PONumber}, Receipt ".($receiptNumber ?? 'N/A'),
                            'DateRecorded' => now(),
                            'DamageType' => 'damaged_product',
                            'Status' => DamagedProduct::STATUS_FOR_SUPPLIER_RETURN,
                        ]);
                        ActivityLog::record('damage.created_from_receiving', "Created damage record #{$damage->DamageID} for {$item->quantityLabel($quantityDamaged)} x \"{$product?->ProductName}\" damaged on arrival (PO #{$purchaseOrder->PONumber})");
                    }

                    if ($quantityReceived <= 0) {
                        continue;
                    }

                    $inventory = Inventory::where('ProductID', $item->ProductID)->lockForUpdate()->first();
                    if (! $inventory) {
                        Inventory::firstOrCreate(['ProductID' => $item->ProductID], ['Quantity' => 0, 'Status' => 'Out of Stock']);
                        $inventory = Inventory::where('ProductID', $item->ProductID)->lockForUpdate()->first();
                    }

                    $inventory->Quantity = Quantity::normalize($inventory->Quantity + $item->toBaseQuantity($quantityReceived));
                    $inventory->Status = Inventory::resolveStatus($inventory->Quantity, $inventory->ReorderThreshold);
                    StockBatches::receivingAs([
                        'Source' => StockBatch::SOURCE_PURCHASE_ORDER,
                        'SupplierID' => $purchaseOrder->SupplierID,
                        'PurchaseOrderID' => $purchaseOrder->PurchaseOrderID,
                        'StockReceivingDeliveryItemID' => $deliveryItem->StockReceivingDeliveryItemID,
                        'ReceiptNumber' => $receiptNumber,
                        'UnitCost' => $deliveryItem->UnitCost,
                        'SellingPrice' => $deliveryItem->SellingPrice,
                    ], fn () => $inventory->save());
                }

                if ($receivedNow === 0) {
                    throw new \RuntimeException('Enter how many of each product arrived in this delivery.');
                }

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

        try {
            ReorderPoint::refresh(PurchaseOrderItem::where('PurchaseOrderID', $stockReceivingBatch->PurchaseOrderID)->pluck('ProductID'));
        } catch (Throwable $e) {
            Log::error('Re-Order Point refresh after a delivery failed: '.$e->getMessage());
        }

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

    public function checkReceiptNumber(Request $request)
    {
        $receiptNumber = trim((string) $request->input('receipt_number', ''));
        $excludeBatchId = $request->filled('exclude_batch_id') ? (int) $request->input('exclude_batch_id') : null;

        return response()->json([
            'used' => $receiptNumber !== '' && self::receiptNumberIsUsed($receiptNumber, $excludeBatchId),
            'receipt_number' => $receiptNumber,
        ]);
    }

    private static function receiptNumberIsUsed(string $receiptNumber, ?int $excludeBatchId = null): bool
    {
        return StockReceivingBatch::where('ReceiptNumber', $receiptNumber)
            ->when($excludeBatchId, fn ($query) => $query->where('StockReceivingBatchID', '!=', $excludeBatchId))
            ->exists()
            || StockReceivingDelivery::where('ReceiptNumber', $receiptNumber)->exists()
            || StockReceiving::where('ReceiptNumber', $receiptNumber)->exists();
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

                $stockReceiving = StockReceiving::create([
                    'ProductID' => $productId,
                    'SupplierID' => $supplierId,
                    'Quantity' => $data['Quantity'],
                    'ReceiptNumber' => $data['ReceiptNumber'],
                    'DateReceived' => $data['DateReceived'],
                    'PurchaseOrderID' => $data['purchase_order_id'] ?? null,
                    'PurchaseOrderItemID' => $data['purchase_order_item_id'] ?? null,
                ]);

                $inventory = Inventory::where('ProductID', $productId)->lockForUpdate()->first();
                if (! $inventory) {
                    Inventory::firstOrCreate(['ProductID' => $productId], ['Quantity' => 0, 'Status' => 'Out of Stock']);
                    $inventory = Inventory::where('ProductID', $productId)->lockForUpdate()->first();
                }

                $receivedProduct = Product::find($productId);
                if (! $purchaseOrderItem && $receivedProduct->hasPurchaseUnit() && $receivedProduct->purchaseUnitSize() === null) {
                    throw new \RuntimeException("Receive \"{$receivedProduct->ProductName}\" through its Purchase Order, where the size of each {$receivedProduct->PurchaseUnit} is entered.");
                }
                $baseQuantity = $receivedBaseQuantity = $purchaseOrderItem
                    ? $purchaseOrderItem->toBaseQuantity($data['Quantity'])
                    : Quantity::normalize($data['Quantity'] * (float) $receivedProduct->quantityPerOrderUnit());
                $inventory->Quantity = Quantity::normalize($inventory->Quantity + $baseQuantity);
                $inventory->Status = Inventory::resolveStatus($inventory->Quantity, $inventory->ReorderThreshold);
                StockBatches::receivingAs([
                    'Source' => StockBatch::SOURCE_STOCK_RECEIVING,
                    'SupplierID' => $supplierId,
                    'PurchaseOrderID' => $data['purchase_order_id'] ?? null,
                    'ReceivingID' => $stockReceiving->ReceivingID,
                    'ReceiptNumber' => $data['ReceiptNumber'],
                    'UnitCost' => $purchaseOrderItem?->costPerBaseUnit(),
                ], fn () => $inventory->save());

                if ($purchaseOrderItem) {
                    $purchaseOrderItem->increment('ReceivedQuantity', $data['Quantity']);

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
        if ($product) {
            try {
                ReorderPoint::refresh([$product->ProductID]);
            } catch (Throwable $e) {
                Log::error('Re-Order Point refresh after a delivery failed: '.$e->getMessage());
            }
        }
        ActivityLog::record('stock.received', "Received {$data['Quantity']} x \"{$productName}\" from \"{$supplierName}\"{$poSuffix}");

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

    private static function recordSupplierPrice(Product $product, PurchaseOrder $purchaseOrder, PurchaseOrderItem $item): void
    {
        $unitCost = $item->costPerBaseUnit();
        if ($unitCost === null || $unitCost <= 0 || ! $purchaseOrder->SupplierID) {
            return;
        }

        $link = ProductSupplier::firstOrCreate(
            ['ProductID' => $product->ProductID, 'SupplierID' => $purchaseOrder->SupplierID],
            ['CostPrice' => null]
        );
        $oldCost = $link->CostPrice !== null ? (float) $link->CostPrice : null;
        if ($oldCost === $unitCost) {
            return;
        }

        $link->update(['CostPrice' => $unitCost]);
        ProductCostHistory::log($product, $purchaseOrder->supplier ?? Supplier::find($purchaseOrder->SupplierID), $oldCost, $unitCost, ProductCostHistory::SOURCE_PO_RECEIVING);
    }
}
