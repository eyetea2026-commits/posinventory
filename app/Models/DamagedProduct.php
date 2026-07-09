<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamagedProduct extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'DamagedProduct';
    protected $primaryKey = 'DamageID';

    protected $fillable = [
        'Quantity',
        'Description',
        'DateRecorded',
        'ProductID',
        'SupplierID',
    ];

    protected $casts = [
        'DateRecorded' => 'date',
        'Quantity' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'SupplierID', 'SupplierID');
    }
}