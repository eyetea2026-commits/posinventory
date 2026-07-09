<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'SalesItem';
    protected $primaryKey = 'SalesItemID';

    protected $fillable = [
        'Quantity',
        'UnitPrice',
        'ProductID',
        'SalesTransactionID',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    public function transaction()
    {
        return $this->belongsTo(SalesTransaction::class, 'SalesTransactionID', 'SalesTransactionID');
    }
}
