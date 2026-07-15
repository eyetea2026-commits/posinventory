<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'SalesReturn';
    protected $primaryKey = 'SalesReturnID';

    protected $fillable = [
        'SalesTransactionID',
        'ProductID',
        'Quantity',
        'Reason',
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
    const TYPE_REPLACEMENT = 'replacement';

    const REASON_CODES = [
        'factory_defect' => 'Factory Defect',
        'damaged_product' => 'Damaged Product',
        'wrong_item' => 'Wrong Item',
        'expired_product' => 'Expired Product',
        'other' => 'Other',
    ];

    // Store policy: how many days after purchase a product remains eligible for return.
    const RETURN_WINDOW_DAYS = 7;

    public function transaction()
    {
        return $this->belongsTo(SalesTransaction::class, 'SalesTransactionID', 'SalesTransactionID');
    }

    // Alias for transaction relationship
    public function salesTransaction()
    {
        return $this->belongsTo(SalesTransaction::class, 'SalesTransactionID', 'SalesTransactionID');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
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

    public function replacement()
    {
        return $this->hasOne(Replacement::class, 'SalesReturnID', 'SalesReturnID');
    }

    // How many days elapsed between the original purchase and this return request.
    public function getDaysSincePurchaseAttribute(): ?int
    {
        $transaction = $this->relationLoaded('transaction') ? $this->transaction : $this->transaction()->first();

        if (! $transaction || ! $this->ReturnDate) {
            return null;
        }

        $purchaseDate = \Carbon\Carbon::parse($transaction->SalesTransactionDate)->startOfDay();
        $requestDate = \Carbon\Carbon::parse($this->ReturnDate)->startOfDay();

        return $purchaseDate->diffInDays($requestDate);
    }

    // Whether this request was made within the store's return policy window.
    public function getIsWithinReturnWindowAttribute(): ?bool
    {
        $days = $this->days_since_purchase;

        return $days === null ? null : $days <= self::RETURN_WINDOW_DAYS;
    }
}
