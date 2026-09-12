<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use App\Models\Role;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'age',
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
        ];
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
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        return $this->role && strtolower($this->role->role_name) === 'admin';
    }

    public function isCashier(): bool
    {
        // Check if role is already loaded, if not try to load it
        if (!$this->relationLoaded('role')) {
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

    // Whether `current_session_id` (if any) still points at a session row
    // that hasn't aged past the app's own session lifetime -- the single
    // source of truth the login flow checks to enforce one active session
    // per account. Deliberately NOT in $fillable: only ever set via direct
    // attribute assignment from the auth flow itself, never from
    // mass-assigned request input.
    public function hasActiveSession(): bool
    {
        if (! $this->current_session_id) {
            return false;
        }

        $session = DB::table('sessions')->where('id', $this->current_session_id)->first();

        if (! $session) {
            return false;
        }

        return $session->last_activity >= (now()->timestamp - config('session.lifetime') * 60);
    }

    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name
        ]);
        return !empty($parts) ? implode(' ', $parts) : $this->name;
    }

    public function getInitialsAttribute(): string
    {
        $first = $this->first_name ? substr($this->first_name, 0, 1) : '';
        $last = $this->last_name ? substr($this->last_name, 0, 1) : '';
        return strtoupper($first . $last);
    }
}