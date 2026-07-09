<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'ActivityLog';
    protected $primaryKey = 'ActivityLogID';

    protected $fillable = [
        'UserID',
        'Action',
        'Description',
        'DateRecorded',
    ];

    protected $casts = [
        'DateRecorded' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'UserID', 'id');
    }

    public static function record(string $action, string $description, ?int $userId = null): void
    {
        static::create([
            'UserID' => $userId ?? auth()->id(),
            'Action' => $action,
            'Description' => $description,
            'DateRecorded' => now(),
        ]);
    }
}
