<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordChangedSecurityAlert extends Mailable
{
    use Queueable, SerializesModels;

    public $user, $ip, $device, $os, $browser;

    public function __construct($user, $ip, $device, $os, $browser)
    {
        $this->user    = $user;
        $this->ip      = $ip;
        $this->device  = $device;
        $this->os      = $os;
        $this->browser = $browser;
    }

    public function build()
    {
        return $this->subject('🔐 Alerte sécurité : Mot de passe modifié')
                    ->view('emails.password_changed_security_alert');
    }
}
