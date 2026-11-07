<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSerial extends Model
{
    use HasFactory;

    public const STATUS_IN_STOCK = 'In Stock';

    public const STATUS_SOLD = 'Sold';

    // Older stock adjustments, made before the exact pieces were picked.
    public const STATUS_ADJUSTED_OUT = 'Adjusted Out';

    public const STATUS_DAMAGED = 'Damaged';

    public const STATUS_LOST = 'Lost';

    public const STATUS_REMOVED = 'Removed from Stock';

    protected $table = 'ProductSerial';

    protected $primaryKey = 'ProductSerialID';

    protected $fillable = [
        'ProductID',
        'SerialNumber',
        'Status',
        'SalesTransactionID',
        'StockAdjustmentID',
        // The return request it came back on (see returnableFor()).
        'SalesReturnID',
        'StatusDate',
    ];

    protected function casts(): array
    {
        return [
            'StatusDate' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    public function salesTransaction()
    {
        return $this->belongsTo(SalesTransaction::class, 'SalesTransactionID', 'SalesTransactionID');
    }

    public function salesReturn()
    {
        return $this->belongsTo(SalesReturn::class, 'SalesReturnID', 'SalesReturnID');
    }

    /**
     * The pieces of a product sold on a sale that can still be returned:
     * sold, and not already on a (not declined) return request.
     *
     * @return Builder<ProductSerial>
     */
    public static function returnableFor(int $salesTransactionId, int $productId)
    {
        return self::where('SalesTransactionID', $salesTransactionId)
            ->where('ProductID', $productId)
            ->where('Status', self::STATUS_SOLD)
            ->whereNull('SalesReturnID')
            ->orderBy('ProductSerialID');
    }

    /**
     * SN00001: one count across all products, so a number never repeats.
     */
    public static function formatNumber(int $id): string
    {
        return 'SN'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }
}
