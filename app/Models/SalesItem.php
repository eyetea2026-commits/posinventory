<?php

namespace App\Models;

use App\Casts\Quantity;
use App\Services\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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
        // What the pieces cost, from the stock batches they came out of (FIFO).
        'CostAmount',
        'ProductID',
        'SalesTransactionID',
    ];

    protected $casts = [
        'Quantity' => Quantity::class,
        'BaseQuantity' => Quantity::class,
        'CostAmount' => 'decimal:2',
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

    /**
     * What was paid for the line (after its discount) minus what its
     * pieces cost (FIFO); null when the cost isn't known.
     */
    public function grossProfit(): ?float
    {
        if ($this->CostAmount === null) {
            return null;
        }

        return round((float) $this->Quantity * (float) $this->UnitPrice - (float) ($this->DiscountAmount ?? 0) - (float) $this->CostAmount, 2);
    }

    /**
     * A product's lines on one sale: more than one when its pieces came out
     * of stock batches at different prices. Latest first: a return gives
     * back the last pieces sold first.
     *
     * @return Collection<int, SalesItem>
     */
    public static function linesForProduct(int $salesTransactionId, int $productId, bool $lock = false): Collection
    {
        return self::where('SalesTransactionID', $salesTransactionId)
            ->where('ProductID', $productId)
            ->orderByDesc('SalesItemID')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();
    }

    /**
     * What $quantity returned pieces were paid (after their line's discount)
     * and listed at, coming after the $alreadyReturned pieces before them,
     * each at its own line's price.
     *
     * @return array{refundable: float, listed: float}
     */
    public static function returnedAmounts(int $salesTransactionId, int $productId, float $quantity, float $alreadyReturned = 0): array
    {
        $refundable = 0.0;
        $listed = 0.0;
        $lines = self::linesForProduct($salesTransactionId, $productId);
        // Never skip past the pieces this return needs.
        $sold = (float) $lines->sum(fn (SalesItem $line) => (float) $line->Quantity);
        $skip = max(0, min($alreadyReturned, $sold - $quantity));

        foreach ($lines as $line) {
            if ($quantity <= 0) {
                break;
            }
            $available = (float) $line->Quantity;
            $skipped = min($skip, $available);
            $skip -= $skipped;
            $taken = min($available - $skipped, $quantity);
            if ($taken > 0) {
                $refundable += $taken * $line->refundable_unit_price;
                $listed += $taken * (float) $line->UnitPrice;
                $quantity = round($quantity - $taken, 2);
            }
        }

        // More than the lines hold: the rest at the oldest line's price.
        $oldest = $lines->last();
        if ($quantity > 0 && $oldest) {
            $refundable += $quantity * $oldest->refundable_unit_price;
            $listed += $quantity * (float) $oldest->UnitPrice;
        }

        return ['refundable' => round($refundable, 2), 'listed' => round($listed, 2)];
    }

    public function stockBatchAllocations()
    {
        return $this->hasMany(StockBatchAllocation::class, 'SalesItemID', 'SalesItemID');
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
