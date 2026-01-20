<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\User;
use App\Http\Controllers\Controller; // pour accéder à getEse()

class PasswordResetSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $enterprise;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;

        // 🔹 Récupérer les infos de l’entreprise
        $controller = new Controller();
        $this->enterprise = $controller->getEse($user->id);
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Confirmation de réinitialisation du mot de passe')
                    ->view('emails.password_reset_success')
                    ->with([
                        'user' => $this->user,
                        'enterprise' => $this->enterprise
                    ]);
    }
}
