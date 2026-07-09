<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockReceiving extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'StockReceiving';
    protected $primaryKey = 'ReceivingID';

    protected $fillable = [
        'Quantity',
        'DateReceived',
        'ReceiptNumber',
        'ProductID',
        'SupplierID',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'SupplierID', 'SupplierID');
    }
}
