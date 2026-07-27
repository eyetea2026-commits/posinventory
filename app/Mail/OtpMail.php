<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// ShouldQueue: outbound network calls (SMTP or HTTP API alike) triggered
// directly from a web request on this host are unreliable — proven via many
// side-by-side tests where the identical send succeeds 100% of the time from
// a CLI process but intermittently vanishes with no error when triggered by
// a real page load. Queuing moves the actual send to the cron-triggered
// `queue:work` CLI process instead, which has been reliable in every test.
class OtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $otp;
    public $expiryMinutes;

    public function __construct($otp, $expiryMinutes = 5)
    {
        $this->otp = $otp;
        $this->expiryMinutes = $expiryMinutes;
    }

    public function build()
    {
        return $this->subject('Your OTP Code')->view('emails.otp')->with([
            'otp' => $this->otp,
            'expiryMinutes' => $this->expiryMinutes,
        ]);
    }
}
