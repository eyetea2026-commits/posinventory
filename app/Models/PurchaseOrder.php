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
        'RemainderCancelReason',
        'RemainderCancelledBy',
        'RemainderCancelledAt',
    ];

    protected function casts(): array
    {
        return [
            'RemainderCancelledAt' => 'datetime',
        ];
    }

    const STATUS_DRAFT = 'draft';

    const STATUS_PENDING = 'pending';

    const STATUS_APPROVED = 'approved';

    const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    const STATUS_FULLY_RECEIVED = 'fully_received';

    const STATUS_CANCELLED = 'cancelled';

    // Part was received and the owner cancelled the rest: nothing more is
    // expected from the supplier.
    const STATUS_CLOSED = 'closed';

    const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PENDING => 'Pending',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_PARTIALLY_RECEIVED => 'Partially Received',
        self::STATUS_FULLY_RECEIVED => 'Fully Received',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_CLOSED => 'Closed',
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

    // The single Stock Receiving batch this PO was sent into when printed
    // (see PurchaseOrderController::printPreview()) — distinct from the
    // legacy per-line stockReceivings() log above, which the manual "Record
    // Receipt" flow still uses independently.
    public function stockReceivingBatch()
    {
        return $this->hasOne(StockReceivingBatch::class, 'PurchaseOrderID', 'PurchaseOrderID');
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

    /**
     * Nothing more is owed: every line is received or its rest cancelled.
     */
    public function isSettled(): bool
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return $items->isNotEmpty() && $items->every(fn (PurchaseOrderItem $item) => $item->remaining_quantity <= 0);
    }

    public function hasAnyCancelledQuantity(): bool
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return $items->contains(fn (PurchaseOrderItem $item) => (int) $item->CancelledQuantity > 0);
    }

    public function deliveries()
    {
        return $this->hasMany(StockReceivingDelivery::class, 'PurchaseOrderID', 'PurchaseOrderID');
    }

    public function remainderCancelledByUser()
    {
        return $this->belongsTo(User::class, 'RemainderCancelledBy', 'id');
    }

    public function hasAnyReceivedQuantity(): bool
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return $items->contains(fn (PurchaseOrderItem $item) => $item->ReceivedQuantity > 0);
    }
}
