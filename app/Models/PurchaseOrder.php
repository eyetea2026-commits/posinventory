<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'PurchaseOrder';
    protected $primaryKey = 'PurchaseOrderID';

    protected $fillable = [
        'PurchaseDate',
        'ExpectedDeliveryDate',
        'Status',
        'SupplierID',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'SupplierID', 'SupplierID');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'PurchaseOrderID', 'PurchaseOrderID');
    }

    public function damagedProducts()
    {
        return $this->hasMany(DamagedProduct::class, 'PurchaseOrderID', 'PurchaseOrderID');
    }
}
