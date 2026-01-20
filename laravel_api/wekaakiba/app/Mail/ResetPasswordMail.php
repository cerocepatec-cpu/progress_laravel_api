<?php

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $token;
    public $email;

    public function __construct($token, $email)
    {
        $this->token = $token;
        $this->email = $email;
    }

    public function build()
    {
        return $this->subject('Réinitialisation de mot de passe')
                    ->view('emails.reset')
                    ->with([
                        'resetUrl' => url("/password/reset?token={$this->token}&email={$this->email}")
                    ]);
    }
}

