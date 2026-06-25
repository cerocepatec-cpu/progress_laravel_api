<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SecurityLoginAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public array $context
    ) {}

    public function build()
    {
        return $this->subject('Nouvelle connexion à votre compte')
            ->view('emails.security-login-alert');
    }
}

