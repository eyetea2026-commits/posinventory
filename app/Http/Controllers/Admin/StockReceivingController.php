<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockReceiving;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\ProductReceived;
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

    public function index(Request $request)
    {
        $search = $request->query('search');

        $receivings = StockReceiving::with(['product', 'supplier'])
            ->when($search, function ($query, $search) {
                $query->whereHas('product', function ($product) use ($search) {
                    $product->where('ProductName', 'like', "%{$search}%");
                })->orWhereHas('supplier', function ($supplier) use ($search) {
                    $supplier->where('SupplierName', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('DateReceived')
            ->paginate(15)
            ->withQueryString();

        return view('admin.stock-receivings.index', [
            'receivings' => $receivings,
            'search' => $search,
            'products' => Product::orderBy('ProductName')->get(),
            'suppliers' => Supplier::orderBy('SupplierName')->get(),
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
                $inventory->Status = $inventory->Quantity <= 0
                    ? 'Out of Stock'
                    : ($inventory->Quantity <= ($inventory->ReorderThreshold ?? 50) ? 'Low Stock' : 'Available');
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
