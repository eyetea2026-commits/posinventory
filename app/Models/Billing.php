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
        'BillingDate',
        'DiscountID',
        'SalesTransactionID',
    ];

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
