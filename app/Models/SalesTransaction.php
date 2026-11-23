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

    public const VAT_RATE = 0.12;

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

    public function customDiscountApprover()
    {
        return $this->belongsTo(User::class, 'CustomDiscountApprovedBy', 'id');
    }
}
