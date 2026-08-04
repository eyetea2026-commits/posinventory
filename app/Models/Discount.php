<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Discount extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'Discount';
    protected $primaryKey = 'DiscountID';

    protected $fillable = [
        'ProductID',
        'DiscountRate',
        'Name',
        'PromoCode',
        'Description',
        'StartDate',
        'EndDate',
        'Status',
        'CreatedBy',
    ];

    protected $casts = [
        'DiscountRate' => 'decimal:2',
        'StartDate' => 'date',
        'EndDate' => 'date',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_EXPIRED = 'expired';

    const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_INACTIVE => 'Inactive',
        self::STATUS_EXPIRED => 'Expired',
    ];

    public function billings()
    {
        return $this->hasMany(Billing::class, 'DiscountID', 'DiscountID');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'CreatedBy', 'id');
    }

    // Status is never manually toggled — it's derived entirely from
    // StartDate/EndDate against today, computed at read time so it can't
    // silently drift out of sync with the calendar the way a stored value
    // would without a scheduler (this host doesn't reliably run one). A
    // promo whose EndDate has passed reads as Expired; one whose StartDate
    // hasn't arrived yet reads as Inactive; otherwise it's Active. The
    // stored Status column is effectively vestigial (always 'active' from
    // creation) and kept only so existing rows/migrations don't need a
    // schema change.
    public function getEffectiveStatusAttribute(): string
    {
        $today = now()->startOfDay();

        if ($this->EndDate && Carbon::parse($this->EndDate)->lt($today)) {
            return self::STATUS_EXPIRED;
        }

        if ($this->StartDate && Carbon::parse($this->StartDate)->gt($today)) {
            return self::STATUS_INACTIVE;
        }

        return self::STATUS_ACTIVE;
    }

    public function getEffectiveStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->effective_status] ?? ucfirst($this->effective_status);
    }

    public function getDiscountedPriceAttribute(): ?float
    {
        if (! $this->product) {
            return null;
        }

        return round(((float) $this->product->Price) * (1 - ((float) $this->DiscountRate / 100)), 2);
    }

    // Null once expired/no end date — a "days remaining" that ever goes
    // negative isn't meaningful to show.
    public function getRemainingDaysAttribute(): ?int
    {
        if (! $this->EndDate) {
            return null;
        }

        $days = now()->startOfDay()->diffInDays(Carbon::parse($this->EndDate)->startOfDay(), false);

        return $days >= 0 ? (int) $days : null;
    }

    // "Currently applicable" — the set a promo code lookup at POS checkout
    // (and the admin list's default view) should consider: tied to a real
    // product and within its date window (a window that simply wasn't set
    // is treated as always-open). Purely date-driven, matching
    // getEffectiveStatusAttribute() — there's no separate admin-set status
    // to gate on anymore.
    public function scopeCurrentlyActive($query)
    {
        $today = now()->toDateString();

        return $query->whereNotNull('ProductID')
            ->where(function ($q) use ($today) {
                $q->whereNull('StartDate')->orWhereDate('StartDate', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('EndDate')->orWhereDate('EndDate', '>=', $today);
            });
    }
}
