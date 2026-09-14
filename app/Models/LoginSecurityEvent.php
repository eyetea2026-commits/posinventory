<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginSecurityEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'LoginSecurityEvent';
    protected $primaryKey = 'LoginSecurityEventID';

    protected $fillable = [
        'UserID',
        'IPAddress',
        'UserAgent',
        'DeviceSummary',
        'SessionID',
        'ConfirmationStatus',
        'LoginAt',
        'ConfirmedAt',
    ];

    protected $casts = [
        'LoginAt' => 'datetime',
        'ConfirmedAt' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_NOT_RECOGNIZED = 'not_recognized';

    public function user()
    {
        return $this->belongsTo(User::class, 'UserID', 'id');
    }

    public function isPending(): bool
    {
        return $this->ConfirmationStatus === self::STATUS_PENDING;
    }

    // Good-enough human summary ("Chrome on Windows") without a UA-parsing
    // dependency -- this app installs none, and a full parser is overkill
    // for a display-only field in a security notification.
    public static function summarizeUserAgent(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        $browser = match (true) {
            (bool) preg_match('/Edg\//', $userAgent) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/', $userAgent) => 'Opera',
            (bool) preg_match('/Chrome\//', $userAgent) => 'Chrome',
            (bool) preg_match('/Firefox\//', $userAgent) => 'Firefox',
            (bool) preg_match('/Safari\//', $userAgent) => 'Safari',
            default => 'Unknown Browser',
        };

        $os = match (true) {
            (bool) preg_match('/Windows/', $userAgent) => 'Windows',
            (bool) preg_match('/Mac OS X/', $userAgent) => 'macOS',
            (bool) preg_match('/Android/', $userAgent) => 'Android',
            (bool) preg_match('/iPhone|iPad|iOS/', $userAgent) => 'iOS',
            (bool) preg_match('/Linux/', $userAgent) => 'Linux',
            default => 'Unknown Device',
        };

        return "{$browser} on {$os}";
    }
}
