<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'age',
        'birthdate',
        'address',
        'contact_number',
        'gender',
        'email',
        'password',
        'role_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
            'birthdate' => 'date',
        ];
    }

    /**
     * Age today. With a birthdate it's worked out fresh every time it's
     * read, so it goes up on its own each birthday (nothing has to run to
     * update it); users saved before birthdates existed keep the age typed
     * in back then.
     */
    protected function age(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => ! empty($attributes['birthdate'])
                ? (int) Carbon::parse($attributes['birthdate'])->age
                : ($value === null ? null : (int) $value),
        );
    }

    /**
     * Same as age; kept for the views that read current_age.
     */
    public function getCurrentAgeAttribute(): ?int
    {
        return $this->age;
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // All users with the admin role — the recipient list for system notifications.
    public static function admins()
    {
        return static::whereHas('role', function ($query) {
            $query->whereRaw('LOWER(role_name) = ?', ['admin']);
        })->get();
    }

    public function isAdmin(): bool
    {
        // Check if role is already loaded, if not try to load it
        if (! $this->relationLoaded('role')) {
            $this->load('role');
        }

        return $this->role && strtolower($this->role->role_name) === 'admin';
    }

    /**
     * The Administrator who can also add Administrators and activate or
     * deactivate them. Set only by the migration, never through a form.
     */
    public function isSuperAdmin(): bool
    {
        return $this->isAdmin() && (bool) $this->is_super_admin;
    }

    /**
     * "Super Admin", "Admin" or "Cashier", for badges.
     */
    public function getRoleLabelAttribute(): string
    {
        if ($this->isSuperAdmin()) {
            return 'Super Admin';
        }

        return $this->isAdmin() ? 'Admin' : ucfirst($this->role?->role_name ?? 'N/A');
    }

    /**
     * Whether this user may activate, deactivate or delete $target: the
     * Super Admin manages Administrator accounts; any Administrator manages
     * Cashier accounts; nobody manages the Super Admin account.
     */
    public function canManageAccountStatusOf(User $target): bool
    {
        if (! $this->isAdmin() || $target->isSuperAdmin()) {
            return false;
        }

        return $target->isAdmin() ? $this->isSuperAdmin() : true;
    }

    /**
     * Whether this user may edit $target's profile: Administrator accounts
     * other than your own are the Super Admin's to edit.
     */
    public function canEditAccountOf(User $target): bool
    {
        if (! $this->isAdmin()) {
            return false;
        }

        if ($target->isAdmin() && $target->id !== $this->id) {
            return $this->isSuperAdmin() && ! $target->isSuperAdmin();
        }

        return true;
    }

    public function isCashier(): bool
    {
        // Check if role is already loaded, if not try to load it
        if (! $this->relationLoaded('role')) {
            $this->load('role');
        }

        return $this->role && strtolower($this->role->role_name) === 'cashier';
    }

    // Protection now means "the last active Administrator account" rather
    // than "any Administrator account" — an admin should be able to edit
    // their own (or another admin's) profile freely, and a second admin
    // account should be manageable like any other, but the system must
    // never be left with zero active admins.
    public function isProtected(): bool
    {
        if (! $this->isAdmin()) {
            return false;
        }

        return static::whereHas('role', function ($query) {
            $query->whereRaw('LOWER(role_name) = ?', ['admin']);
        })
            ->where('is_active', true)
            ->count() <= 1;
    }

    /**
     * How long a session counts as "still open" after its browser last
     * checked in. Open pages check in every 30 seconds (session guard), so
     * a browser that was closed frees the account within this window.
     */
    public const SESSION_ALIVE_SECONDS = 120;

    /**
     * Records that the browser holding this session is still open. Kept in
     * the cache, separate from the session itself, so it never counts as
     * activity for the idle timeout.
     */
    public static function markSessionAlive(string $sessionId): void
    {
        Cache::put('session-alive:'.$sessionId, true, now()->addSeconds(self::SESSION_ALIVE_SECONDS));
    }

    // Whether `current_session_id` (if any) is still in use: its session row
    // hasn't aged past the session lifetime AND its browser is still open
    // (checked in within SESSION_ALIVE_SECONDS). The single source of truth
    // the login flow checks to enforce one active session per account; a
    // closed browser no longer blocks the next login until the session
    // times out. Deliberately NOT in $fillable: only ever set via direct
    // attribute assignment from the auth flow itself, never from
    // mass-assigned request input.
    public function hasActiveSession(): bool
    {
        if (! $this->current_session_id) {
            return false;
        }

        $session = DB::table('sessions')->where('id', $this->current_session_id)->first();

        if (! $session || $session->last_activity < (now()->timestamp - config('session.lifetime') * 60)) {
            return false;
        }

        return Cache::has('session-alive:'.$this->current_session_id);
    }

    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ]);

        return ! empty($parts) ? implode(' ', $parts) : $this->name;
    }

    public function getInitialsAttribute(): string
    {
        $first = $this->first_name ? substr($this->first_name, 0, 1) : '';
        $last = $this->last_name ? substr($this->last_name, 0, 1) : '';

        return strtoupper($first.$last);
    }
}
