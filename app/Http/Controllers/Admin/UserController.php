<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\UserAccountCreated;
use App\Notifications\UserAccountDeactivated;
use App\Notifications\UserAccountUpdated;
use App\Services\ActionNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserController extends Controller
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
        $adminRoleId = Role::where('role_name', 'admin')->value('id');

        $users = User::with('role')
            ->when($search, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('is_super_admin')
            ->orderByRaw('CASE WHEN role_id = ? THEN 0 ELSE 1 END', [$adminRoleId ?? 0])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'assignableRoles' => $this->assignableRoles(),
        ]);
    }

    public function create()
    {
        $roles = $this->assignableRoles();

        return view('admin.users.create', [
            'roles' => $roles,
        ]);
    }

    public function checkName(Request $request)
    {
        $first = trim((string) $request->input('first_name', ''));
        $middle = trim((string) $request->input('middle_name', ''));
        $last = trim((string) $request->input('last_name', ''));
        $excludeId = $request->input('exclude_id');

        $fullName = trim($first.' '.$middle.' '.$last);
        $normalized = preg_replace('/\s+/', ' ', strtolower($fullName));

        $duplicate = false;
        if ($normalized !== '') {
            $duplicate = User::when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->get()
                ->contains(function ($existing) use ($normalized) {
                    $existingFullName = preg_replace(
                        '/\s+/',
                        ' ',
                        strtolower(trim(($existing->first_name ?? '').' '.($existing->middle_name ?? '').' '.($existing->last_name ?? '')))
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
        $assignableRoleIds = $this->assignableRoles()->pluck('id')->all();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birthdate' => self::birthdateRules('required'),
            'address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['required', 'digits:11', 'unique:users,contact_number'],
            'gender' => ['nullable', 'in:Male,Female,Prefer not to say'],
            'name' => ['required', 'string', 'max:255', 'unique:users,name'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['required', 'integer', Rule::in($assignableRoleIds)],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'role_id.in' => auth()->user()->isSuperAdmin() ? 'Select a valid role.' : 'Only the Super Admin can add Administrator accounts.',
            'name.required' => 'Username is required.',
            'name.unique' => 'This username is already taken.',
            'email.unique' => 'This email is already registered.',
            'contact_number.digits' => 'Contact number must be exactly 11 digits.',
            'contact_number.unique' => 'This contact number is already registered.',
            ...self::birthdateMessages(),
            'password.confirmed' => 'Password and Confirm Password must match.',
        ]);

        $fullName = trim($data['first_name'].' '.($data['middle_name'] ?? '').' '.$data['last_name']);

        $normalizedFullName = preg_replace('/\s+/', ' ', strtolower(trim($fullName)));
        $duplicate = User::get()->first(function ($existing) use ($normalizedFullName) {
            $existingFullName = preg_replace(
                '/\s+/',
                ' ',
                strtolower(trim(($existing->first_name ?? '').' '.($existing->middle_name ?? '').' '.($existing->last_name ?? '')))
            );

            return $existingFullName !== '' && $existingFullName === $normalizedFullName;
        });

        if ($duplicate) {
            return back()
                ->withInput()
                ->with('error', 'A user with the name "'.$fullName.'" already exists. Duplicate names are not allowed.');
        }

        $newUser = User::create([
            'name' => $data['name'],
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'birthdate' => $data['birthdate'],
            'age' => Carbon::parse($data['birthdate'])->age,
            'address' => $data['address'] ?? null,
            'contact_number' => $data['contact_number'],
            'gender' => $data['gender'] ?? null,
            'email' => $data['email'] ?? null,
            'role_id' => $data['role_id'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);

        ActivityLog::record('user.created', "Created user \"{$newUser->name}\" ({$newUser->role_label})");

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
        if (! auth()->user()->canEditAccountOf($user)) {
            return $this->denyAccountAction('Only the Super Admin can edit Administrator accounts.');
        }

        $user->load('role');
        $roles = $this->assignableRoles()->push($user->role)->filter()->unique('id')->sortBy('role_name')->values();

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
        if (! auth()->user()->canEditAccountOf($user)) {
            return $this->denyAccountAction('Only the Super Admin can edit Administrator accounts.');
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birthdate' => self::birthdateRules('nullable'),
            'address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['required', 'digits:11', 'unique:users,contact_number,'.$user->id],
            'gender' => ['nullable', 'in:Male,Female,Prefer not to say'],
            'name' => ['required', 'string', 'max:255', 'unique:users,name,'.$user->id],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ], [
            'contact_number.digits' => 'Contact number must be exactly 11 digits.',
            ...self::birthdateMessages(),
        ]);

        if ($user->isAdmin()) {
            $request->validate(['current_password' => ['required', 'string']]);

            if (! Hash::check($request->input('current_password'), auth()->user()->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'Your password is incorrect.',
                ]);
            }
        }

        $roleChanged = (int) $data['role_id'] !== (int) $user->role_id;

        if ($roleChanged) {
            $adminRoleId = (int) Role::whereRaw('LOWER(role_name) = ?', ['admin'])->value('id');

            if ($user->isSuperAdmin()) {
                throw ValidationException::withMessages(['role_id' => 'The Super Admin account must stay an Administrator.']);
            }

            if ($user->is(auth()->user())) {
                throw ValidationException::withMessages(['role_id' => 'You can\'t change your own role.']);
            }

            if (($user->isAdmin() || (int) $data['role_id'] === $adminRoleId) && ! auth()->user()->isSuperAdmin()) {
                throw ValidationException::withMessages(['role_id' => 'Only the Super Admin can give or remove the Administrator role.']);
            }
        }
        $passwordChanged = ! empty($data['password']);
        $oldRoleName = $user->role?->role_name;

        $updateData = $data;
        unset($updateData['password']);
        if (! empty($data['birthdate'])) {
            $updateData['age'] = Carbon::parse($data['birthdate'])->age;
        } else {
            unset($updateData['birthdate']);
        }

        $performUpdate = function (User $target) use ($updateData, $passwordChanged, $data) {
            $target->fill($updateData);
            if ($passwordChanged) {
                $target->password = Hash::make($data['password']);
            }
            $target->save();
        };

        if ($roleChanged) {
            $blocked = $this->withLastAdminGuard(
                $user,
                'This is the last active Administrator account — its role cannot be changed.',
                function (User $lockedUser) use ($performUpdate) {
                    $performUpdate($lockedUser);

                    return null;
                },
                422,
                true
            );

            if ($blocked !== null) {
                return $blocked;
            }

            $user->refresh();
        } else {
            $performUpdate($user);
        }

        ActivityLog::record('user.updated', "Updated user \"{$user->name}\"".($passwordChanged ? ' (password changed)' : ''));
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

    public function verifyPassword(Request $request)
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($data['password'], auth()->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'Your password is incorrect.',
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function resetPassword(Request $request, User $user)
    {
        if ($user->isAdmin()) {
            abort(403);
        }

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($data['current_password'], auth()->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Your password is incorrect.',
            ]);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        ActivityLog::record('user.password_reset', "Reset password for \"{$user->name}\"");

        try {
            Notification::send(User::admins(), new UserAccountUpdated($user));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch UserAccountUpdated notification', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Password reset successfully.']);
    }

    public function deactivate(User $user)
    {
        if (! auth()->user()->canManageAccountStatusOf($user)) {
            return $this->denyAccountAction($this->accountStatusDeniedMessage($user, 'deactivate'));
        }

        $blocked = $this->withLastAdminGuard(
            $user,
            'Cannot deactivate the last active Administrator account.',
            function (User $lockedUser) {
                $lockedUser->update(['is_active' => false]);

                return null;
            }
        );

        if ($blocked !== null) {
            return $blocked;
        }

        $user->refresh();
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
        if (! auth()->user()->canManageAccountStatusOf($user)) {
            return $this->denyAccountAction($this->accountStatusDeniedMessage($user, 'activate'));
        }

        $user->update(['is_active' => true]);
        ActivityLog::record('user.activated', "Activated user \"{$user->name}\"");
        ActionNotifier::toAdmins('User Account Activated', "The account \"{$user->name}\" has been activated.", route('admin.users.index'), 'user-plus', 'success');

        if ($this->isAjaxRequest()) {
            return response()->json(['status' => 'User account activated.']);
        }

        return back()->with('status', 'User account activated.');
    }

    private static function birthdateRules(string $presence): array
    {
        return [
            $presence,
            'date',
            'before_or_equal:'.today()->subYears(18)->toDateString(),
            'after:'.today()->subYears(120)->toDateString(),
        ];
    }

    private static function birthdateMessages(): array
    {
        return [
            'birthdate.required' => 'Birthdate is required.',
            'birthdate.date' => 'Enter a valid birthdate.',
            'birthdate.before_or_equal' => 'User must be at least 18 years old.',
            'birthdate.after' => 'Enter a valid birthdate.',
        ];
    }

    private function withLastAdminGuard(User $user, string $blockedMessage, \Closure $callback, int $blockedStatus = 403, bool $withInput = false)
    {
        return DB::transaction(function () use ($user, $blockedMessage, $callback, $blockedStatus, $withInput) {
            User::whereHas('role', function ($query) {
                $query->whereRaw('LOWER(role_name) = ?', ['admin']);
            })->where('is_active', true)->lockForUpdate()->get();

            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();

            if ($lockedUser->isProtected()) {
                if ($this->isAjaxRequest()) {
                    return response()->json(['error' => $blockedMessage], $blockedStatus);
                }

                $redirect = back();
                if ($withInput) {
                    $redirect = $redirect->withInput();
                }

                return $redirect->with('error', $blockedMessage);
            }

            return $callback($lockedUser);
        });
    }

    private function assignableRoles()
    {
        $roleNames = auth()->user()->isSuperAdmin() ? ['admin', 'cashier'] : ['cashier'];

        return Role::whereIn('role_name', $roleNames)->orderBy('role_name')->get();
    }

    private function accountStatusDeniedMessage(User $user, string $action): string
    {
        if ($user->isSuperAdmin()) {
            return "The Super Admin account cannot be {$action}d.";
        }

        return "Only the Super Admin can {$action} Administrator accounts.";
    }

    private function denyAccountAction(string $message)
    {
        if ($this->isAjaxRequest()) {
            return response()->json(['error' => $message], 403);
        }

        return back()->with('error', $message);
    }

    private function isAjaxRequest(): bool
    {
        return request()->ajax() ||
               request()->expectsJson() ||
               (request()->header('Accept') && str_contains(request()->header('Accept'), 'application/json'));
    }

    public function destroy(User $user)
    {
        if (! auth()->user()->canManageAccountStatusOf($user)) {
            return $this->denyAccountAction($this->accountStatusDeniedMessage($user, 'delete'));
        }

        $staff = Staff::where('UserID', $user->id)->first();
        if ($staff && SalesTransaction::where('StaffID', $staff->StaffID)->exists()) {
            $message = 'Cannot delete this cashier — they have recorded sales. Deactivate the account instead.';
            if ($this->isAjaxRequest()) {
                return response()->json(['error' => $message], 409);
            }

            return back()->with('error', $message);
        }

        $deletedName = $user->name;

        $blocked = $this->withLastAdminGuard(
            $user,
            'Cannot delete the last active Administrator account.',
            function (User $lockedUser) {
                $lockedUser->delete();

                return null;
            }
        );

        if ($blocked !== null) {
            return $blocked;
        }

        ActivityLog::record('user.deleted', "Deleted user \"{$deletedName}\"");
        ActionNotifier::toAdmins('User Account Deleted', "The account \"{$deletedName}\" has been deleted.", route('admin.users.index'), 'user-x', 'danger');

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
                    'age' => $user->current_age,
                    'birthdate' => $user->birthdate?->format('m/d/Y'),
                    'gender' => $user->gender,
                    'address' => $user->address,
                    'role_name' => $user->role?->role_name,
                    'role_label' => $user->role_label,
                    'is_super_admin' => $user->isSuperAdmin(),
                    'can_edit' => auth()->user()->canEditAccountOf($user),
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
