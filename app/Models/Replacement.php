<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Replacement extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'Replacement';
    protected $primaryKey = 'ReplacementID';

    protected $fillable = [
        'SalesReturnID',
        'ReplacementProductID',
        'Quantity',
        'ProcessedBy',
        'ReplacementDate',
        'SlipNumber',
        'Notes',
    ];

    public function salesReturn()
    {
        return $this->belongsTo(SalesReturn::class, 'SalesReturnID', 'SalesReturnID');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'ReplacementProductID', 'ProductID');
    }

    public function processedByUser()
    {
        return $this->belongsTo(User::class, 'ProcessedBy', 'id');
    }
}
