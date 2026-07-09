<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockReceiving;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        ]);
    }

    public function create()
    {
        return view('admin.stock-receivings.create', [
            'products' => Product::orderBy('ProductName')->get(),
            'suppliers' => Supplier::orderBy('SupplierName')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'ProductID' => ['required', 'integer', 'exists:Product,ProductID'],
            'SupplierID' => ['required', 'integer', 'exists:Supplier,SupplierID'],
            'Quantity' => ['required', 'integer', 'min:1'],
            'ReceiptNumber' => ['required', 'string', 'max:50', 'unique:StockReceiving,ReceiptNumber'],
            'DateReceived' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($data) {
            StockReceiving::create($data);

            $inventory = Inventory::firstOrCreate(
                ['ProductID' => $data['ProductID']],
                ['Quantity' => 0, 'Status' => 'Out of Stock']
            );

            $inventory->Quantity += $data['Quantity'];

            // Update status based on quantity
            if ($inventory->Quantity <= 0) {
                $inventory->Status = 'Out of Stock';
            } elseif ($inventory->Quantity <= 10) {
                $inventory->Status = 'Low Stock';
            } else {
                $inventory->Status = 'Available';
            }

            $inventory->save();
        });

        $productName = Product::find($data['ProductID'])?->ProductName ?? 'Unknown product';
        $supplierName = Supplier::find($data['SupplierID'])?->SupplierName ?? 'Unknown supplier';
        ActivityLog::record('stock.received', "Received {$data['Quantity']} x \"{$productName}\" from \"{$supplierName}\"");

        return redirect()->route('admin.stock-receivings.index')->with('status', 'Stock receiving recorded successfully.');
    }
}
