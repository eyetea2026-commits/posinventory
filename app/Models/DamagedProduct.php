<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DamagedProduct extends Model
{
    use HasFactory, SoftDeletes;

    public $timestamps = false;

    protected $table = 'DamagedProduct';
    protected $primaryKey = 'DamageID';

    protected $fillable = [
        'Quantity',
        'Description',
        'DateRecorded',
        'ProductID',
        'SalesReturnID',
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
    const STATUS_REPLACEMENT_RECEIVED = 'replacement_received';
    const STATUS_DISPOSED = 'disposed';
    const STATUS_CANCELLED = 'cancelled';

    // Display label for STATUS_FOR_SUPPLIER_RETURN — kept as "Pending Supplier
    // Return" in the UI without renaming the stored value (avoids a data
    // migration on existing rows).
    const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_FOR_SUPPLIER_RETURN => 'Pending Supplier Return',
        self::STATUS_RETURNED_TO_SUPPLIER => 'Returned to Supplier',
        self::STATUS_REPLACEMENT_RECEIVED => 'Replacement Received',
        self::STATUS_DISPOSED => 'Disposed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    const DAMAGE_TYPES = [
        'factory_defect' => 'Factory Defect',
        'damaged_product' => 'Damaged Product',
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

    public function salesReturn()
    {
        return $this->belongsTo(SalesReturn::class, 'SalesReturnID', 'SalesReturnID');
    }

    public function resolvedByUser()
    {
        return $this->belongsTo(User::class, 'ResolvedBy', 'id');
    }
}