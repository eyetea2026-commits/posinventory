<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BrandController extends Controller
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

    // Display brands list
    public function index(Request $request)
    {
        $search = $request->get('search');
        $categoryId = $request->get('category_id');

        $brands = Brand::with('category')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('BrandName', 'like', "%{$search}%")
                        ->orWhereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery->where('CategoryName', 'like', "%{$search}%");
                        });
                });
            })
            ->when($categoryId, function ($query, $categoryId) {
                $query->where('CategoryID', $categoryId);
            })
            ->orderBy('BrandName')
            ->paginate(15)
            ->withQueryString();

        $categories = Category::orderBy('CategoryName')->get();

        // Real-time search: same debounced-AJAX pattern as Categories/Products
        // — keyed off an explicit ?ajax=1 flag rather than the
        // X-Requested-With header alone, since the Add/Edit modal's own
        // AJAX POST carries that same header and follows its redirect back
        // to this same index route.
        if ($request->boolean('ajax')) {
            return response()->json([
                'rows' => view('admin.brands.partials.rows', ['brands' => $brands])->render(),
                'pagination' => view('admin.brands.partials.pagination', ['brands' => $brands])->render(),
            ]);
        }

        return view('admin.brands.index', compact('brands', 'categories', 'search', 'categoryId'));
    }

    /**
     * The exact "This brand already exists under the selected category."
     * check, shared by store()/update() — case-insensitive and
     * whitespace-trimmed, scoped to one category.
     */
    private function duplicateExists(string $name, int $categoryId, ?int $excludeId = null): bool
    {
        $normalized = mb_strtolower(trim($name));

        return Brand::where('BrandNameNormalized', $normalized)
            ->where('CategoryID', $categoryId)
            ->when($excludeId, function ($query, $excludeId) {
                $query->where('BrandID', '!=', $excludeId);
            })
            ->exists();
    }

    // Show create form (AJAX modal fields, mirrors Category's edit())
    public function create(Request $request)
    {
        $categories = Category::orderBy('CategoryName')->get();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'html' => view('admin.brands.partials.brand-form-fields', [
                    'brand' => null,
                    'categories' => $categories,
                ])->render(),
            ]);
        }

        return view('admin.brands.create', compact('categories'));
    }

    // Store new brand
    public function store(Request $request)
    {
        $data = $request->validate([
            'BrandName' => ['required', 'string', 'max:100'],
            'CategoryID' => ['required', 'integer', 'exists:Category,CategoryID'],
        ], [
            'BrandName.required' => 'Brand name is required.',
            'CategoryID.required' => 'Please select a category.',
        ]);

        if ($this->duplicateExists($data['BrandName'], (int) $data['CategoryID'])) {
            throw ValidationException::withMessages([
                'BrandName' => 'This brand already exists under the selected category.',
            ]);
        }

        try {
            $brand = Brand::create([
                'BrandName' => trim($data['BrandName']),
                'CategoryID' => $data['CategoryID'],
            ]);
        } catch (QueryException $e) {
            // Defense in depth: a concurrent request could pass the check
            // above and still collide on the database's own unique index
            // (BrandNameNormalized, CategoryID) a moment later. Never let
            // that raw constraint error reach the user.
            if ($this->isDuplicateConstraintViolation($e)) {
                throw ValidationException::withMessages([
                    'BrandName' => 'This brand already exists under the selected category.',
                ]);
            }

            throw $e;
        }

        return redirect()->route('admin.brands.index')->with('success', "Brand \"{$brand->BrandName}\" created successfully.");
    }

    // View brand details (read-only) — AJAX only, feeds the View Details modal
    public function show(Brand $brand)
    {
        $brand->load('category', 'products');

        return response()->json([
            'brand' => [
                'BrandID' => $brand->BrandID,
                'BrandName' => $brand->BrandName,
                'CategoryName' => $brand->category?->CategoryName ?? 'N/A',
                'ProductCount' => $brand->products->count(),
                'Products' => $brand->products->pluck('ProductName'),
            ],
        ]);
    }

    // Show edit form (AJAX modal fields, mirrors Category's edit())
    public function edit(Request $request, Brand $brand)
    {
        $categories = Category::orderBy('CategoryName')->get();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'html' => view('admin.brands.partials.brand-form-fields', [
                    'brand' => $brand,
                    'categories' => $categories,
                ])->render(),
            ]);
        }

        return view('admin.brands.edit', compact('brand', 'categories'));
    }

    // Update brand
    public function update(Request $request, Brand $brand)
    {
        $data = $request->validate([
            'BrandName' => ['required', 'string', 'max:100'],
            'CategoryID' => ['required', 'integer', 'exists:Category,CategoryID'],
        ], [
            'BrandName.required' => 'Brand name is required.',
            'CategoryID.required' => 'Please select a category.',
        ]);

        if ($this->duplicateExists($data['BrandName'], (int) $data['CategoryID'], $brand->BrandID)) {
            throw ValidationException::withMessages([
                'BrandName' => 'This brand already exists under the selected category.',
            ]);
        }

        try {
            $brand->update([
                'BrandName' => trim($data['BrandName']),
                'CategoryID' => $data['CategoryID'],
            ]);
        } catch (QueryException $e) {
            if ($this->isDuplicateConstraintViolation($e)) {
                throw ValidationException::withMessages([
                    'BrandName' => 'This brand already exists under the selected category.',
                ]);
            }

            throw $e;
        }

        return redirect()->route('admin.brands.index')->with('success', "Brand \"{$brand->BrandName}\" updated successfully.");
    }

    // Delete brand
    public function destroy(Brand $brand)
    {
        if ($brand->products()->count() > 0) {
            return redirect()->route('admin.brands.index')->with('error', 'Cannot delete a brand that still has products assigned to it.');
        }

        $brandName = $brand->BrandName;
        $brand->delete();

        return redirect()->route('admin.brands.index')->with('success', "Brand \"{$brandName}\" deleted successfully.");
    }

    /**
     * MySQL error 1062 / SQLite "UNIQUE constraint failed" both surface as
     * SQLSTATE 23000 — good enough to distinguish "some other row already
     * has this value" from any other query failure without depending on a
     * driver-specific error message.
     */
    private function isDuplicateConstraintViolation(QueryException $e): bool
    {
        return $e->getCode() === '23000';
    }
}
