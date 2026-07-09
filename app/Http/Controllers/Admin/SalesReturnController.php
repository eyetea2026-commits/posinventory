<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\SalesTransaction;
use App\Models\StockAdjustment;
use App\Models\Inventory;
use Illuminate\Http\Request;

class SalesReturnController extends Controller
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

        $returns = SalesReturn::with(['transaction', 'product'])
            ->when($search, function ($query, $search) {
                $query->where('Status', 'like', "%{$search}%")
                    ->orWhere('Reason', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($product) use ($search) {
                        $product->where('ProductName', 'like', "%{$search}%");
                    });
            })
            ->orderByDesc('ReturnDate')
            ->paginate(15)
            ->withQueryString();

        return view('admin.sales-returns.index', [
            'returns' => $returns,
            'search' => $search,
        ]);
    }

    public function create()
    {
        return view('admin.sales-returns.create', [
            'transactions' => SalesTransaction::orderByDesc('SalesTransactionDate')->take(50)->get(),
            'products' => Product::orderBy('ProductName')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'SalesTransactionID' => ['required', 'integer', 'exists:SalesTransaction,SalesTransactionID'],
            'ProductID' => ['required', 'integer', 'exists:Product,ProductID'],
            'Quantity' => ['required', 'integer', 'min:1'],
            'Reason' => ['required', 'string', 'max:255'],
            'ReturnDate' => ['required', 'date'],
        ]);

        $salesReturn = SalesReturn::create([
            'SalesTransactionID' => $data['SalesTransactionID'],
            'ProductID' => $data['ProductID'],
            'Quantity' => $data['Quantity'],
            'Reason' => $data['Reason'],
            'ReturnDate' => $data['ReturnDate'],
            'Status' => 'pending',
        ]);

        return redirect()->route('admin.sales-returns.index')->with('status', 'Return request submitted successfully.');
    }

    public function approve(SalesReturn $salesReturn)
    {
        if ($salesReturn->Status !== 'pending') {
            return back()->with('status', 'Only pending returns can be approved.');
        }

        $salesReturn->update([
            'Status' => 'approved',
            'ApprovedBy' => auth()->id(),
        ]);

        $inventory = Inventory::firstOrCreate(
            ['ProductID' => $salesReturn->ProductID],
            ['Quantity' => 0, 'Status' => 'out-of-stock']
        );

        $inventory->Quantity += $salesReturn->Quantity;
        $inventory->Status = $inventory->Quantity > 0 ? 'available' : 'out-of-stock';
        $inventory->save();

        return back()->with('status', 'Return approved and inventory updated.');
    }

    public function reject(SalesReturn $salesReturn)
    {
        if ($salesReturn->Status !== 'pending') {
            return back()->with('status', 'Only pending returns can be rejected.');
        }

        $salesReturn->update([
            'Status' => 'rejected',
            'ApprovedBy' => auth()->id(),
        ]);

        return back()->with('status', 'Return request rejected.');
    }
}
