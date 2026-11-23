<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use App\Notifications\CategoryUpdated;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
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

    public function index(Request $request)
    {
        $search = $request->get('search');

        $categories = Category::withCount('products')
            ->when($search, function ($query) use ($search) {
                return $query->where('CategoryName', 'like', "%{$search}%");
            })
            ->orderByDesc('IsActive')
            ->orderBy('CategoryID', 'desc')
            ->paginate(15)->withQueryString();

        if ($request->boolean('ajax')) {
            return response()->json([
                'rows' => view('admin.categories.partials.rows', ['categories' => $categories])->render(),
                'pagination' => view('admin.categories.partials.pagination', ['categories' => $categories])->render(),
            ]);
        }

        return view('admin.categories.index', compact('categories', 'search'));
    }

    public function create()
    {
        return view('admin.categories.create');
    }

    public function checkName(Request $request)
    {
        $name = trim((string) $request->input('CategoryName', ''));
        $excludeId = $request->input('exclude_id');

        $normalize = function (string $value): string {
            return preg_replace('/\s+/', ' ', strtolower($value));
        };

        $nameTaken = false;
        if ($name !== '') {
            $normalized = $normalize($name);
            $nameTaken = Category::when($excludeId, function ($query, $excludeId) {
                $query->where('CategoryID', '!=', $excludeId);
            })->get()->contains(function ($existing) use ($normalize, $normalized) {
                return $normalize((string) ($existing->CategoryName ?? '')) === $normalized;
            });
        }

        return response()->json([
            'name' => $nameTaken,
            'name_value' => $name,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'CategoryName' => 'required|string|max:100|unique:Category,CategoryName',
            'Description' => 'nullable|string|max:500',
            'Brands' => ['nullable', 'array'],
            'Brands.*' => ['nullable', 'string', 'max:100'],
        ], [
            'CategoryName.required' => 'Category name is required.',
            'CategoryName.unique' => 'This category already exists.',
        ]);

        $tracksSerialNumbers = $request->boolean('TracksSerialNumbers');

        $category = DB::transaction(function () use ($data, $tracksSerialNumbers) {
            $category = Category::create([
                'CategoryName' => $data['CategoryName'],
                'Description' => $data['Description'] ?? null,
                'TracksSerialNumbers' => $tracksSerialNumbers,
            ]);

            $seen = [];
            foreach ($data['Brands'] ?? [] as $brandName) {
                $brandName = trim((string) $brandName);
                if ($brandName === '') {
                    continue;
                }
                $normalized = mb_strtolower($brandName);
                if (isset($seen[$normalized])) {
                    continue;
                }
                $seen[$normalized] = true;

                try {
                    Brand::create(['BrandName' => $brandName, 'CategoryID' => $category->CategoryID]);
                } catch (QueryException $e) {
                    if ($e->getCode() === '23000') {
                        throw ValidationException::withMessages([
                            'Brands' => 'This brand already exists under the selected category.',
                        ]);
                    }

                    throw $e;
                }
            }

            return $category;
        });

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

    public function edit(Request $request, Category $category)
    {
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'html' => view('admin.categories.partials.category-form-fields', [
                    'category' => $category,
                ])->render(),
            ]);
        }

        return view('admin.categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'CategoryName' => 'required|string|max:100|unique:Category,CategoryName,'.$category->CategoryID.',CategoryID',
            'Description' => 'nullable|string|max:500',
        ], [
            'CategoryName.required' => 'Category name is required.',
            'CategoryName.unique' => 'This category already exists.',
        ]);

        $category->update([
            'CategoryName' => $request->CategoryName,
            'Description' => $request->Description,
            ...($request->has('TracksSerialNumbers') ? ['TracksSerialNumbers' => $request->boolean('TracksSerialNumbers')] : []),
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
}
