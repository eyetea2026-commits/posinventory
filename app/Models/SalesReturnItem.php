<?php

namespace App\Models;

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
        'UnitPrice',
        'Reason',
    ];

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

    public function getIsUnsalableAttribute(): bool
    {
        return in_array($this->Reason, SalesReturn::UNSALABLE_REASONS, true);
    }
}
