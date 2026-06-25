<?php

namespace App\Mail;

use App\Models\TwoFactorRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TwoFactorMagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public TwoFactorRequest $request;

    public function __construct(TwoFactorRequest $request)
    {
        $this->request = $request;
    }

    public function build()
    {
        $url = config('app.url') . '/2fa/verify/' . $this->request->token;

        return $this->subject('🔐 Vérification de sécurité requise')
            ->markdown('emails.twofactor.magic-link', [
                'url' => $url,
                'request' => $this->request,
            ]);
    }
}
