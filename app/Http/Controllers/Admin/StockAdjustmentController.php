<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\Product;
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
            'Reason' => ['required', 'string', 'max:255'],
            'Date' => ['required', 'date'],
        ]);

        // Get current inventory to validate adjustment won't cause negative stock
        $inventory = Inventory::where('ProductID', $data['ProductID'])->first();
        $currentQty = $inventory ? $inventory->Quantity : 0;
        $newQty = $currentQty + $data['QuantityAdjust'];

        // Prevent negative inventory
        if ($newQty < 0) {
            return back()->withErrors(['QuantityAdjust' => 'Cannot reduce stock below zero. Current stock: ' . $currentQty]);
        }

        DB::transaction(function () use ($data, $newQty) {
            StockAdjustment::create($data);

            $inventory = Inventory::firstOrCreate(
                ['ProductID' => $data['ProductID']],
                ['Quantity' => 0, 'Status' => 'Out of Stock']
            );

            $inventory->Quantity = $newQty;

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
        $productName = $product?->ProductName ?? 'Unknown product';
        $sign = $data['QuantityAdjust'] >= 0 ? '+' : '';
        ActivityLog::record('stock.adjusted', "Adjusted \"{$productName}\" by {$sign}{$data['QuantityAdjust']} (new total: {$newQty})");

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

        return redirect()->route('admin.stock-adjustments.index')->with('status', 'Stock adjustment saved successfully.');
    }
}
