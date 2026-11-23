<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProductSupplier extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'ProductSupplier';

    protected $primaryKey = 'ProductSupplierID';

    protected $fillable = [
        'ProductID',
        'SupplierID',
        'CostPrice',
        'IsPreferred',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'SupplierID', 'SupplierID');
    }

    public function markPreferred(): void
    {
        DB::transaction(function () {
            ProductSupplier::where('ProductID', $this->ProductID)
                ->where('IsPreferred', 1)
                ->lockForUpdate()
                ->update(['IsPreferred' => null]);

            $this->update(['IsPreferred' => 1]);
        });
    }
}
