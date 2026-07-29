<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\User;
use App\Notifications\CategoryUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

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

        // Real-time search: matches the debounced-AJAX pattern used by
        // Products/Inventory — return just the rendered rows/pagination
        // instead of a full page reload.
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'rows' => view('admin.categories.partials.rows', ['categories' => $categories])->render(),
                'pagination' => view('admin.categories.partials.pagination', ['categories' => $categories])->render(),
            ]);
        }

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

        $category = Category::create([
            'CategoryName' => $request->CategoryName,
            'Description' => $request->Description
        ]);

        try {
            Notification::send(User::admins(), new CategoryUpdated($category, 'Created'));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch CategoryUpdated notification', [
                'category_id' => $category->CategoryID,
                'exception' => $e->getMessage(),
            ]);
        }

        return redirect()->route('admin.categories.index')->with('success', 'Category created successfully.');
    }

    // Show edit form
    public function edit(Request $request, Category $category)
    {
        // Edit Category modal: return just the rendered form fields instead
        // of a full page, so the modal can inject it without navigating.
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'html' => view('admin.categories.partials.category-form-fields', [
                    'category' => $category,
                ])->render(),
            ]);
        }

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

        try {
            Notification::send(User::admins(), new CategoryUpdated($category, 'Updated'));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch CategoryUpdated notification', [
                'category_id' => $category->CategoryID,
                'exception' => $e->getMessage(),
            ]);
        }

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