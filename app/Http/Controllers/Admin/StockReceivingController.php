<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\Product;
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

        $product = Product::find($data['ProductID']);
        $supplier = Supplier::find($data['SupplierID']);
        $productName = $product?->ProductName ?? 'Unknown product';
        $supplierName = $supplier?->SupplierName ?? 'Unknown supplier';
        ActivityLog::record('stock.received', "Received {$data['Quantity']} x \"{$productName}\" from \"{$supplierName}\"");

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
