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

    protected $fillable = ['BrandName', 'CategoryID', 'BrandNameNormalized'];

    protected static function booted(): void
    {
        static::saving(function (Brand $brand) {
            $brand->BrandNameNormalized = mb_strtolower(trim((string) $brand->BrandName));
        });
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'BrandID', 'BrandID');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'CategoryID', 'CategoryID');
    }
}
