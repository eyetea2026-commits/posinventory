<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DamagedProduct;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Inventory;
use Illuminate\Http\Request;

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

    // Display damaged products list
    public function index(Request $request)
    {
        $search = $request->get('search');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $damagedProducts = DamagedProduct::with(['product', 'supplier'])
            ->when($search, function($query) use ($search) {
                return $query->whereHas('product', function($q) use ($search) {
                    $q->where('ProductName', 'like', "%{$search}%");
                });
            })
            ->when($dateFrom, function($query) use ($dateFrom) {
                return $query->whereDate('DateRecorded', '>=', $dateFrom);
            })
            ->when($dateTo, function($query) use ($dateTo) {
                return $query->whereDate('DateRecorded', '<=', $dateTo);
            })
            ->orderBy('DamageID', 'desc')
            ->get();

        return view('admin.damages.index', compact('damagedProducts', 'search', 'dateFrom', 'dateTo'));
    }

    // Show create form
    public function create()
    {
        $products = Product::with('inventory')->get();
        $suppliers = Supplier::all();
        return view('admin.damages.create', compact('products', 'suppliers'));
    }

    // Store new damaged product record
    public function store(Request $request)
    {
        $request->validate([
            'ProductID' => 'required|exists:Product,ProductID',
            'SupplierID' => 'required|exists:Supplier,SupplierID',
            'Quantity' => 'required|integer|min:1',
            'Description' => 'required|string|max:500',
            'DateRecorded' => 'required|date',
        ], [
            'ProductID.required' => 'Please select a product.',
            'SupplierID.required' => 'Please select a supplier.',
            'Quantity.required' => 'Quantity is required.',
            'Quantity.min' => 'Quantity must be at least 1.',
            'Description.required' => 'Damage description is required.',
            'DateRecorded.required' => 'Date is required.',
        ]);

        // Reject a damage quantity greater than what's actually in stock —
        // otherwise it silently clamps to 0 below and the damage record
        // ends up overstating how many units were actually lost.
        $inventory = Inventory::where('ProductID', $request->ProductID)->first();
        $available = $inventory->Quantity ?? 0;
        if ($request->Quantity > $available) {
            return back()
                ->withErrors(['Quantity' => "Cannot record {$request->Quantity} damaged units — only {$available} in stock."])
                ->withInput();
        }

        // Create damage record
        DamagedProduct::create([
            'ProductID' => $request->ProductID,
            'SupplierID' => $request->SupplierID,
            'Quantity' => $request->Quantity,
            'Description' => $request->Description,
            'DateRecorded' => $request->DateRecorded,
        ]);

        // Decrease inventory quantity
        if ($inventory) {
            $newQuantity = max(0, $inventory->Quantity - $request->Quantity);
            $inventory->Quantity = $newQuantity;
            $inventory->Status = $newQuantity > 0 ? ($newQuantity <= 10 ? 'Low Stock' : 'Available') : 'Out of Stock';
            $inventory->save();
        }

        return redirect()->route('admin.damages.index')->with('success', 'Damaged product recorded successfully.');
    }

    // Show edit form
    public function edit(DamagedProduct $damage)
    {
        $products = Product::with('inventory')->get();
        $suppliers = Supplier::all();
        return view('admin.damages.edit', compact('damage', 'products', 'suppliers'));
    }

    // Update damaged product record
    public function update(Request $request, DamagedProduct $damage)
    {
        $request->validate([
            'ProductID' => 'required|exists:Product,ProductID',
            'SupplierID' => 'required|exists:Supplier,SupplierID',
            'Quantity' => 'required|integer|min:1',
            'Description' => 'required|string|max:500',
            'DateRecorded' => 'required|date',
        ]);

        // Reject a damage quantity greater than what would actually be
        // available once the old damaged quantity is restored — otherwise
        // it silently clamps to 0 below and the record overstates the loss.
        if ((int) $request->ProductID === (int) $damage->ProductID) {
            $currentInventory = Inventory::where('ProductID', $damage->ProductID)->first();
            $available = ($currentInventory->Quantity ?? 0) + $damage->Quantity;
        } else {
            $currentInventory = Inventory::where('ProductID', $request->ProductID)->first();
            $available = $currentInventory->Quantity ?? 0;
        }
        if ($request->Quantity > $available) {
            return back()
                ->withErrors(['Quantity' => "Cannot record {$request->Quantity} damaged units — only {$available} in stock."])
                ->withInput();
        }

        // Restore old quantity to inventory
        $oldInventory = Inventory::where('ProductID', $damage->ProductID)->first();
        if ($oldInventory) {
            $oldInventory->Quantity += $damage->Quantity;
            $oldInventory->Status = $oldInventory->Quantity > 0 ? ($oldInventory->Quantity <= 10 ? 'Low Stock' : 'Available') : 'Out of Stock';
            $oldInventory->save();
        }

        // Update damage record
        $damage->update([
            'ProductID' => $request->ProductID,
            'SupplierID' => $request->SupplierID,
            'Quantity' => $request->Quantity,
            'Description' => $request->Description,
            'DateRecorded' => $request->DateRecorded,
        ]);

        // Decrease new quantity from inventory
        $newInventory = Inventory::where('ProductID', $request->ProductID)->first();
        if ($newInventory) {
            $newQuantity = max(0, $newInventory->Quantity - $request->Quantity);
            $newInventory->Quantity = $newQuantity;
            $newInventory->Status = $newQuantity > 0 ? ($newQuantity <= 10 ? 'Low Stock' : 'Available') : 'Out of Stock';
            $newInventory->save();
        }

        return redirect()->route('admin.damages.index')->with('success', 'Damaged product record updated successfully.');
    }

    // Delete damaged product record
    public function destroy(DamagedProduct $damage)
    {
        // Restore quantity to inventory
        $inventory = Inventory::where('ProductID', $damage->ProductID)->first();
        if ($inventory) {
            $inventory->Quantity += $damage->Quantity;
            $inventory->Status = $inventory->Quantity > 0 ? ($inventory->Quantity <= 10 ? 'Low Stock' : 'Available') : 'Out of Stock';
            $inventory->save();
        }

        $damage->delete();

        return redirect()->route('admin.damages.index')->with('success', 'Damaged product record deleted successfully.');
    }
}