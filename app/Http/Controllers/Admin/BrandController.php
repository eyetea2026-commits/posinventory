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

    /**
     * The exact "This brand already exists under the selected category."
     * check — case-insensitive and whitespace-trimmed, scoped to one
     * category.
     */
    private function duplicateExists(string $name, int $categoryId): bool
    {
        $normalized = mb_strtolower(trim($name));

        return Brand::where('BrandNameNormalized', $normalized)
            ->where('CategoryID', $categoryId)
            ->exists();
    }

    // Add a Brand to a Category — called from the Edit Category modal's
    // inline "Brands" panel.
    public function store(Request $request, Category $category)
    {
        $data = $request->validate([
            'BrandName' => ['required', 'string', 'max:100'],
        ], [
            'BrandName.required' => 'Brand name is required.',
        ]);

        if ($this->duplicateExists($data['BrandName'], $category->CategoryID)) {
            throw ValidationException::withMessages([
                'BrandName' => 'This brand already exists under the selected category.',
            ]);
        }

        try {
            $brand = Brand::create([
                'BrandName' => trim($data['BrandName']),
                'CategoryID' => $category->CategoryID,
            ]);
        } catch (QueryException $e) {
            // Defense in depth: a concurrent request could pass the check
            // above and still collide on the database's own unique index
            // (BrandNameNormalized, CategoryID) a moment later. Never let
            // that raw constraint error reach the user.
            if ($e->getCode() === '23000') {
                throw ValidationException::withMessages([
                    'BrandName' => 'This brand already exists under the selected category.',
                ]);
            }

            throw $e;
        }

        return response()->json([
            'success' => true,
            'brand' => ['BrandID' => $brand->BrandID, 'BrandName' => $brand->BrandName],
        ]);
    }

    // Remove a Brand — called from the Edit Category modal's inline
    // "Brands" panel.
    public function destroy(Brand $brand)
    {
        if ($brand->products()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove a brand that still has products assigned to it.',
            ], 422);
        }

        $brand->delete();

        return response()->json(['success' => true]);
    }
}
