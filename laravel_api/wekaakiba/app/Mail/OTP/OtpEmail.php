<?php

namespace App\Mail\OTP;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class OtpEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $otp;

    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    public function build()
    {
        return $this->subject("Votre OTP de confirmation")
                    ->view('emails.otp')
                    ->with(['otp' => $this->otp]);
    }
}
