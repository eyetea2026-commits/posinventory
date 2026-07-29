<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserAccountCreated;
use App\Notifications\UserAccountDeactivated;
use App\Notifications\UserAccountUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Throwable;

class UserController extends Controller
{
    public function __construct()
    {
        // Route-level role:admin middleware already gates every route here —
        // this constructor-level check is defense-in-depth, matching the
        // same double-gate pattern every other admin controller uses, so a
        // future route added without the right middleware doesn't go
        // unprotected specifically in the one controller that manages
        // accounts and roles.
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
        $excludeId = $request->input('exclude_id');

        $fullName = trim($first . ' ' . $middle . ' ' . $last);
        $normalized = preg_replace('/\s+/', ' ', strtolower($fullName));

        $duplicate = false;
        if ($normalized !== '') {
            $duplicate = User::when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->get()
                ->contains(function ($existing) use ($normalized) {
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
        // This endpoint only ever creates Cashier accounts (the create() form
        // only offers that option) — validated here server-side too, so a
        // crafted request with a different role_id can't create an
        // unauthorized Administrator account. Never rely on the dropdown
        // alone to enforce this.
        $cashierRoleId = Role::where('role_name', 'cashier')->value('id');

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'age' => ['nullable', 'integer', 'min:1', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:50', 'unique:users,contact_number'],
            'gender' => ['nullable', 'in:Male,Female,Other'],
            'name' => ['required', 'string', 'max:255', 'unique:users,name'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['required', 'integer', Rule::in(array_filter([$cashierRoleId]))],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'role_id.in' => 'Only Cashier accounts can be created here.',
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

        $newUser = User::create([
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

        ActivityLog::record('user.created', "Created user \"{$newUser->name}\" (Cashier)");

        try {
            Notification::send(User::admins(), new UserAccountCreated($newUser));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch UserAccountCreated notification', [
                'user_id' => $newUser->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return redirect()->route('admin.users.index')->with('status', 'User account created successfully.');
    }

    public function edit(Request $request, User $user)
    {
        $roles = Role::orderBy('role_name')->get();
        $user->load('role');

        // Edit User modal: return just the rendered form fields instead of a
        // full page, so the modal can inject it without navigating.
        if ($this->isAjaxRequest()) {
            return response()->json([
                'html' => view('admin.users.partials.user-form-fields', [
                    'roles' => $roles,
                    'user' => $user,
                ])->render(),
            ]);
        }

        return view('admin.users.edit', [
            'user' => $user,
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
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        // Never rely on the Edit form disabling the Role field client-side —
        // if this is the last active Administrator, reject any attempt to
        // change their role away from admin server-side too, since that
        // would leave the system with zero admins.
        if ($user->isProtected() && (int) $data['role_id'] !== (int) $user->role_id) {
            $message = 'This is the last active Administrator account — its role cannot be changed.';
            if ($this->isAjaxRequest()) {
                return response()->json(['error' => $message], 422);
            }
            return back()->withInput()->with('error', $message);
        }

        $roleChanged = (int) $data['role_id'] !== (int) $user->role_id;
        $passwordChanged = ! empty($data['password']);
        $oldRoleName = $user->role?->role_name;

        // Strip `password` before mass assignment. The ConvertEmptyStringsToNull
        // middleware turns an empty password field into null, which would
        // overwrite the user's hashed password and trip the NOT NULL constraint
        // at save time. We re-apply the new password below only when one was
        // actually submitted.
        $updateData = $data;
        unset($updateData['password']);
        $user->fill($updateData);

        if ($passwordChanged) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        ActivityLog::record('user.updated', "Updated user \"{$user->name}\"" . ($passwordChanged ? ' (password changed)' : ''));
        if ($roleChanged) {
            $newRoleName = Role::find($data['role_id'])?->role_name;
            ActivityLog::record('user.role_changed', "Changed \"{$user->name}\"'s role from \"{$oldRoleName}\" to \"{$newRoleName}\"");
        }

        try {
            Notification::send(User::admins(), new UserAccountUpdated($user));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch UserAccountUpdated notification', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return redirect()->route('admin.users.index')->with('status', 'User account updated successfully.');
    }

    public function deactivate(User $user)
    {
        if ($user->isProtected()) {
            $message = 'Cannot deactivate the last active Administrator account.';
            if ($this->isAjaxRequest()) {
                return response()->json(['error' => $message], 403);
            }
            return back()->with('error', $message);
        }

        $user->update(['is_active' => false]);
        ActivityLog::record('user.deactivated', "Deactivated user \"{$user->name}\"");

        try {
            Notification::send(User::admins(), new UserAccountDeactivated($user));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch UserAccountDeactivated notification', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);
        }

        if ($this->isAjaxRequest()) {
            return response()->json(['status' => 'User account deactivated.']);
        }
        return back()->with('status', 'User account deactivated.');
    }

    public function activate(User $user)
    {
        $user->update(['is_active' => true]);
        ActivityLog::record('user.activated', "Activated user \"{$user->name}\"");

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
            $message = 'Cannot delete the last active Administrator account.';
            if ($this->isAjaxRequest()) {
                return response()->json(['error' => $message], 403);
            }
            return back()->with('error', $message);
        }

        $staff = \App\Models\Staff::where('UserID', $user->id)->first();
        if ($staff && \App\Models\SalesTransaction::where('StaffID', $staff->StaffID)->exists()) {
            $message = 'Cannot delete this cashier — they have recorded sales. Deactivate the account instead.';
            if ($this->isAjaxRequest()) {
                return response()->json(['error' => $message], 409);
            }
            return back()->with('error', $message);
        }

        $deletedName = $user->name;
        $user->delete();
        ActivityLog::record('user.deleted', "Deleted user \"{$deletedName}\"");

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