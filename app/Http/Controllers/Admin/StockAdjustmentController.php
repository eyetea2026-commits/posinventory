<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DamagedProduct;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Notifications\StockAdjustmentRecorded;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class StockAdjustmentController extends Controller
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

        $adjustments = StockAdjustment::with('product')
            ->when($search, function ($query, $search) {
                $query->where('Reason', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($product) use ($search) {
                        $product->where('ProductName', 'like', "%{$search}%");
                    });
            })
            ->orderByDesc('Date')
            ->paginate(15)
            ->withQueryString();

        return view('admin.stock-adjustments.index', [
            'adjustments' => $adjustments,
            'search' => $search,
            'products' => Product::orderBy('ProductName')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.stock-adjustments.create', [
            'products' => Product::orderBy('ProductName')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'ProductID' => ['required', 'integer', 'exists:Product,ProductID'],
            'QuantityAdjust' => ['required', 'integer'],
            'Reason' => ['required', 'string', 'in:' . implode(',', StockAdjustment::REASONS)],
            'Remarks' => ['nullable', 'string', 'max:500'],
            'Date' => ['required', 'date'],
        ]);

        $newQty = null;
        $adjustment = null;
        $damage = null;

        try {
            DB::transaction(function () use ($data, &$newQty, &$adjustment, &$damage) {
                // Lock, compute, and re-validate entirely inside the
                // transaction — reading/checking before the lock (as this
                // used to) leaves a window where two concurrent adjustments
                // for the same product can both pass the negative-stock
                // check against the same stale quantity and jointly push
                // the true total negative.
                $inventory = Inventory::where('ProductID', $data['ProductID'])->lockForUpdate()->first();

                if (! $inventory) {
                    $inventory = Inventory::firstOrCreate(
                        ['ProductID' => $data['ProductID']],
                        ['Quantity' => 0, 'Status' => 'Out of Stock']
                    );
                    $inventory = Inventory::where('ProductID', $data['ProductID'])->lockForUpdate()->first();
                }

                $newQty = $inventory->Quantity + $data['QuantityAdjust'];

                if ($newQty < 0) {
                    throw new \RuntimeException('Cannot reduce stock below zero. Current stock: ' . $inventory->Quantity);
                }

                $adjustment = StockAdjustment::create($data);

                $inventory->Quantity = $newQty;
                $inventory->Status = $newQty <= 0
                    ? 'Out of Stock'
                    : ($newQty <= ($inventory->ReorderThreshold ?? 50) ? 'Low Stock' : 'Available');
                $inventory->save();

                // A decrease recorded as "Damaged" is a loss the Damage
                // module needs to track (supplier return / disposal) —
                // no separate manual damage entry should be required.
                if ($data['Reason'] === StockAdjustment::REASON_DAMAGED && $data['QuantityAdjust'] < 0) {
                    $damage = $this->createDamageRecordForAdjustment($adjustment);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $product = Product::find($data['ProductID']);
        $productName = $product?->ProductName ?? 'Unknown product';
        $sign = $data['QuantityAdjust'] >= 0 ? '+' : '';
        ActivityLog::record('stock.adjusted', "Adjusted \"{$productName}\" by {$sign}{$data['QuantityAdjust']} (new total: {$newQty})" . ($damage ? " — created damage record #{$damage->DamageID}" : ''));

        // The adjustment itself already committed above — a notification
        // failure (broken mail transport, queue connection down) must not
        // turn a successful adjustment into a 500 response.
        if ($product) {
            try {
                Notification::send(
                    User::admins(),
                    new StockAdjustmentRecorded($product, (int) $data['QuantityAdjust'], $newQty, $data['Reason'])
                );
            } catch (Throwable $e) {
                Log::error('Failed to dispatch StockAdjustmentRecorded notification', [
                    'product_id' => $product->ProductID,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('admin.stock-adjustments.index')->with('success', 'Stock adjustment saved successfully.');
    }

    // Mirrors SalesReturnController::createDamageRecordForItem() — a
    // "Damaged" stock adjustment is exactly as much a loss as a customer's
    // defective return, so it goes through the same Damage module/supplier
    // return workflow instead of just silently decrementing inventory.
    private function createDamageRecordForAdjustment(StockAdjustment $adjustment): DamagedProduct
    {
        $supplierId = PurchaseOrderItem::where('ProductID', $adjustment->ProductID)
            ->join('PurchaseOrder', 'PurchaseOrder.PurchaseOrderID', '=', 'PurchaseOrderItem.PurchaseOrderID')
            ->orderByDesc('PurchaseOrder.PurchaseOrderID')
            ->value('PurchaseOrder.SupplierID');

        $damage = DamagedProduct::create([
            'ProductID' => $adjustment->ProductID,
            'StockAdjustmentID' => $adjustment->AdjustmentID,
            'SourceModule' => DamagedProduct::SOURCE_STOCK_ADJUSTMENT,
            'SupplierID' => $supplierId,
            'Quantity' => abs($adjustment->QuantityAdjust),
            'Description' => "Stock adjustment — {$adjustment->Reason} (Adjustment #{$adjustment->AdjustmentID})",
            'DateRecorded' => $adjustment->Date,
            'DamageType' => 'damaged_product',
            'Remarks' => $adjustment->Remarks,
            'Status' => DamagedProduct::STATUS_FOR_SUPPLIER_RETURN,
        ]);

        ActivityLog::record(
            'damage.created_from_adjustment',
            "Created damage record #{$damage->DamageID} for {$damage->Quantity} x \"{$adjustment->product?->ProductName}\" from stock adjustment #{$adjustment->AdjustmentID}"
        );

        return $damage;
    }
}
