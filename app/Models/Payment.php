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
        'ReferenceNumber',
        'BankName',
        'AccountName',
        'PaymentDate',
        'PaymentTime',
        'Remarks',
        // Optional proof of payment photo (GCash / Bank Transfer / Cheque),
        // on the private disk - see App\Services\PaymentProof.
        'ProofImagePath',
    ];

    protected $casts = [
        'PaymentDate' => 'date',
    ];

    public function billing()
    {
        return $this->belongsTo(Billing::class, 'BillingID', 'BillingID');
    }
}
