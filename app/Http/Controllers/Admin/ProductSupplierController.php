<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\ProductSupplier;
use Illuminate\Http\Request;

class ProductSupplierController extends Controller
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

    public function index(Product $product)
    {
        return response()->json([
            'suppliers' => $product->suppliers()->with('supplier')->get(),
        ]);
    }

    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'SupplierID' => ['required', 'integer', 'exists:Supplier,SupplierID'],
            'CostPrice' => ['required', 'numeric', 'min:0'],
        ]);

        $existing = ProductSupplier::where('ProductID', $product->ProductID)
            ->where('SupplierID', $data['SupplierID'])
            ->first();

        $oldCost = $existing?->CostPrice;

        $productSupplier = ProductSupplier::updateOrCreate(
            ['ProductID' => $product->ProductID, 'SupplierID' => $data['SupplierID']],
            ['CostPrice' => $data['CostPrice']]
        );

        ProductCostHistory::log($product, $productSupplier->supplier, $oldCost, (float) $data['CostPrice'], ProductCostHistory::SOURCE_SUPPLIER_PIVOT_UPDATE);

        return response()->json(['success' => true, 'productSupplier' => $productSupplier->load('supplier')]);
    }

    public function markPreferred(ProductSupplier $productSupplier)
    {
        $productSupplier->markPreferred();

        return response()->json(['success' => true]);
    }

    public function destroy(ProductSupplier $productSupplier)
    {
        $productSupplier->delete();

        return response()->json(['success' => true]);
    }
}
