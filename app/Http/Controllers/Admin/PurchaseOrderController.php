<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
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

        $purchaseOrders = PurchaseOrder::with(['supplier', 'items.product'])
            ->when($search, function ($query, $search) {
                $query->where('Status', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($supplier) use ($search) {
                        $supplier->where('SupplierName', 'like', "%{$search}%");
                    });
            })
            ->orderByDesc('PurchaseDate')
            ->paginate(15)
            ->withQueryString();

        return view('admin.purchase-orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'search' => $search,
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'items.product']);

        return view('admin.purchase-orders.show', [
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    public function create()
    {
        return view('admin.purchase-orders.create', [
            'suppliers' => Supplier::orderBy('SupplierName')->get(),
            'products' => Product::orderBy('ProductName')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'SupplierID' => ['required', 'integer', 'exists:Supplier,SupplierID'],
            'PurchaseDate' => ['required', 'date'],
            'ExpectedDeliveryDate' => ['nullable', 'date', 'after_or_equal:PurchaseDate'],
            'Status' => ['required', 'string', 'in:pending,approved'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'integer', 'exists:Product,ProductID'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($data) {
            $purchaseOrder = PurchaseOrder::create([
                'PurchaseDate' => $data['PurchaseDate'],
                'ExpectedDeliveryDate' => $data['ExpectedDeliveryDate'] ?? null,
                'Status' => $data['Status'],
                'SupplierID' => $data['SupplierID'],
            ]);

            foreach ($data['products'] as $item) {
                if (empty($item['product_id']) || empty($item['quantity'])) {
                    continue;
                }

                PurchaseOrderItem::create([
                    'PurchaseOrderID' => $purchaseOrder->PurchaseOrderID,
                    'ProductID' => $item['product_id'],
                    'Quantity' => $item['quantity'],
                ]);
            }
        });

        return redirect()->route('admin.purchase-orders.index')->with('status', 'Purchase order created successfully.');
    }
}
