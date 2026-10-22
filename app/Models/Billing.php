<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Billing extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'Billing';

    protected $primaryKey = 'BillingID';

    protected $fillable = [
        'CustomerName',
        'VatApplied',
        'BillingAmount',
        'Subtotal',
        'DiscountAmount',
        'CustomDiscountPercent',
        'CustomDiscountAmount',
        'CustomDiscountApprovedBy',
        'PromoCode',
        'VatAmount',
        'BillingDate',
        'DiscountID',
        'SalesTransactionID',
    ];

    /**
     * The Administrator whose password approved the custom discount.
     */
    public function customDiscountApprover()
    {
        return $this->belongsTo(User::class, 'CustomDiscountApprovedBy', 'id');
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class, 'DiscountID', 'DiscountID');
    }

    public function transaction()
    {
        return $this->belongsTo(SalesTransaction::class, 'SalesTransactionID', 'SalesTransactionID');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class, 'BillingID', 'BillingID');
    }
}
