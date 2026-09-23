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
        // BrandNameNormalized backs the case-insensitive unique index on
        // (BrandNameNormalized, CategoryID) -- kept in sync here so every
        // write path (this controller, tinker, a future API endpoint, a
        // script) gets the same duplicate protection without having to
        // remember to set it manually.
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
