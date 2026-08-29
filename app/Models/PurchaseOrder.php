<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $table = 'PurchaseOrder';
    protected $primaryKey = 'PurchaseOrderID';

    protected $fillable = [
        'PONumber',
        'PurchaseDate',
        'ExpectedDeliveryDate',
        'Notes',
        'Status',
        'SupplierID',
        'CreatedBy',
        'ApprovedBy',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    const STATUS_FULLY_RECEIVED = 'fully_received';
    const STATUS_CANCELLED = 'cancelled';

    const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PENDING => 'Pending',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_PARTIALLY_RECEIVED => 'Partially Received',
        self::STATUS_FULLY_RECEIVED => 'Fully Received',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    // Statuses where the supplier/items are still safe to change — once
    // approved, receiving starts referencing these exact line IDs.
    const EDITABLE_STATUSES = [self::STATUS_DRAFT, self::STATUS_PENDING];

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

    public function stockReceivings()
    {
        return $this->hasMany(StockReceiving::class, 'PurchaseOrderID', 'PurchaseOrderID');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'CreatedBy', 'id');
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'ApprovedBy', 'id');
    }

    public function isFullyReceived(): bool
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return $items->isNotEmpty() && $items->every(fn (PurchaseOrderItem $item) => $item->ReceivedQuantity >= $item->Quantity);
    }

    public function hasAnyReceivedQuantity(): bool
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return $items->contains(fn (PurchaseOrderItem $item) => $item->ReceivedQuantity > 0);
    }
}
