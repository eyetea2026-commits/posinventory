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

    protected $fillable = [
        'Quantity',
        'Unit',
        'BaseQuantity',
        'UnitPrice',
        'DiscountAmount',
        'CostAmount',
        'ProductID',
        'SalesTransactionID',
    ];

    protected $casts = [
        'Quantity' => Quantity::class,
        'BaseQuantity' => Quantity::class,
        'CostAmount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (SalesItem $item) {
            $item->Unit = $item->Unit ?: UnitOfMeasure::DEFAULT;
            if ((float) $item->BaseQuantity <= 0) {
                $item->BaseQuantity = $item->Quantity;
            }
        });
    }

    public function quantityPerUnit(): int|float
    {
        return (float) $this->Quantity > 0 && (float) $this->BaseQuantity > 0
            ? Quantity::normalize($this->BaseQuantity / $this->Quantity)
            : 1;
    }

    public function quantityLabel(): string
    {
        return UnitOfMeasure::format($this->Quantity, $this->Unit ?: UnitOfMeasure::DEFAULT);
    }

    public function grossProfit(): ?float
    {
        if ($this->CostAmount === null) {
            return null;
        }

        return round((float) $this->Quantity * (float) $this->UnitPrice - (float) ($this->DiscountAmount ?? 0) - (float) $this->CostAmount, 2);
    }

    public static function linesForProduct(int $salesTransactionId, int $productId, bool $lock = false): Collection
    {
        return self::where('SalesTransactionID', $salesTransactionId)
            ->where('ProductID', $productId)
            ->orderByDesc('SalesItemID')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();
    }

    public static function returnedAmounts(int $salesTransactionId, int $productId, float $quantity, float $alreadyReturned = 0): array
    {
        $refundable = 0.0;
        $listed = 0.0;
        $lines = self::linesForProduct($salesTransactionId, $productId);
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

    public function getRefundableUnitPriceAttribute(): float
    {
        $discountAmount = (float) ($this->DiscountAmount ?? 0);

        if ($discountAmount <= 0 || $this->Quantity <= 0) {
            return (float) $this->UnitPrice;
        }

        return (float) $this->UnitPrice - ($discountAmount / $this->Quantity);
    }
}
