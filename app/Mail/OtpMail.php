<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// Not ShouldQueue: an OTP must reach the recipient before they act on the
// page, so it has to send synchronously in the request — a queued OTP is
// only as good as whatever processes the queue, and this app has no
// reliable persistent worker (Hostinger shared hosting has no crontab
// access, and a scheduled GitHub Actions workaround never actually fired).
// The earlier "web requests can't send mail reliably" finding turned out to
// be a misdiagnosis: production's live document root has its own .env,
// separate from the directory these fixes were mistakenly tested against —
// once that was corrected, direct synchronous sending works correctly.
class OtpMail extends Mailable
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
