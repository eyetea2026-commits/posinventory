<?php

namespace App\Models;

use App\Casts\Quantity;
use App\Services\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'PurchaseOrderItem';

    protected $primaryKey = 'PurchaseOrderItemID';

    // Quantity, ReceivedQuantity and CancelledQuantity are in the unit it
    // was ordered in (Unit: roll, box...), CostPriceAtOrder is per that
    // unit, and QuantityPerUnit is how many base units one of it adds to
    // stock (1 roll = 5,000 meters), as it was when the order was made.
    protected $fillable = [
        'Quantity',
        'Unit',
        'QuantityPerUnit',
        'ReceivedQuantity',
        'CancelledQuantity',
        'ReceiptNumber',
        'CostPriceAtOrder',
        'PurchaseOrderID',
        'ProductID',
    ];

    protected $casts = [
        'QuantityPerUnit' => Quantity::class,
    ];

    /**
     * The unit and conversion a new order line for this product uses.
     *
     * @return array{Unit: string, QuantityPerUnit: int|float}
     */
    public static function unitFor(Product $product): array
    {
        return ['Unit' => $product->orderUnit(), 'QuantityPerUnit' => $product->quantityPerOrderUnit()];
    }

    /**
     * How many base units this many ordered units add to stock.
     */
    public function toBaseQuantity(int|float $quantity): int|float
    {
        return Quantity::normalize($quantity * ((float) $this->QuantityPerUnit ?: 1));
    }

    /**
     * "2 rolls" - plus "(10,000 meters)" when one roll isn't one meter.
     */
    public function quantityLabel(int|float|null $quantity = null): string
    {
        $quantity ??= $this->Quantity;
        $unit = $this->Unit ?: UnitOfMeasure::DEFAULT;
        $label = UnitOfMeasure::format($quantity, $unit);
        $baseUnit = $this->product?->baseUnit();

        if ((float) $this->QuantityPerUnit != 1 && $baseUnit && $baseUnit !== $unit) {
            $label .= ' ('.UnitOfMeasure::format($this->toBaseQuantity($quantity), $baseUnit).')';
        }

        return $label;
    }

    /**
     * For tables: a plain count for pieces (as always), "2 rolls (10,000
     * meters)" for anything ordered by another unit.
     */
    public function tableQuantity(int|float|null $quantity = null): string
    {
        $quantity ??= $this->Quantity;

        return $this->isOrderedByThePiece() ? UnitOfMeasure::number($quantity) : $this->quantityLabel($quantity);
    }

    /**
     * "₱4,500.00" per piece, "₱4,500.00/roll" otherwise.
     */
    public function unitCostLabel(): string
    {
        return '₱'.number_format((float) $this->CostPriceAtOrder, 2).($this->isOrderedByThePiece() ? '' : '/'.$this->Unit);
    }

    private function isOrderedByThePiece(): bool
    {
        return ($this->Unit ?: UnitOfMeasure::DEFAULT) === UnitOfMeasure::DEFAULT && (float) ($this->QuantityPerUnit ?: 1) == 1;
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'PurchaseOrderID', 'PurchaseOrderID');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    /**
     * Still owed by the supplier: ordered, less what arrived and what the
     * owner cancelled.
     */
    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->Quantity - $this->ReceivedQuantity - (int) $this->CancelledQuantity);
    }

    // The financial value of this PO line — what was ORDERED at the price
    // agreed for this order, not what has arrived so far. Using
    // ReceivedQuantity here made every line show ₱0.00 until receiving
    // started, which isn't what a Line Total is for; Received/Remaining
    // already have their own columns for tracking fulfillment progress.
    public function getLineTotalAttribute(): float
    {
        return $this->Quantity * $this->CostPriceAtOrder;
    }
}
