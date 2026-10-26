<?php

namespace App\Models;

use App\Casts\Quantity;
use App\Services\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'SalesItem';

    protected $primaryKey = 'SalesItemID';

    // Quantity is in the unit it was sold in (Unit: meter, box...) and
    // UnitPrice is that unit's price; BaseQuantity is what it took from
    // stock in the product's base unit (1 box = 100 pieces).
    protected $fillable = [
        'Quantity',
        'Unit',
        'BaseQuantity',
        'UnitPrice',
        'DiscountAmount',
        'ProductID',
        'SalesTransactionID',
    ];

    protected $casts = [
        'Quantity' => Quantity::class,
        'BaseQuantity' => Quantity::class,
    ];

    /**
     * A line saved without its unit is a piece-for-piece line.
     */
    protected static function booted(): void
    {
        static::creating(function (SalesItem $item) {
            $item->Unit = $item->Unit ?: UnitOfMeasure::DEFAULT;
            if ((float) $item->BaseQuantity <= 0) {
                $item->BaseQuantity = $item->Quantity;
            }
        });
    }

    /**
     * Base units per sold unit (100 for a box of 100 pieces, 1 by the piece).
     */
    public function quantityPerUnit(): int|float
    {
        return (float) $this->Quantity > 0 && (float) $this->BaseQuantity > 0
            ? Quantity::normalize($this->BaseQuantity / $this->Quantity)
            : 1;
    }

    /**
     * "1.5 meters", "2 boxes".
     */
    public function quantityLabel(): string
    {
        return UnitOfMeasure::format($this->Quantity, $this->Unit ?: UnitOfMeasure::DEFAULT);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    public function transaction()
    {
        return $this->belongsTo(SalesTransaction::class, 'SalesTransactionID', 'SalesTransactionID');
    }

    // What the customer actually paid per unit of this line — UnitPrice is
    // always the full, undiscounted price; DiscountAmount (this line's own
    // share of the sale's promo discount, null on rows predating this
    // column) is spread evenly back across the line's own quantity so a
    // return of only PART of the line still prorates correctly. Refunds
    // must be based on this, never bare UnitPrice, or a promo'd item gets
    // refunded for more than it was sold for.
    public function getRefundableUnitPriceAttribute(): float
    {
        $discountAmount = (float) ($this->DiscountAmount ?? 0);

        if ($discountAmount <= 0 || $this->Quantity <= 0) {
            return (float) $this->UnitPrice;
        }

        return (float) $this->UnitPrice - ($discountAmount / $this->Quantity);
    }
}
