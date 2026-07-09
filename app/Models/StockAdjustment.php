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
        'Date',
        'ProductID',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }
}
