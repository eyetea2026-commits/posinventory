<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'Brand';
    protected $primaryKey = 'BrandID';

    protected $fillable = ['BrandName'];

    public function products()
    {
        return $this->hasMany(Product::class, 'BrandID', 'BrandID');
    }
}
