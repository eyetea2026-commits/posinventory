<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Supplier;
use Illuminate\Http\Request;

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
            ->orderBy('SupplierName')
            ->paginate(15)
            ->withQueryString();

        return view('admin.suppliers.index', [
            'suppliers' => $suppliers,
            'search' => $search,
        ]);
    }

    public function create()
    {
        return view('admin.suppliers.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'SupplierName' => ['required', 'string', 'max:150', 'unique:Supplier,SupplierName'],
            'ContactNumber' => ['required', 'string', 'max:50'],
            'Email' => ['required', 'email', 'max:150', 'unique:Supplier,Email'],
            'Address' => ['required', 'string', 'max:255'],
        ]);

        $supplier = Supplier::create($data);

        ActivityLog::record('supplier.created', "Added supplier \"{$supplier->SupplierName}\"");

        return redirect()->route('admin.suppliers.index')->with('status', 'Supplier added successfully.');
    }

    public function edit(Supplier $supplier)
    {
        return view('admin.suppliers.edit', [
            'supplier' => $supplier,
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'SupplierName' => ['required', 'string', 'max:150', 'unique:Supplier,SupplierName,' . $supplier->SupplierID . ',SupplierID'],
            'ContactNumber' => ['required', 'string', 'max:50'],
            'Email' => ['required', 'email', 'max:150', 'unique:Supplier,Email,' . $supplier->SupplierID . ',SupplierID'],
            'Address' => ['required', 'string', 'max:255'],
        ]);

        $supplier->update($data);

        ActivityLog::record('supplier.updated', "Updated supplier \"{$supplier->SupplierName}\"");

        return redirect()->route('admin.suppliers.index')->with('status', 'Supplier updated successfully.');
    }
}
