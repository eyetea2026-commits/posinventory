<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'Supplier';
    protected $primaryKey = 'SupplierID';

    protected $fillable = [
        'SupplierName',
        'ContactNumber',
        'Email',
        'Address',
    ];

    public function stockReceivings()
    {
        return $this->hasMany(StockReceiving::class, 'SupplierID', 'SupplierID');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'SupplierID', 'SupplierID');
    }
}
