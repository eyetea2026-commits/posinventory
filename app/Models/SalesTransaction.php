<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesTransaction extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'SalesTransaction';

    protected $primaryKey = 'SalesTransactionID';

    // The sale and what it came to: the subtotal, promo and custom
    // discounts, VAT and the total due (the customer pays at once; how is
    // in Payment).
    protected $fillable = [
        'CustomerName',
        'SalesTransactionDate',
        'StaffID',
        'Subtotal',
        'DiscountAmount',
        'CustomDiscountPercent',
        'CustomDiscountAmount',
        'CustomDiscountApprovedBy',
        'PromoCode',
        'VatApplied',
        'VatAmount',
        'TotalAmount',
        'DiscountID',
    ];

    protected $casts = [
        'SalesTransactionDate' => 'datetime',
    ];

    /** Philippine VAT rate. */
    public const VAT_RATE = 0.12;

    /**
     * The VAT inside a VAT-inclusive amount (POS prices already include
     * VAT): Total × 12/112, so the VAT-exclusive part (Total ÷ 1.12) plus
     * 12% of it adds back up to the Total. ₱213.00 → ₱22.82.
     */
    public static function vatIncludedIn(float $total): float
    {
        return round($total * self::VAT_RATE / (1 + self::VAT_RATE), 2);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'StaffID', 'StaffID');
    }

    public function items()
    {
        return $this->hasMany(SalesItem::class, 'SalesTransactionID', 'SalesTransactionID');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class, 'SalesTransactionID', 'SalesTransactionID');
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class, 'DiscountID', 'DiscountID');
    }

    /**
     * The Administrator whose password approved the custom discount.
     */
    public function customDiscountApprover()
    {
        return $this->belongsTo(User::class, 'CustomDiscountApprovedBy', 'id');
    }
}
