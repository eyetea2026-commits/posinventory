<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct()
    {
        // Middleware is applied at route level for better control
        // Auth check is done via role:admin middleware in routes
    }

    public function index(Request $request)
    {
        $search = $request->query('search');

        $users = User::with('role')
            ->when($search, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function create()
    {
        // Only allow creating Cashier role (role_id = 2)
        $roles = Role::where('role_name', 'cashier')->get();

        return view('admin.users.create', [
            'roles' => $roles,
        ]);
    }

    /**
     * AJAX endpoint: check whether a given full name (first + middle + last)
     * already exists. Returns { duplicate: bool, name: string }.
     */
    public function checkName(Request $request)
    {
        $first = trim((string) $request->input('first_name', ''));
        $middle = trim((string) $request->input('middle_name', ''));
        $last = trim((string) $request->input('last_name', ''));

        $fullName = trim($first . ' ' . $middle . ' ' . $last);
        $normalized = preg_replace('/\s+/', ' ', strtolower($fullName));

        $duplicate = false;
        if ($normalized !== '') {
            $duplicate = User::get()->contains(function ($existing) use ($normalized) {
                $existingFullName = preg_replace(
                    '/\s+/',
                    ' ',
                    strtolower(trim(($existing->first_name ?? '') . ' ' . ($existing->middle_name ?? '') . ' ' . ($existing->last_name ?? '')))
                );
                return $existingFullName !== '' && $existingFullName === $normalized;
            });
        }

        return response()->json([
            'duplicate' => $duplicate,
            'name' => $fullName,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'age' => ['nullable', 'integer', 'min:1', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:50', 'unique:users,contact_number'],
            'gender' => ['nullable', 'in:Male,Female,Other'],
            'name' => ['required', 'string', 'max:255', 'unique:users,name'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'name.required' => 'Username is required.',
            'name.unique' => 'This username is already taken.',
            'email.unique' => 'This email is already registered.',
            'contact_number.unique' => 'This contact number is already registered.',
            'password.confirmed' => 'Password and Confirm Password must match.',
        ]);

        // Combine name from first, middle, last
        $fullName = trim($data['first_name'] . ' ' . ($data['middle_name'] ?? '') . ' ' . $data['last_name']);

        // Block duplicate full name (case-insensitive, whitespace-normalized).
        // This is the authoritative server-side check — anything the client-side
        // duplicate-name AJAX misses is caught here before a user is created.
        $normalizedFullName = preg_replace('/\s+/', ' ', strtolower(trim($fullName)));
        $duplicate = User::get()->first(function ($existing) use ($normalizedFullName) {
            $existingFullName = preg_replace(
                '/\s+/',
                ' ',
                strtolower(trim(($existing->first_name ?? '') . ' ' . ($existing->middle_name ?? '') . ' ' . ($existing->last_name ?? '')))
            );
            return $existingFullName !== '' && $existingFullName === $normalizedFullName;
        });

        if ($duplicate) {
            return back()
                ->withInput()
                ->with('error', 'A user with the name "' . $fullName . '" already exists. Duplicate names are not allowed.');
        }

        User::create([
            'name' => $data['name'],
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'age' => $data['age'] ?? null,
            'address' => $data['address'] ?? null,
            'contact_number' => $data['contact_number'],
            'gender' => $data['gender'] ?? null,
            'email' => $data['email'],
            'role_id' => $data['role_id'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);

        return redirect()->route('admin.users.index')->with('status', 'User account created successfully.');
    }

    public function edit(User $user)
    {
        $roles = Role::orderBy('role_name')->get();

        return view('admin.users.edit', [
            'user' => $user->load('role'),
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'age' => ['nullable', 'integer', 'min:1', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:50', 'unique:users,contact_number,' . $user->id],
            'gender' => ['nullable', 'in:Male,Female,Other'],
            'name' => ['required', 'string', 'max:255', 'unique:users,name,' . $user->id],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        // Strip `password` before mass assignment. The ConvertEmptyStringsToNull
        // middleware turns an empty password field into null, which would
        // overwrite the user's hashed password and trip the NOT NULL constraint
        // at save time. We re-apply the new password below only when one was
        // actually submitted.
        $updateData = $data;
        unset($updateData['password']);
        $user->fill($updateData);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return redirect()->route('admin.users.index')->with('status', 'User account updated successfully.');
    }

    public function deactivate(User $user)
    {
        if ($user->isProtected()) {
            if ($this->isAjaxRequest()) {
                return response()->json(['error' => 'Cannot deactivate administrator account.'], 403);
            }
            return back()->with('error', 'Cannot deactivate administrator account.');
        }

        $user->update(['is_active' => false]);

        if ($this->isAjaxRequest()) {
            return response()->json(['status' => 'User account deactivated.']);
        }
        return back()->with('status', 'User account deactivated.');
    }

    public function activate(User $user)
    {
        $user->update(['is_active' => true]);

        if ($this->isAjaxRequest()) {
            return response()->json(['status' => 'User account activated.']);
        }
        return back()->with('status', 'User account activated.');
    }

    /**
     * Check if the request is an AJAX request
     * Works with both traditional X-Requested-With header and Accept header
     */
    private function isAjaxRequest(): bool
    {
        return request()->ajax() ||
               request()->expectsJson() ||
               (request()->header('Accept') && str_contains(request()->header('Accept'), 'application/json'));
    }

    public function destroy(User $user)
    {
        if ($user->isProtected()) {
            if ($this->isAjaxRequest()) {
                return response()->json(['error' => 'Cannot delete administrator account.'], 403);
            }
            return back()->with('error', 'Cannot delete administrator account.');
        }

        $staff = \App\Models\Staff::where('UserID', $user->id)->first();
        if ($staff && \App\Models\SalesTransaction::where('StaffID', $staff->StaffID)->exists()) {
            $message = 'Cannot delete this cashier — they have recorded sales. Deactivate the account instead.';
            if ($this->isAjaxRequest()) {
                return response()->json(['error' => $message], 409);
            }
            return back()->with('error', $message);
        }

        $user->delete();

        if ($this->isAjaxRequest()) {
            return response()->json(['status' => 'User deleted successfully.']);
        }
        return redirect()->route('admin.users.index')->with('status', 'User deleted successfully.');
    }

    public function show(User $user, Request $request)
    {
        $user->load('role');

        if ($request->expectsJson() || $request->ajax() || ($request->header('Accept') && str_contains($request->header('Accept'), 'application/json'))) {
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'middle_name' => $user->middle_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'contact_number' => $user->contact_number,
                    'age' => $user->age,
                    'gender' => $user->gender,
                    'address' => $user->address,
                    'role_name' => $user->role?->role_name,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
            ]);
        }

        return view('admin.users.show', compact('user'));
    }
}