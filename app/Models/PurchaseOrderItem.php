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

    const MAX_COST_PRICE = 99999.99;

    const MAX_QUANTITY = 999;

    const MAX_METERS_PER_ROLL = 9999;

    protected $primaryKey = 'PurchaseOrderItemID';

    protected $fillable = [
        'Quantity',
        'Unit',
        'QuantityPerUnit',
        'ReceivedQuantity',
        'CancelledQuantity',
        'CostPriceAtOrder',
        'PurchaseOrderID',
        'ProductID',
    ];

    protected $casts = [
        'QuantityPerUnit' => Quantity::class,
    ];

    public static function unitFor(Product $product): array
    {
        return ['Unit' => $product->orderUnit(), 'QuantityPerUnit' => $product->quantityPerOrderUnit()];
    }

    public function orderedMetersPerRoll(): int|float|null
    {
        $product = $this->product;

        return $product
            && $product->baseUnit() === 'meter'
            && ($this->Unit ?: UnitOfMeasure::DEFAULT) !== 'meter'
            && (float) $this->QuantityPerUnit > 1
            ? $this->QuantityPerUnit
            : null;
    }

    public function costPerBaseUnit(): ?float
    {
        if ($this->CostPriceAtOrder === null) {
            return null;
        }

        return round((float) $this->CostPriceAtOrder / ((float) $this->QuantityPerUnit ?: 1), 2);
    }

    public function toBaseQuantity(int|float $quantity): int|float
    {
        return Quantity::normalize($quantity * ((float) $this->QuantityPerUnit ?: 1));
    }

    public function quantityLabel(int|float|null $quantity = null): string
    {
        return UnitOfMeasure::format($quantity ?? $this->Quantity, $this->Unit ?: UnitOfMeasure::DEFAULT);
    }

    public function tableQuantity(int|float|null $quantity = null): string
    {
        $quantity ??= $this->Quantity;

        return $this->isOrderedByThePiece() ? UnitOfMeasure::number($quantity) : $this->quantityLabel($quantity);
    }

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

    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->Quantity - $this->ReceivedQuantity - (int) $this->CancelledQuantity);
    }

    public function getLineTotalAttribute(): float
    {
        return $this->Quantity * $this->CostPriceAtOrder;
    }
}
