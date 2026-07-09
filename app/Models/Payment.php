<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'Payment';
    protected $primaryKey = 'PaymentID';

    protected $fillable = [
        'PaymentAmount',
        'PaymentMethod',
        'ReceiptNumber',
        'BillingID',
    ];

    public function billing()
    {
        return $this->belongsTo(Billing::class, 'BillingID', 'BillingID');
    }
}
