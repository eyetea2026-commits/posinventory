<?php

namespace App\Http\Controllers\Admin;

use App\Casts\Quantity;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DamagedProduct;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\PurchaseOrderItem;
use App\Models\StockAdjustment;
use App\Models\StockBatchAllocation;
use App\Models\User;
use App\Notifications\StockAdjustmentRecorded;
use App\Services\SerialNumbers;
use App\Services\StockBatches;
use App\Services\UnitOfMeasure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
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

        $adjustments = StockAdjustment::with(['product', 'serials'])
            ->when($search, function ($query, $search) {
                $query->where('Reason', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($product) use ($search) {
                        $product->where('ProductName', 'like', "%{$search}%");
                    })
                    ->orWhereHas('serials', function ($serial) use ($search) {
                        $serial->where('SerialNumber', 'like', "%{$search}%");
                    });
            })
            ->orderByDesc('AdjustmentID')
            ->paginate(15)
            ->withQueryString();

        return view('admin.stock-adjustments.index', [
            'adjustments' => $adjustments,
            'search' => $search,
            'products' => $this->productsWithStock(),
        ]);
    }

    public function create()
    {
        return view('admin.stock-adjustments.create', [
            'products' => $this->productsWithStock(),
        ]);
    }

    private function productsWithStock()
    {
        return Product::whereHas('inventory', fn ($inventory) => $inventory->where('Quantity', '>', 0))
            ->with([
                'inventory',
                'category',
                'serials' => fn ($serials) => $serials->where('Status', ProductSerial::STATUS_IN_STOCK)->orderBy('ProductSerialID'),
            ])
            ->orderBy('ProductName')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'ProductID' => ['required', 'integer', 'exists:Product,ProductID'],
            'QuantityAdjust' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999'],
            'Reason' => ['required', 'string', 'in:'.implode(',', StockAdjustment::REASONS)],
            'SerialNumbers' => ['nullable', 'array'],
            'SerialNumbers.*' => ['required', 'string', 'max:20', 'distinct'],
            'Remarks' => ['nullable', 'string', 'max:500'],
            'Date' => ['required', 'date'],
        ], [
            'QuantityAdjust.gt' => 'Enter how many to remove (more than 0).',
        ]);

        $quantityRemoved = Quantity::normalize($data['QuantityAdjust']);
        $adjustedProduct = Product::with('category')->find($data['ProductID']);
        if (is_float($quantityRemoved) && ! $adjustedProduct->AllowsFractionalQuantity) {
            throw ValidationException::withMessages([
                'QuantityAdjust' => "\"{$adjustedProduct->ProductName}\" is counted in whole ".UnitOfMeasure::plural($adjustedProduct->baseUnit()).'.',
            ]);
        }

        $serialNumbers = [];
        if ($adjustedProduct->tracksSerialNumbers()) {
            $serialNumbers = array_values(array_unique(array_map(fn ($serial) => strtoupper(trim($serial)), $data['SerialNumbers'] ?? [])));
            if (count($serialNumbers) !== $quantityRemoved) {
                throw ValidationException::withMessages([
                    'SerialNumbers' => "Tick the serial number of each \"{$adjustedProduct->ProductName}\" being removed ({$quantityRemoved} needed, ".count($serialNumbers).' ticked).',
                ]);
            }
        }
        unset($data['SerialNumbers']);

        $data['QuantityAdjust'] = -$quantityRemoved;

        $newQty = null;
        $adjustment = null;
        $damage = null;

        try {
            DB::transaction(function () use ($data, $serialNumbers, $adjustedProduct, &$newQty, &$adjustment, &$damage) {
                $inventory = Inventory::where('ProductID', $data['ProductID'])->lockForUpdate()->first();

                if (! $inventory) {
                    $inventory = Inventory::firstOrCreate(
                        ['ProductID' => $data['ProductID']],
                        ['Quantity' => 0, 'Status' => 'Out of Stock']
                    );
                    $inventory = Inventory::where('ProductID', $data['ProductID'])->lockForUpdate()->first();
                }

                $newQty = Quantity::normalize($inventory->Quantity + $data['QuantityAdjust']);

                if ($newQty < 0) {
                    throw new \RuntimeException('Cannot reduce stock below zero. Current stock: '.$inventory->Quantity);
                }

                $serials = collect();
                if ($serialNumbers !== []) {
                    $serials = ProductSerial::where('ProductID', $data['ProductID'])
                        ->whereIn('SerialNumber', $serialNumbers)
                        ->where('Status', ProductSerial::STATUS_IN_STOCK)
                        ->orderBy('ProductSerialID')
                        ->lockForUpdate()
                        ->get();
                    if ($serials->count() !== count($serialNumbers)) {
                        $unavailable = array_diff($serialNumbers, $serials->pluck('SerialNumber')->all());
                        throw ValidationException::withMessages([
                            'SerialNumbers' => 'Serial number '.implode(', ', $unavailable)." is not an in-stock \"{$adjustedProduct->ProductName}\".",
                        ]);
                    }
                }

                $adjustment = StockAdjustment::create($data);

                $serialStatus = StockAdjustment::serialStatusFor($data['Reason']);
                $serials->each(fn (ProductSerial $serial) => $serial->update([
                    'Status' => $serialStatus,
                    'StatusDate' => now(),
                    'StockAdjustmentID' => $adjustment->AdjustmentID,
                ]));

                $inventory->Quantity = $newQty;
                $inventory->Status = Inventory::resolveStatus($newQty, $inventory->ReorderThreshold);
                StockBatches::takingFor(
                    ['Reason' => StockBatchAllocation::REASON_STOCK_ADJUSTMENT, 'StockAdjustmentID' => $adjustment->AdjustmentID],
                    fn () => SerialNumbers::removingAs($serialStatus, fn () => $inventory->save())
                );

                if ($data['Reason'] === StockAdjustment::REASON_DAMAGED) {
                    $damage = $this->createDamageRecordForAdjustment($adjustment, $serials->pluck('SerialNumber')->all());
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $product = Product::find($data['ProductID']);
        $productName = $product?->ProductName ?? 'Unknown product';
        $adjustedBy = $product ? $product->formatQuantity($data['QuantityAdjust']) : $data['QuantityAdjust'];
        $newTotal = $product ? $product->formatQuantity($newQty) : $newQty;
        ActivityLog::record('stock.adjusted', "Adjusted \"{$productName}\" by {$adjustedBy} ({$data['Reason']}".($serialNumbers !== [] ? ': '.implode(', ', $serialNumbers) : '')."; new total: {$newTotal})".($damage ? " — created damage record #{$damage->DamageID}" : ''));

        if ($product) {
            try {
                Notification::send(
                    User::admins(),
                    new StockAdjustmentRecorded($product, $data['QuantityAdjust'], $newQty, $data['Reason'])
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

    private function createDamageRecordForAdjustment(StockAdjustment $adjustment, array $serialNumbers = []): DamagedProduct
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
            'Description' => "Stock adjustment — {$adjustment->Reason} (Adjustment #{$adjustment->AdjustmentID})"
                .($serialNumbers !== [] ? ' — Serial numbers: '.implode(', ', $serialNumbers) : ''),
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
