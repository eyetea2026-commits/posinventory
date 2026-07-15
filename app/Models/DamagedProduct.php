<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamagedProduct extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'DamagedProduct';
    protected $primaryKey = 'DamageID';

    protected $fillable = [
        'Quantity',
        'Description',
        'DateRecorded',
        'ProductID',
        'SupplierID',
        'Status',
        'PurchaseOrderID',
        'DamageType',
        'InspectionNotes',
        'WarehouseLocation',
        'Remarks',
        'ResolvedBy',
        'ResolvedDate',
    ];

    protected $casts = [
        'DateRecorded' => 'date',
        'Quantity' => 'integer',
        'ResolvedDate' => 'date',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_FOR_SUPPLIER_RETURN = 'for_supplier_return';
    const STATUS_RETURNED_TO_SUPPLIER = 'returned_to_supplier';
    const STATUS_DISPOSED = 'disposed';

    const DAMAGE_TYPES = [
        'factory_defect' => 'Factory Defect',
        'broken' => 'Broken',
        'expired' => 'Expired',
        'leaking' => 'Leaking',
        'missing_parts' => 'Missing Parts',
        'packaging_damage' => 'Packaging Damage',
        'wrong_delivery' => 'Wrong Delivery',
        'other' => 'Other',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'SupplierID', 'SupplierID');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'PurchaseOrderID', 'PurchaseOrderID');
    }

    public function resolvedByUser()
    {
        return $this->belongsTo(User::class, 'ResolvedBy', 'id');
    }
}