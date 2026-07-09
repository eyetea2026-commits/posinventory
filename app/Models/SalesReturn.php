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
        'Status',
        'ReturnDate',
        'ApprovedBy',
        'RefundMethod',
        'RefundAmount',
        'RefundAccountNumber',
        'RefundDate',
        'StaffID',
        'CustomerName',
    ];

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
}
