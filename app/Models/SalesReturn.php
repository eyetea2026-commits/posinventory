<?php

namespace App\Models;

use App\Casts\Quantity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    use HasFactory;

    protected $table = 'SalesReturn';

    protected $primaryKey = 'SalesReturnID';

    protected $fillable = [
        'SalesTransactionID',
        'Remarks',
        'ReturnType',
        'Status',
        'DeclineReason',
        'ReturnDate',
        'ApprovedBy',
        'ProcessedBy',
        'RefundMethod',
        'RefundAmount',
        'RefundAccountNumber',
        'RefundDate',
        'StaffID',
        'CustomerName',
    ];

    const STATUS_PENDING = 'pending';

    const STATUS_APPROVED = 'approved';

    const STATUS_DECLINED = 'declined';

    const STATUS_PROCESSED = 'processed';

    const TYPE_REFUND = 'refund';

    const REASON_CODES = [
        'factory_defect' => 'Factory Defect',
    ];

    const DEFAULT_REASON_CODE = 'factory_defect';

    const UNSALABLE_REASONS = ['Factory Defect', 'Damaged Product'];

    const RETURN_WINDOW_DAYS = 7;

    public static function statusTone(?string $status): string
    {
        return match ($status) {
            self::STATUS_APPROVED, self::STATUS_PROCESSED => 'success',
            self::STATUS_DECLINED => 'danger',
            default => 'warning',
        };
    }

    public function transaction()
    {
        return $this->belongsTo(SalesTransaction::class, 'SalesTransactionID', 'SalesTransactionID');
    }

    public function salesTransaction()
    {
        return $this->belongsTo(SalesTransaction::class, 'SalesTransactionID', 'SalesTransactionID');
    }

    public function items()
    {
        return $this->hasMany(SalesReturnItem::class, 'SalesReturnID', 'SalesReturnID');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'StaffID', 'StaffID');
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'ApprovedBy', 'id');
    }

    public function processedByUser()
    {
        return $this->belongsTo(User::class, 'ProcessedBy', 'id');
    }

    public function damagedProduct()
    {
        return $this->hasOne(DamagedProduct::class, 'SalesReturnID', 'SalesReturnID');
    }

    public function getIsUnsalableReturnAttribute(): bool
    {
        return in_array($this->Reason, self::UNSALABLE_REASONS, true);
    }

    public function hasUnsalableItems(): bool
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return $items->contains(fn (SalesReturnItem $item) => $item->is_unsalable);
    }

    public function getTotalQuantityAttribute(): int|float
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return Quantity::normalize($items->sum('Quantity'));
    }

    public function getDaysSincePurchaseAttribute(): ?int
    {
        $transaction = $this->relationLoaded('transaction') ? $this->transaction : $this->transaction()->first();

        if (! $transaction || ! $this->ReturnDate) {
            return null;
        }

        $purchaseDate = Carbon::parse($transaction->SalesTransactionDate)->startOfDay();
        $requestDate = Carbon::parse($this->ReturnDate)->startOfDay();

        return $purchaseDate->diffInDays($requestDate);
    }

    public function getIsWithinReturnWindowAttribute(): ?bool
    {
        $days = $this->days_since_purchase;

        return $days === null ? null : $days <= self::RETURN_WINDOW_DAYS;
    }
}
