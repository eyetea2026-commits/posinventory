<?php

namespace App\Http\Controllers\Admin;

use App\Casts\Quantity;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DamagedProduct;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Services\ActionNotifier;
use App\Services\StockBatches;
use App\Services\UnitOfMeasure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DamageController extends Controller
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
        $search = $request->get('search');
        $supplierId = $request->get('supplier_id');

        $damagedProducts = DamagedProduct::with(['product', 'supplier', 'purchaseOrder'])
            ->when($search, function ($query) use ($search) {
                return $query->whereHas('product', function ($q) use ($search) {
                    $q->where('ProductName', 'like', "%{$search}%");
                });
            })
            ->when($supplierId, function ($query) use ($supplierId) {
                return $query->where('SupplierID', $supplierId);
            })
            ->orderBy('DamageID', 'desc')
            ->paginate(15)
            ->withQueryString();

        if ($request->boolean('ajax')) {
            return response()->json([
                'rows' => view('admin.damages.partials.rows', ['damagedProducts' => $damagedProducts])->render(),
                'pagination' => view('admin.damages.partials.pagination', ['damagedProducts' => $damagedProducts])->render(),
            ]);
        }

        $kpis = [
            'total' => DamagedProduct::count(),
            'total_cost' => (float) DamagedProduct::join('Product', 'DamagedProduct.ProductID', '=', 'Product.ProductID')
                ->selectRaw('SUM(DamagedProduct.Quantity * COALESCE(Product.CostPrice, 0)) as cost')
                ->value('cost'),
        ];

        $suppliers = Supplier::orderBy('SupplierName')->get();

        return view('admin.damages.index', compact('damagedProducts', 'search', 'supplierId', 'kpis', 'suppliers'));
    }

    public function show(DamagedProduct $damage)
    {
        $damage->load([
            'product.category',
            'product.brand',
            'product.inventory',
            'supplier',
            'purchaseOrder',
            'salesReturn.transaction',
            'salesReturn.staff.user.role',
            'salesReturn.approvedByUser',
            'stockAdjustment',
            'resolvedByUser',
        ]);

        $auditHistory = ActivityLog::where('Description', 'like', "%Damage #{$damage->DamageID}%")
            ->orWhere('Description', 'like', "%damage #{$damage->DamageID}%")
            ->orWhere('Description', 'like', "%damage record #{$damage->DamageID}%")
            ->orderByDesc('DateRecorded')
            ->get(['Action', 'Description', 'DateRecorded'])
            ->map(fn (ActivityLog $log) => [
                'Action' => $log->Action,
                'Description' => $log->Description,
                'DateRecorded' => $log->DateRecorded?->format('m/d/Y h:i A'),
            ]);

        if ($damage->salesReturn) {
            $staff = $damage->salesReturn->staff;
            $user = $staff?->user;

            $requestedBy = [
                'Name' => $user?->full_name ?? 'Unknown User',
                'EmployeeID' => $staff ? ('EMP-'.str_pad((string) $staff->StaffID, 4, '0', STR_PAD_LEFT)) : 'N/A',
                'Role' => $user?->role?->role_name ? ucfirst($user->role->role_name) : 'Unknown',
                'RequestDate' => optional($damage->salesReturn->created_at)->format('m/d/Y g:i A'),
            ];
        } else {
            $created = ActivityLog::with('user.role')
                ->where('Description', 'like', '%as damaged%')
                ->where('Description', 'like', "%\"{$damage->product?->ProductName}\"%")
                ->orderBy('DateRecorded')
                ->first();

            $requestedBy = [
                'Name' => $created?->user?->full_name ?? 'Unknown User',
                'EmployeeID' => 'N/A',
                'Role' => $created?->user?->role?->role_name ? ucfirst($created->user->role->role_name) : 'Admin',
                'RequestDate' => $created ? $created->DateRecorded->format('m/d/Y g:i A') : optional($damage->DateRecorded)->format('m/d/Y'),
            ];
        }

        $supplierReturnStatus = in_array($damage->Status, [
            DamagedProduct::STATUS_FOR_SUPPLIER_RETURN,
            DamagedProduct::STATUS_RETURNED_TO_SUPPLIER,
            DamagedProduct::STATUS_REPLACEMENT_RECEIVED,
            DamagedProduct::STATUS_CANCELLED,
        ], true)
            ? (DamagedProduct::STATUS_LABELS[$damage->Status] ?? $damage->Status)
            : 'Not Applicable';

        return response()->json([
            'damage' => [
                'DamageID' => $damage->DamageID,
                'DamageNumber' => 'DMG-'.str_pad((string) $damage->DamageID, 6, '0', STR_PAD_LEFT),
                'Quantity' => $damage->Quantity,
                'QuantityLabel' => $damage->product ? $damage->product->formatQuantity($damage->Quantity) : (string) $damage->Quantity,
                'DamageType' => DamagedProduct::DAMAGE_TYPES[$damage->DamageType] ?? $damage->DamageType,
                'Status' => DamagedProduct::STATUS_LABELS[$damage->Status] ?? $damage->Status,
                'SourceModule' => DamagedProduct::SOURCE_LABELS[$damage->SourceModule] ?? ($damage->SourceModule ?? 'Unknown'),
                'Description' => $damage->Description,
                'InspectionNotes' => $damage->InspectionNotes,
                'WarehouseLocation' => $damage->WarehouseLocation,
                'Remarks' => $damage->Remarks,
                'ImageUrl' => $damage->ImagePath ? Storage::url($damage->ImagePath) : null,
                'DateRecorded' => optional($damage->DateRecorded)->format('m/d/Y'),
                'ResolvedBy' => $damage->resolvedByUser?->full_name,
                'ResolvedDate' => optional($damage->ResolvedDate)->format('m/d/Y'),
            ],
            'product' => [
                'ProductName' => $damage->product?->ProductName,
                'Brand' => $damage->product?->brand?->BrandName,
                'Category' => $damage->product?->category?->CategoryName,
                'CostPrice' => $damage->product?->CostPrice,
                'CurrentStock' => $damage->product?->inventory?->Quantity,
                'CurrentStockLabel' => $damage->product?->inventory ? $damage->product->stockLabel($damage->product->inventory->Quantity) : null,
            ],
            'supplier' => $damage->supplier ? [
                'SupplierName' => $damage->supplier->SupplierName,
                'ContactNumber' => $damage->supplier->ContactNumber,
                'Email' => $damage->supplier->Email,
            ] : null,
            'purchaseOrder' => $damage->purchaseOrder ? [
                'PONumber' => $damage->purchaseOrder->PONumber,
            ] : null,
            'salesReturn' => $damage->salesReturn ? [
                'SalesReturnID' => $damage->salesReturn->SalesReturnID,
                'ReceiptNumber' => $damage->salesReturn->SalesTransactionID ? ('RCT-'.str_pad($damage->salesReturn->SalesTransactionID, 6, '0', STR_PAD_LEFT)) : null,
                'Reason' => DamagedProduct::DAMAGE_TYPES[$damage->DamageType] ?? $damage->DamageType,
            ] : null,
            'requestedBy' => $requestedBy,
            'approvedBy' => $damage->salesReturn?->approvedByUser ? [
                'Name' => $damage->salesReturn->approvedByUser->full_name,
            ] : null,
            'supplierReturnStatus' => $supplierReturnStatus,
            'stockAdjustment' => $damage->stockAdjustment ? [
                'AdjustmentID' => $damage->stockAdjustment->AdjustmentID,
                'QuantityAdjust' => $damage->stockAdjustment->QuantityAdjust,
                'QuantityAdjustLabel' => $damage->product ? $damage->product->formatQuantity($damage->stockAdjustment->QuantityAdjust) : (string) $damage->stockAdjustment->QuantityAdjust,
                'Reason' => $damage->stockAdjustment->Reason,
            ] : null,
            'auditHistory' => $auditHistory,
        ]);
    }

    public function printReport(DamagedProduct $damage)
    {
        $damage->load([
            'product.category',
            'product.brand',
            'supplier',
            'purchaseOrder',
            'salesReturn.staff.user',
            'salesReturn.approvedByUser',
        ]);

        return view('admin.damages.print', ['damage' => $damage]);
    }

    public function edit(Request $request, DamagedProduct $damage)
    {
        $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if ($damage->Status !== DamagedProduct::STATUS_PENDING) {
            if ($isAjax) {
                return response()->json(['error' => 'Only pending damage records can be edited.'], 422);
            }

            return redirect()->route('admin.damages.index')->with('error', 'Only pending damage records can be edited.');
        }

        $products = Product::with(['inventory', 'category', 'suppliers'])->orderBy('ProductName')->get();
        $suppliers = Supplier::orderBy('SupplierName')->get();
        $purchaseOrders = PurchaseOrder::with('supplier')->orderByDesc('PurchaseOrderID')->get();

        if ($isAjax) {
            return response()->json([
                'html' => view('admin.damages.partials.damage-form-fields', compact('damage', 'products', 'suppliers', 'purchaseOrders'))->render(),
            ]);
        }

        return view('admin.damages.edit', compact('damage', 'products', 'suppliers', 'purchaseOrders'));
    }

    public function update(Request $request, DamagedProduct $damage)
    {
        if ($damage->Status !== DamagedProduct::STATUS_PENDING) {
            return back()->with('error', 'Only pending damage records can be edited.');
        }

        $data = $request->validate([
            'ProductID' => 'required|exists:Product,ProductID',
            'SupplierID' => 'required|exists:Supplier,SupplierID',
            'PurchaseOrderID' => 'nullable|integer|exists:PurchaseOrder,PurchaseOrderID',
            'Quantity' => ['required', 'numeric', 'gt:0', 'max:9999999', 'decimal:0,2'],
            'Description' => 'required|string|max:500',
            'DateRecorded' => 'required|date',
            'DamageType' => 'required|in:'.implode(',', array_keys(DamagedProduct::DAMAGE_TYPES)),
            'InspectionNotes' => 'nullable|string|max:1000',
            'WarehouseLocation' => 'nullable|string|max:100',
            'Remarks' => 'nullable|string|max:500',
            'Image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $data['Quantity'] = Quantity::normalize($data['Quantity']);
        $damagedProduct = Product::find($data['ProductID']);
        if (is_float($data['Quantity']) && ! $damagedProduct->AllowsFractionalQuantity) {
            throw ValidationException::withMessages([
                'Quantity' => "\"{$damagedProduct->ProductName}\" is counted in whole ".UnitOfMeasure::plural($damagedProduct->baseUnit()).'.',
            ]);
        }

        $newImagePath = $request->hasFile('Image') ? $request->file('Image')->store('damages', 'public') : null;
        $sameProduct = (int) $data['ProductID'] === (int) $damage->ProductID;

        try {
            DB::transaction(function () use ($data, $damage, $newImagePath, $sameProduct) {
                if ($sameProduct) {
                    $inventory = Inventory::where('ProductID', $damage->ProductID)->lockForUpdate()->first();
                    $available = ($inventory->Quantity ?? 0) + $damage->Quantity;

                    if ($data['Quantity'] > $available) {
                        throw new \RuntimeException("Cannot record {$data['Quantity']} damaged units — only {$available} in stock.");
                    }

                    if ($inventory) {
                        $newQuantity = max(0, $available - $data['Quantity']);
                        $inventory->Quantity = $newQuantity;
                        $inventory->Status = Inventory::resolveStatus($newQuantity, $inventory->ReorderThreshold);
                        $inventory->save();
                    }
                } else {
                    $oldInventory = Inventory::where('ProductID', $damage->ProductID)->lockForUpdate()->first();
                    $newInventory = Inventory::where('ProductID', $data['ProductID'])->lockForUpdate()->first();
                    $available = $newInventory->Quantity ?? 0;

                    if ($data['Quantity'] > $available) {
                        throw new \RuntimeException("Cannot record {$data['Quantity']} damaged units — only {$available} in stock.");
                    }

                    if ($oldInventory) {
                        $oldInventory->Quantity += $damage->Quantity;
                        $oldInventory->Status = Inventory::resolveStatus($oldInventory->Quantity, $oldInventory->ReorderThreshold);
                        $oldInventory->save();
                    }

                    if ($newInventory) {
                        $newQuantity = max(0, $newInventory->Quantity - $data['Quantity']);
                        $newInventory->Quantity = $newQuantity;
                        $newInventory->Status = Inventory::resolveStatus($newQuantity, $newInventory->ReorderThreshold);
                        $newInventory->save();
                    }
                }

                $damage->update([
                    'ProductID' => $data['ProductID'],
                    'SupplierID' => $data['SupplierID'],
                    'PurchaseOrderID' => $data['PurchaseOrderID'] ?? null,
                    'Quantity' => $data['Quantity'],
                    'Description' => $data['Description'],
                    'DateRecorded' => $data['DateRecorded'],
                    'DamageType' => $data['DamageType'],
                    'InspectionNotes' => $data['InspectionNotes'] ?? null,
                    'WarehouseLocation' => $data['WarehouseLocation'] ?? null,
                    'Remarks' => $data['Remarks'] ?? null,
                    'ImagePath' => $newImagePath ?? $damage->ImagePath,
                ]);
            });
        } catch (\RuntimeException $e) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }
            throw ValidationException::withMessages(['Quantity' => $e->getMessage()]);
        }

        ActivityLog::record('damage.updated', "Updated damage record #{$damage->DamageID}");
        ActionNotifier::toAdmins('Damage Record Updated', "Damage record #{$damage->DamageID} (\"{$damage->product?->ProductName}\") has been updated.", route('admin.damages.index'), 'triangle-alert');

        return redirect()->route('admin.damages.index')->with('success', 'Damaged product record updated successfully.');
    }

    public function destroy(DamagedProduct $damage)
    {
        if ($damage->Status !== DamagedProduct::STATUS_PENDING) {
            return back()->with('error', 'Only pending damage records can be deleted.');
        }

        $damageId = $damage->DamageID;
        $productName = $damage->product?->ProductName ?? 'Unknown Product';

        DB::transaction(function () use ($damage) {
            $inventory = Inventory::where('ProductID', $damage->ProductID)->lockForUpdate()->first();
            if ($inventory) {
                $inventory->Quantity += $damage->Quantity;
                $inventory->Status = Inventory::resolveStatus($inventory->Quantity, $inventory->ReorderThreshold);
                $inventory->save();
            }

            $damage->delete();
        });

        ActivityLog::record('damage.deleted', "Deleted damage record #{$damageId} for \"{$productName}\"");
        ActionNotifier::toAdmins('Damage Record Deleted', "Damage record #{$damageId} for \"{$productName}\" has been deleted and its quantity restored to inventory.", route('admin.damages.index'), 'triangle-alert', 'danger');

        return redirect()->route('admin.damages.index')->with('success', 'Damaged product record deleted successfully.');
    }

    public function markForSupplierReturn(DamagedProduct $damage)
    {
        if ($damage->Status !== DamagedProduct::STATUS_PENDING) {
            return back()->with('error', 'Only pending damage records can be marked for supplier return.');
        }

        $damage->update(['Status' => DamagedProduct::STATUS_FOR_SUPPLIER_RETURN]);
        ActivityLog::record('damage.marked_for_supplier_return', "Marked damage #{$damage->DamageID} for supplier return");
        ActionNotifier::toAdmins('Damage Marked for Supplier Return', "Damage record #{$damage->DamageID} (\"{$damage->product?->ProductName}\") is now pending supplier return.", route('admin.damages.index'), 'truck', 'warning');

        return back()->with('success', 'Damage record marked for supplier return.');
    }

    public function confirmSupplierReturn(DamagedProduct $damage)
    {
        if ($damage->Status !== DamagedProduct::STATUS_FOR_SUPPLIER_RETURN) {
            return back()->with('error', 'Only records marked "For Supplier Return" can be confirmed as returned.');
        }

        $damage->update([
            'Status' => DamagedProduct::STATUS_RETURNED_TO_SUPPLIER,
            'ResolvedBy' => auth()->id(),
            'ResolvedDate' => now(),
        ]);
        ActivityLog::record('damage.returned_to_supplier', "Confirmed damage #{$damage->DamageID} returned to supplier");
        ActionNotifier::toAdmins('Damage Returned to Supplier', "Damage record #{$damage->DamageID} (\"{$damage->product?->ProductName}\") has been returned to the supplier.", route('admin.damages.index'), 'truck');

        return back()->with('success', 'Damage record confirmed as returned to supplier.');
    }

    public function markDisposed(DamagedProduct $damage)
    {
        if (! in_array($damage->Status, [DamagedProduct::STATUS_PENDING, DamagedProduct::STATUS_FOR_SUPPLIER_RETURN], true)) {
            return back()->with('error', 'This damage record cannot be marked as disposed.');
        }

        $damage->update([
            'Status' => DamagedProduct::STATUS_DISPOSED,
            'ResolvedBy' => auth()->id(),
            'ResolvedDate' => now(),
        ]);
        ActivityLog::record('damage.disposed', "Disposed of damage #{$damage->DamageID}");
        ActionNotifier::toAdmins('Damage Record Disposed', "Damage record #{$damage->DamageID} (\"{$damage->product?->ProductName}\") has been marked as disposed.", route('admin.damages.index'), 'triangle-alert', 'danger');

        return back()->with('success', 'Damage record marked as disposed.');
    }

    public function bulkConfirmSupplierReturn(Request $request)
    {
        $data = $request->validate([
            'damage_ids' => 'required|array|min:1',
            'damage_ids.*' => 'integer|exists:DamagedProduct,DamageID',
        ]);

        $count = 0;

        DB::transaction(function () use ($data, &$count) {
            $damages = DamagedProduct::whereIn('DamageID', $data['damage_ids'])
                ->where('Status', DamagedProduct::STATUS_FOR_SUPPLIER_RETURN)
                ->lockForUpdate()
                ->get();

            foreach ($damages as $damage) {
                $damage->update([
                    'Status' => DamagedProduct::STATUS_RETURNED_TO_SUPPLIER,
                    'ResolvedBy' => auth()->id(),
                    'ResolvedDate' => now(),
                ]);

                ActivityLog::record(
                    'damage.returned_to_supplier',
                    "Confirmed damage #{$damage->DamageID} ({$damage->Quantity} x \"{$damage->product?->ProductName}\") returned to supplier"
                );

                $count++;
            }
        });

        if ($count === 0) {
            return back()->with('error', 'None of the selected records were eligible — they may have already been processed.');
        }

        ActionNotifier::toAdmins('Damage Returned to Supplier', "{$count} damage record(s) have been returned to the supplier.", route('admin.damages.index'), 'truck');

        return back()->with('success', "{$count} damage record(s) confirmed returned to supplier.");
    }

    public function receiveReplacement(Request $request, DamagedProduct $damage)
    {
        if ($damage->Status !== DamagedProduct::STATUS_RETURNED_TO_SUPPLIER) {
            return back()->with('error', 'Only records already returned to the supplier can receive a replacement.');
        }

        $data = $request->validate([
            'replacement_quantity' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
        ]);

        $qty = Quantity::normalize($data['replacement_quantity'] ?? $damage->Quantity);

        try {
            DB::transaction(function () use ($damage, $qty) {
                $locked = DamagedProduct::where('DamageID', $damage->DamageID)
                    ->where('Status', DamagedProduct::STATUS_RETURNED_TO_SUPPLIER)
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    throw new \RuntimeException('This record is no longer awaiting a supplier replacement — it may have already been processed.');
                }

                $inventory = Inventory::where('ProductID', $damage->ProductID)->lockForUpdate()->first();
                if (! $inventory) {
                    $inventory = Inventory::firstOrCreate(
                        ['ProductID' => $damage->ProductID],
                        ['Quantity' => 0, 'Status' => 'Out of Stock']
                    );
                }

                $inventory->Quantity += $qty;
                $inventory->Status = Inventory::resolveStatus($inventory->Quantity, $inventory->ReorderThreshold);
                StockBatches::receivingAs([
                    'Source' => StockBatch::SOURCE_SUPPLIER_REPLACEMENT,
                    'SupplierID' => $locked->SupplierID,
                    'PurchaseOrderID' => $locked->PurchaseOrderID,
                    'DamageID' => $locked->DamageID,
                    'UnitCost' => $locked->unitCost(),
                ], fn () => $inventory->save());

                $locked->update([
                    'Status' => DamagedProduct::STATUS_REPLACEMENT_RECEIVED,
                    'ResolvedBy' => auth()->id(),
                    'ResolvedDate' => now(),
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLog::record(
            'damage.replacement_received',
            "Received {$qty} x replacement for \"{$damage->product?->ProductName}\" (Damage #{$damage->DamageID}) — inventory increased"
        );
        ActionNotifier::toAdmins('Supplier Replacement Received', "Received {$qty} x replacement for \"{$damage->product?->ProductName}\" (Damage #{$damage->DamageID}). Inventory has been updated.", route('admin.damages.index'), 'package', 'success');

        return back()->with('success', 'Replacement received and inventory updated.');
    }

    public function cancel(DamagedProduct $damage)
    {
        if ($damage->Status !== DamagedProduct::STATUS_FOR_SUPPLIER_RETURN) {
            return back()->with('error', 'Only records still pending supplier return can be cancelled.');
        }

        try {
            DB::transaction(function () use ($damage) {
                $locked = DamagedProduct::where('DamageID', $damage->DamageID)
                    ->where('Status', DamagedProduct::STATUS_FOR_SUPPLIER_RETURN)
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    throw new \RuntimeException('This record is no longer pending supplier return — it may have already been processed.');
                }

                $inventory = Inventory::where('ProductID', $damage->ProductID)->lockForUpdate()->first();
                if (! $inventory) {
                    $inventory = Inventory::firstOrCreate(
                        ['ProductID' => $damage->ProductID],
                        ['Quantity' => 0, 'Status' => 'Out of Stock']
                    );
                }

                $inventory->Quantity += $damage->Quantity;
                $inventory->Status = Inventory::resolveStatus($inventory->Quantity, $inventory->ReorderThreshold);
                StockBatches::receivingAs([
                    'Source' => StockBatch::SOURCE_RESTORED,
                    'SupplierID' => $locked->SupplierID,
                    'PurchaseOrderID' => $locked->PurchaseOrderID,
                    'DamageID' => $locked->DamageID,
                    'UnitCost' => $locked->unitCost(),
                ], fn () => $inventory->save());

                $locked->update([
                    'Status' => DamagedProduct::STATUS_CANCELLED,
                    'ResolvedBy' => auth()->id(),
                    'ResolvedDate' => now(),
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLog::record('damage.cancelled', "Cancelled damage #{$damage->DamageID} ({$damage->Quantity} x \"{$damage->product?->ProductName}\") — restored to inventory");
        ActionNotifier::toAdmins('Damage Record Cancelled', "Damage record #{$damage->DamageID} ({$damage->Quantity} x \"{$damage->product?->ProductName}\") has been cancelled and restored to inventory.", route('admin.damages.index'), 'rotate-ccw');

        return back()->with('success', 'Damage record cancelled and quantity restored to inventory.');
    }
}
