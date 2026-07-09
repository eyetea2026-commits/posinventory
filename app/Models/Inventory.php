<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'Inventory';
    protected $primaryKey = 'InventoryID';

    protected $fillable = [
        'Quantity',
        'Status',
        'ReorderThreshold',
        'ProductID',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }
}
