<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\ProductSupplier;
use App\Services\ActionNotifier;
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
        // Only the supplier is chosen; a cost price is optional. A link
        // without one uses the product's own Cost Price (purchase orders
        // fall back to it), and re-adding a supplier keeps any price it
        // already has.
        $data = $request->validate([
            'SupplierID' => ['required', 'integer', 'exists:Supplier,SupplierID'],
            'CostPrice' => ['nullable', 'numeric', 'min:0'],
        ]);

        $existing = ProductSupplier::where('ProductID', $product->ProductID)
            ->where('SupplierID', $data['SupplierID'])
            ->first();

        $oldCost = $existing?->CostPrice;
        $newCost = $data['CostPrice'] ?? null;

        $productSupplier = ProductSupplier::updateOrCreate(
            ['ProductID' => $product->ProductID, 'SupplierID' => $data['SupplierID']],
            $newCost !== null ? ['CostPrice' => $newCost] : ($existing ? [] : ['CostPrice' => null])
        );

        if ($newCost !== null) {
            ProductCostHistory::log($product, $productSupplier->supplier, $oldCost, (float) $newCost, ProductCostHistory::SOURCE_SUPPLIER_PIVOT_UPDATE);
        }

        ActionNotifier::toAdmins('Product Supplier Added', "\"{$productSupplier->supplier?->SupplierName}\" is now a supplier of \"{$product->ProductName}\".", route('admin.products.index'), 'truck', 'success');

        return response()->json(['success' => true, 'productSupplier' => $productSupplier->load('supplier')]);
    }

    public function markPreferred(ProductSupplier $productSupplier)
    {
        $productSupplier->markPreferred();

        ActionNotifier::toAdmins('Preferred Supplier Changed', "\"{$productSupplier->supplier?->SupplierName}\" is now the preferred supplier of \"{$productSupplier->product?->ProductName}\".", route('admin.products.index'), 'truck');

        return response()->json(['success' => true]);
    }

    public function destroy(ProductSupplier $productSupplier)
    {
        $productSupplier->delete();

        ActionNotifier::toAdmins('Product Supplier Removed', "\"{$productSupplier->supplier?->SupplierName}\" is no longer a supplier of \"{$productSupplier->product?->ProductName}\".", route('admin.products.index'), 'truck', 'danger');

        return response()->json(['success' => true]);
    }
}
