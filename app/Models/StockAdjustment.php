<?php

namespace App\Models;

use App\Casts\Quantity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'StockAdjustment';

    protected $primaryKey = 'AdjustmentID';

    protected $fillable = [
        'QuantityAdjust',
        'Reason',
        'Remarks',
        'Date',
        'ProductID',
    ];

    protected $casts = [
        // In the product's base unit (-0.5 meters is a valid adjustment).
        'QuantityAdjust' => Quantity::class,
    ];

    const REASON_DAMAGED = 'Damaged';

    const REASON_LOST = 'Lost';

    // No longer offered; older adjustments may still carry it.
    const REASON_COUNT_ERROR = 'Inventory Count Error';

    // Every adjustment takes stock out (QuantityAdjust is stored negative);
    // stock only comes in through Stock Receiving.
    const REASONS = [
        self::REASON_DAMAGED,
        self::REASON_LOST,
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    /**
     * The serial-numbered pieces this adjustment took out of stock.
     */
    public function serials()
    {
        return $this->hasMany(ProductSerial::class, 'StockAdjustmentID', 'AdjustmentID')->orderBy('ProductSerialID');
    }

    /**
     * The status a piece taken out by this reason gets.
     */
    public static function serialStatusFor(string $reason): string
    {
        return $reason === self::REASON_DAMAGED ? ProductSerial::STATUS_DAMAGED : ProductSerial::STATUS_LOST;
    }

    public function damagedProduct()
    {
        return $this->hasOne(DamagedProduct::class, 'StockAdjustmentID', 'AdjustmentID');
    }
}
