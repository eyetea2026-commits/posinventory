<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'SalesItem';
    protected $primaryKey = 'SalesItemID';

    protected $fillable = [
        'Quantity',
        'UnitPrice',
        'DiscountAmount',
        'ProductID',
        'SalesTransactionID',
    ];

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
