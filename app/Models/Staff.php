<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'Staff';
    protected $primaryKey = 'StaffID';

    protected $fillable = [
        'FirstName',
        'MiddleName',
        'LastName',
        'ContactNumber',
        'Email',
        'Age',
        'Gender',
        'UserID',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'UserID', 'id');
    }

    public function salesTransactions()
    {
        return $this->hasMany(SalesTransaction::class, 'StaffID', 'StaffID');
    }
}
