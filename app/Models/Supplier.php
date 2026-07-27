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
        'ContactPerson',
        'ContactNumber',
        'Email',
        'Address',
        'Status',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    public function stockReceivings()
    {
        return $this->hasMany(StockReceiving::class, 'SupplierID', 'SupplierID');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'SupplierID', 'SupplierID');
    }

    public function damagedProducts()
    {
        return $this->hasMany(DamagedProduct::class, 'SupplierID', 'SupplierID');
    }

    public function productSuppliers()
    {
        return $this->hasMany(ProductSupplier::class, 'SupplierID', 'SupplierID');
    }
}
