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
            'birthdate' => 'date',
        ];
    }

    protected function age(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => ! empty($attributes['birthdate'])
                ? (int) Carbon::parse($attributes['birthdate'])->age
                : ($value === null ? null : (int) $value),
        );
    }

    public function getCurrentAgeAttribute(): ?int
    {
        return $this->age;
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public static function admins()
    {
        return static::whereHas('role', function ($query) {
            $query->whereRaw('LOWER(role_name) = ?', ['admin']);
        })->get();
    }

    public function isAdmin(): bool
    {
        if (! $this->relationLoaded('role')) {
            $this->load('role');
        }

        return $this->role && strtolower($this->role->role_name) === 'admin';
    }

    public function getRoleLabelAttribute(): string
    {
        return $this->isAdmin() ? 'Admin' : ucfirst($this->role?->role_name ?? 'N/A');
    }

    public function canManageAccountStatusOf(User $target): bool
    {
        return $this->isAdmin() && ! $target->is($this);
    }

    public function canEditAccountOf(User $target): bool
    {
        return $this->isAdmin();
    }

    public function isCashier(): bool
    {
        if (! $this->relationLoaded('role')) {
            $this->load('role');
        }

        return $this->role && strtolower($this->role->role_name) === 'cashier';
    }

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

    public const SESSION_ALIVE_SECONDS = 120;

    public static function markSessionAlive(string $sessionId): void
    {
        Cache::put('session-alive:'.$sessionId, true, now()->addSeconds(self::SESSION_ALIVE_SECONDS));
    }

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

    public function getNameWithMiddleInitialAttribute(): string
    {
        $middle = trim((string) $this->middle_name);
        $parts = array_filter([
            $this->first_name,
            $middle !== '' ? mb_strtoupper(mb_substr($middle, 0, 1)).'.' : null,
            $this->last_name,
        ]);

        return ! empty($parts) ? implode(' ', $parts) : $this->name;
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
