<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSerial extends Model
{
    use HasFactory;

    public const STATUS_IN_STOCK = 'In Stock';

    public const STATUS_SOLD = 'Sold';

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

    public static function returnableFor(int $salesTransactionId, int $productId)
    {
        return self::where('SalesTransactionID', $salesTransactionId)
            ->where('ProductID', $productId)
            ->where('Status', self::STATUS_SOLD)
            ->whereNull('SalesReturnID')
            ->orderBy('ProductSerialID');
    }

    public static function formatNumber(int $id): string
    {
        return 'SN'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }
}
