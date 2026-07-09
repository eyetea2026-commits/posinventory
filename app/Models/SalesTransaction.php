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
    ];

    protected $casts = [
        'SalesTransactionDate' => 'datetime',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'StaffID', 'StaffID');
    }

    public function items()
    {
        return $this->hasMany(SalesItem::class, 'SalesTransactionID', 'SalesTransactionID');
    }

    public function billing()
    {
        return $this->hasOne(Billing::class, 'SalesTransactionID', 'SalesTransactionID');
    }
}
