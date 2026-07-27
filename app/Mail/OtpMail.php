<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

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
