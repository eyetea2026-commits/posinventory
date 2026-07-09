<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    public $timestamps = false;

    protected $table = 'Customer';
    protected $primaryKey = 'CustomerID';

    protected $fillable = [
        'CustomerName',
        'ContactNumber',
        'Email',
        'Address',
    ];
}