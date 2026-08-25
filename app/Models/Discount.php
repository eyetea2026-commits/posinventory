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
        'DiscountType',
        'Name',
        'PromoCode',
        'Description',
        'StartDate',
        'EndDate',
        'Status',
        'CreatedBy',
    ];

    const TYPE_PERCENTAGE = 'percentage';
    const TYPE_FIXED = 'fixed';

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

    // Legacy singular relation — still resolves correctly for pre-pivot
    // rows (ProductID still populated) but is null for every promo created
    // after the multi-product redesign. Kept for old code paths/history
    // display of legacy rows; new code should use products() instead.
    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    // The set of products this promo is actually assigned to — decided in
    // a separate "Apply" step after creation, not at creation time. A promo
    // can be assigned to zero (not yet applied to anything), one, or many
    // products; scopeCurrentlyActive() below only considers it "applicable"
    // once it has at least one.
    public function products()
    {
        return $this->belongsToMany(Product::class, 'DiscountProduct', 'DiscountID', 'ProductID')
            ->withTimestamps();
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

    // Back-compat for legacy single-product rows/views (ProductID still
    // populated). Always null for a post-redesign multi-product promo —
    // use discountedPriceFor() against one of products() instead.
    public function getDiscountedPriceAttribute(): ?float
    {
        if (! $this->product) {
            return null;
        }

        return $this->discountedPriceFor($this->product);
    }

    // Only 'percentage' math is wired up — DiscountType exists so 'fixed'
    // can be added later without another migration, but isn't computed yet.
    public function discountedPriceFor(Product $product): float
    {
        $price = (float) $product->Price;

        if ($this->DiscountType === self::TYPE_FIXED) {
            return max(0, round($price - (float) $this->DiscountRate, 2));
        }

        return round($price * (1 - ((float) $this->DiscountRate / 100)), 2);
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
    // (and the admin list's default view) should consider: assigned to at
    // least one real product (via the pivot — covers both legacy
    // single-ProductID rows, backfilled into the pivot by the migration,
    // and new multi-product assignments) and within its date window (a
    // window that simply wasn't set is treated as always-open). Purely
    // date-driven, matching getEffectiveStatusAttribute() — there's no
    // separate admin-set status to gate on anymore.
    public function scopeCurrentlyActive($query)
    {
        $today = now()->toDateString();

        return $query->whereHas('products')
            ->where(function ($q) use ($today) {
                $q->whereNull('StartDate')->orWhereDate('StartDate', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('EndDate')->orWhereDate('EndDate', '>=', $today);
            });
    }

    // Just the "hasn't expired yet" half of scopeCurrentlyActive() — without
    // the "has at least one product assigned" requirement, so it also keeps
    // a brand-new promo (created but not yet applied to anything) visible.
    // Used to hide expired promos from the admin's active Promo Discount
    // list and "Choose Promo Discount" picker, which scopeCurrentlyActive()
    // can't be reused for since it would also hide unassigned ones.
    public function scopeNotExpired($query)
    {
        $today = now()->toDateString();

        return $query->where(function ($q) use ($today) {
            $q->whereNull('EndDate')->orWhereDate('EndDate', '>=', $today);
        });
    }
}
