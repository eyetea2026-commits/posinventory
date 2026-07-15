<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DamagedProduct;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    // Display damaged products list + dashboard KPIs
    public function index(Request $request)
    {
        $search = $request->get('search');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $status = $request->get('status');
        $damageType = $request->get('damage_type');
        $supplierId = $request->get('supplier_id');
        $poId = $request->get('po_id');

        $damagedProducts = DamagedProduct::with(['product', 'supplier', 'purchaseOrder'])
            ->when($search, function ($query) use ($search) {
                return $query->whereHas('product', function ($q) use ($search) {
                    $q->where('ProductName', 'like', "%{$search}%");
                });
            })
            ->when($dateFrom, function ($query) use ($dateFrom) {
                return $query->whereDate('DateRecorded', '>=', $dateFrom);
            })
            ->when($dateTo, function ($query) use ($dateTo) {
                return $query->whereDate('DateRecorded', '<=', $dateTo);
            })
            ->when($status, function ($query) use ($status) {
                return $query->where('Status', $status);
            })
            ->when($damageType, function ($query) use ($damageType) {
                return $query->where('DamageType', $damageType);
            })
            ->when($supplierId, function ($query) use ($supplierId) {
                return $query->where('SupplierID', $supplierId);
            })
            ->when($poId, function ($query) use ($poId) {
                return $query->where('PurchaseOrderID', $poId);
            })
            ->orderBy('DamageID', 'desc')
            ->paginate(15)
            ->withQueryString();

        $kpis = [
            'total' => DamagedProduct::count(),
            'pending_supplier_return' => DamagedProduct::where('Status', DamagedProduct::STATUS_FOR_SUPPLIER_RETURN)->count(),
            'returned_to_supplier' => DamagedProduct::where('Status', DamagedProduct::STATUS_RETURNED_TO_SUPPLIER)->count(),
            'disposed' => DamagedProduct::where('Status', DamagedProduct::STATUS_DISPOSED)->count(),
            'total_cost' => (float) DamagedProduct::join('Product', 'DamagedProduct.ProductID', '=', 'Product.ProductID')
                ->selectRaw('SUM(DamagedProduct.Quantity * COALESCE(Product.CostPrice, 0)) as cost')
                ->value('cost'),
        ];

        $recentlyAdded = DamagedProduct::with(['product', 'supplier'])
            ->orderByDesc('DamageID')
            ->take(5)
            ->get();

        $suppliers = Supplier::orderBy('SupplierName')->get();

        return view('admin.damages.index', compact(
            'damagedProducts', 'search', 'dateFrom', 'dateTo', 'status', 'damageType', 'supplierId', 'poId',
            'kpis', 'recentlyAdded', 'suppliers'
        ));
    }

    // Show create form
    public function create()
    {
        $products = Product::with('inventory')->orderBy('ProductName')->get();
        $suppliers = Supplier::orderBy('SupplierName')->get();
        $purchaseOrders = PurchaseOrder::with('supplier')->orderByDesc('PurchaseOrderID')->get();
        return view('admin.damages.create', compact('products', 'suppliers', 'purchaseOrders'));
    }

    // Store new damaged product record
    public function store(Request $request)
    {
        $data = $request->validate([
            'ProductID' => 'required|exists:Product,ProductID',
            'SupplierID' => 'required|exists:Supplier,SupplierID',
            'PurchaseOrderID' => 'nullable|integer|exists:PurchaseOrder,PurchaseOrderID',
            'Quantity' => 'required|integer|min:1',
            'Description' => 'required|string|max:500',
            'DateRecorded' => 'required|date',
            'DamageType' => 'required|in:' . implode(',', array_keys(DamagedProduct::DAMAGE_TYPES)),
            'InspectionNotes' => 'nullable|string|max:1000',
            'WarehouseLocation' => 'nullable|string|max:100',
            'Remarks' => 'nullable|string|max:500',
        ], [
            'ProductID.required' => 'Please select a product.',
            'SupplierID.required' => 'Please select a supplier.',
            'Quantity.required' => 'Quantity is required.',
            'Quantity.min' => 'Quantity must be at least 1.',
            'Description.required' => 'Damage description is required.',
            'DateRecorded.required' => 'Date is required.',
            'DamageType.required' => 'Please select a damage type.',
        ]);

        // Reject a damage quantity greater than what's actually in stock —
        // otherwise it silently clamps to 0 below and the damage record
        // ends up overstating how many units were actually lost.
        $inventory = Inventory::where('ProductID', $data['ProductID'])->first();
        $available = $inventory->Quantity ?? 0;
        if ($data['Quantity'] > $available) {
            return back()
                ->withErrors(['Quantity' => "Cannot record {$data['Quantity']} damaged units — only {$available} in stock."])
                ->withInput();
        }

        $damage = DB::transaction(function () use ($data, $inventory) {
            $damage = DamagedProduct::create([
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
                'Status' => DamagedProduct::STATUS_PENDING,
            ]);

            if ($inventory) {
                $newQuantity = max(0, $inventory->Quantity - $data['Quantity']);
                $inventory->Quantity = $newQuantity;
                $inventory->Status = $newQuantity > 0 ? ($newQuantity <= 10 ? 'Low Stock' : 'Available') : 'Out of Stock';
                $inventory->save();
            }

            return $damage;
        });

        $productName = $damage->product?->ProductName ?? 'Unknown Product';
        ActivityLog::record('damage.created', "Recorded {$damage->Quantity} x \"{$productName}\" as damaged (Supplier: \"{$damage->supplier?->SupplierName}\")");

        return redirect()->route('admin.damages.index')->with('success', 'Damaged product recorded successfully.');
    }

    // Show edit form
    public function edit(DamagedProduct $damage)
    {
        if ($damage->Status !== DamagedProduct::STATUS_PENDING) {
            return redirect()->route('admin.damages.index')->with('error', 'Only pending damage records can be edited.');
        }

        $products = Product::with('inventory')->orderBy('ProductName')->get();
        $suppliers = Supplier::orderBy('SupplierName')->get();
        $purchaseOrders = PurchaseOrder::with('supplier')->orderByDesc('PurchaseOrderID')->get();
        return view('admin.damages.edit', compact('damage', 'products', 'suppliers', 'purchaseOrders'));
    }

    // Update damaged product record
    public function update(Request $request, DamagedProduct $damage)
    {
        if ($damage->Status !== DamagedProduct::STATUS_PENDING) {
            return back()->with('error', 'Only pending damage records can be edited.');
        }

        $data = $request->validate([
            'ProductID' => 'required|exists:Product,ProductID',
            'SupplierID' => 'required|exists:Supplier,SupplierID',
            'PurchaseOrderID' => 'nullable|integer|exists:PurchaseOrder,PurchaseOrderID',
            'Quantity' => 'required|integer|min:1',
            'Description' => 'required|string|max:500',
            'DateRecorded' => 'required|date',
            'DamageType' => 'required|in:' . implode(',', array_keys(DamagedProduct::DAMAGE_TYPES)),
            'InspectionNotes' => 'nullable|string|max:1000',
            'WarehouseLocation' => 'nullable|string|max:100',
            'Remarks' => 'nullable|string|max:500',
        ]);

        // Reject a damage quantity greater than what would actually be
        // available once the old damaged quantity is restored — otherwise
        // it silently clamps to 0 below and the record overstates the loss.
        if ((int) $data['ProductID'] === (int) $damage->ProductID) {
            $currentInventory = Inventory::where('ProductID', $damage->ProductID)->first();
            $available = ($currentInventory->Quantity ?? 0) + $damage->Quantity;
        } else {
            $currentInventory = Inventory::where('ProductID', $data['ProductID'])->first();
            $available = $currentInventory->Quantity ?? 0;
        }
        if ($data['Quantity'] > $available) {
            return back()
                ->withErrors(['Quantity' => "Cannot record {$data['Quantity']} damaged units — only {$available} in stock."])
                ->withInput();
        }

        DB::transaction(function () use ($data, $damage) {
            // Restore old quantity to inventory
            $oldInventory = Inventory::where('ProductID', $damage->ProductID)->first();
            if ($oldInventory) {
                $oldInventory->Quantity += $damage->Quantity;
                $oldInventory->Status = $oldInventory->Quantity > 0 ? ($oldInventory->Quantity <= 10 ? 'Low Stock' : 'Available') : 'Out of Stock';
                $oldInventory->save();
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
            ]);

            // Decrease new quantity from inventory
            $newInventory = Inventory::where('ProductID', $data['ProductID'])->first();
            if ($newInventory) {
                $newQuantity = max(0, $newInventory->Quantity - $data['Quantity']);
                $newInventory->Quantity = $newQuantity;
                $newInventory->Status = $newQuantity > 0 ? ($newQuantity <= 10 ? 'Low Stock' : 'Available') : 'Out of Stock';
                $newInventory->save();
            }
        });

        ActivityLog::record('damage.updated', "Updated damage record #{$damage->DamageID}");

        return redirect()->route('admin.damages.index')->with('success', 'Damaged product record updated successfully.');
    }

    // Delete damaged product record
    public function destroy(DamagedProduct $damage)
    {
        if ($damage->Status !== DamagedProduct::STATUS_PENDING) {
            return back()->with('error', 'Only pending damage records can be deleted.');
        }

        $damageId = $damage->DamageID;
        $productName = $damage->product?->ProductName ?? 'Unknown Product';

        DB::transaction(function () use ($damage) {
            // Restore quantity to inventory
            $inventory = Inventory::where('ProductID', $damage->ProductID)->first();
            if ($inventory) {
                $inventory->Quantity += $damage->Quantity;
                $inventory->Status = $inventory->Quantity > 0 ? ($inventory->Quantity <= 10 ? 'Low Stock' : 'Available') : 'Out of Stock';
                $inventory->save();
            }

            $damage->delete();
        });

        ActivityLog::record('damage.deleted', "Deleted damage record #{$damageId} for \"{$productName}\"");

        return redirect()->route('admin.damages.index')->with('success', 'Damaged product record deleted successfully.');
    }

    public function markForSupplierReturn(DamagedProduct $damage)
    {
        if ($damage->Status !== DamagedProduct::STATUS_PENDING) {
            return back()->with('error', 'Only pending damage records can be marked for supplier return.');
        }

        $damage->update(['Status' => DamagedProduct::STATUS_FOR_SUPPLIER_RETURN]);
        ActivityLog::record('damage.marked_for_supplier_return', "Marked damage #{$damage->DamageID} for supplier return");

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

        return back()->with('success', 'Damage record marked as disposed.');
    }

    public function export(Request $request)
    {
        $format = $request->get('format', 'csv');
        $filters = $request->only(['search', 'date_from', 'date_to', 'status', 'damage_type', 'supplier_id', 'po_id']);

        if ($format === 'pdf') {
            $damagedProducts = $this->filteredQuery($filters)->orderByDesc('DamageID')->get();
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.damages.pdf', compact('damagedProducts'));
            return $pdf->download('damage-records-' . now()->format('Y-m-d') . '.pdf');
        }

        if ($format === 'excel') {
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\DamagesExport($filters),
                'damage-records-' . now()->format('Y-m-d') . '.xlsx'
            );
        }

        return $this->exportCSV($filters);
    }

    private function filteredQuery(array $filters)
    {
        return DamagedProduct::with(['product', 'supplier', 'purchaseOrder'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                return $query->whereHas('product', function ($q) use ($search) {
                    $q->where('ProductName', 'like', "%{$search}%");
                });
            })
            ->when($filters['date_from'] ?? null, function ($query, $dateFrom) {
                return $query->whereDate('DateRecorded', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function ($query, $dateTo) {
                return $query->whereDate('DateRecorded', '<=', $dateTo);
            })
            ->when($filters['status'] ?? null, function ($query, $status) {
                return $query->where('Status', $status);
            })
            ->when($filters['damage_type'] ?? null, function ($query, $damageType) {
                return $query->where('DamageType', $damageType);
            })
            ->when($filters['supplier_id'] ?? null, function ($query, $supplierId) {
                return $query->where('SupplierID', $supplierId);
            })
            ->when($filters['po_id'] ?? null, function ($query, $poId) {
                return $query->where('PurchaseOrderID', $poId);
            });
    }

    private function exportCSV(array $filters)
    {
        return new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($filters) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Date', 'Product', 'Supplier', 'PO#', 'Quantity', 'Damage Type', 'Status', 'Description']);

            $this->filteredQuery($filters)->orderByDesc('DamageID')->chunk(100, function ($items) use ($handle) {
                foreach ($items as $item) {
                    fputcsv($handle, [
                        $item->DamageID,
                        optional($item->DateRecorded)->format('Y-m-d'),
                        $this->csvSafe($item->product?->ProductName ?? 'N/A'),
                        $this->csvSafe($item->supplier?->SupplierName ?? 'N/A'),
                        $item->PurchaseOrderID ?? '',
                        $item->Quantity,
                        $this->csvSafe(DamagedProduct::DAMAGE_TYPES[$item->DamageType] ?? $item->DamageType),
                        $item->Status,
                        $this->csvSafe($item->Description),
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="damage-records-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    private function csvSafe($value)
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}
