<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'Discount';
    protected $primaryKey = 'DiscountID';

    protected $fillable = [
        'DiscountRate',
    ];

    public function billings()
    {
        return $this->hasMany(Billing::class, 'DiscountID', 'DiscountID');
    }
}
