<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
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
    // Display categories list
    public function index(Request $request)
    {
        $search = $request->get('search');

        $categories = Category::when($search, function($query) use ($search) {
            return $query->where('CategoryName', 'like', "%{$search}%");
        })->orderBy('CategoryID', 'desc')->paginate(15)->withQueryString();

        return view('admin.categories.index', compact('categories', 'search'));
    }

    // Show create form
    public function create()
    {
        return view('admin.categories.create');
    }

    // Store new category
    public function store(Request $request)
    {
        $request->validate([
            'CategoryName' => 'required|string|max:100|unique:Category,CategoryName',
            'Description' => 'nullable|string|max:500'
        ], [
            'CategoryName.required' => 'Category name is required.',
            'CategoryName.unique' => 'This category already exists.',
        ]);

        Category::create([
            'CategoryName' => $request->CategoryName,
            'Description' => $request->Description
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Category created successfully.');
    }

    // Show edit form
    public function edit(Category $category)
    {
        return view('admin.categories.edit', compact('category'));
    }

    // Update category
    public function update(Request $request, Category $category)
    {
        $request->validate([
            'CategoryName' => 'required|string|max:100|unique:Category,CategoryName,' . $category->CategoryID . ',CategoryID',
            'Description' => 'nullable|string|max:500'
        ], [
            'CategoryName.required' => 'Category name is required.',
            'CategoryName.unique' => 'This category already exists.',
        ]);

        $category->update([
            'CategoryName' => $request->CategoryName,
            'Description' => $request->Description
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated successfully.');
    }

    // Delete category
    public function destroy(Category $category)
    {
        // Check if category has products
        if ($category->products()->count() > 0) {
            return redirect()->route('admin.categories.index')->with('error', 'Cannot delete category with associated products.');
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted successfully.');
    }
}