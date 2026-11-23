<?php

namespace App\Models;

use App\Casts\Quantity;
use App\Services\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReturnItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'SalesReturnItem';

    protected $primaryKey = 'SalesReturnItemID';

    protected $fillable = [
        'SalesReturnID',
        'ProductID',
        'Quantity',
        'Unit',
        'BaseQuantity',
        'UnitPrice',
        'Reason',
    ];

    protected $casts = [
        'Quantity' => Quantity::class,
        'BaseQuantity' => Quantity::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (SalesReturnItem $item) {
            $item->Unit = $item->Unit ?: UnitOfMeasure::DEFAULT;
            if ((float) $item->BaseQuantity <= 0) {
                $item->BaseQuantity = $item->Quantity;
            }
        });
    }

    public function quantityLabel(): string
    {
        return UnitOfMeasure::format($this->Quantity, $this->Unit ?: UnitOfMeasure::DEFAULT);
    }

    public function salesReturn()
    {
        return $this->belongsTo(SalesReturn::class, 'SalesReturnID', 'SalesReturnID');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    public function getLineTotalAttribute(): float
    {
        return $this->Quantity * $this->UnitPrice;
    }

    public function refundableTotal(): float
    {
        $salesTransactionId = (int) $this->salesReturn?->SalesTransactionID;
        $earlier = (float) self::where('ProductID', $this->ProductID)
            ->where('SalesReturnID', '<', $this->SalesReturnID)
            ->whereHas('salesReturn', fn ($query) => $query
                ->where('SalesTransactionID', $salesTransactionId)
                ->where('Status', '!=', SalesReturn::STATUS_DECLINED))
            ->sum('Quantity');

        if (SalesItem::linesForProduct($salesTransactionId, $this->ProductID)->isEmpty()) {
            return round($this->line_total, 2);
        }

        return SalesItem::returnedAmounts($salesTransactionId, $this->ProductID, (float) $this->Quantity, $earlier)['refundable'];
    }

    public function getIsUnsalableAttribute(): bool
    {
        return in_array($this->Reason, SalesReturn::UNSALABLE_REASONS, true);
    }
}
