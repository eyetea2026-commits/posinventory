<?php

namespace App\Models;

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

    const REASON_DAMAGED = 'Damaged';
    const REASON_LOST = 'Lost';
    const REASON_STOLEN = 'Stolen';
    const REASON_COUNT_ERROR = 'Inventory Count Error';

    const REASONS = [
        self::REASON_DAMAGED,
        self::REASON_LOST,
        self::REASON_STOLEN,
        self::REASON_COUNT_ERROR,
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    public function damagedProduct()
    {
        return $this->hasOne(DamagedProduct::class, 'StockAdjustmentID', 'AdjustmentID');
    }
}
