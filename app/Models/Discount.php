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
        self::STATUS_INACTIVE => 'Scheduled',
        self::STATUS_EXPIRED => 'Expired',
    ];

    public function salesTransactions()
    {
        return $this->hasMany(SalesTransaction::class, 'DiscountID', 'DiscountID');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'DiscountProduct', 'DiscountID', 'ProductID')
            ->withTimestamps();
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'CreatedBy', 'id');
    }

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

    public function discountedPriceFor(Product $product): float
    {
        return $this->discountedPrice((float) $product->Price);
    }

    public function discountedPrice(float $price): float
    {
        if ($this->DiscountType === self::TYPE_FIXED) {
            return max(0, round($price - (float) $this->DiscountRate, 2));
        }

        return round($price * (1 - ((float) $this->DiscountRate / 100)), 2);
    }

    public function getRemainingDaysAttribute(): ?int
    {
        if (! $this->EndDate) {
            return null;
        }

        $days = now()->startOfDay()->diffInDays(Carbon::parse($this->EndDate)->startOfDay(), false);

        return $days >= 0 ? (int) $days : null;
    }

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

    public function scopeNotExpired($query)
    {
        $today = now()->toDateString();

        return $query->where(function ($q) use ($today) {
            $q->whereNull('EndDate')->orWhereDate('EndDate', '>=', $today);
        });
    }
}
