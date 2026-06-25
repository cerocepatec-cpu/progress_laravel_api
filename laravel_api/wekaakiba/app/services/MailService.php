<?php

namespace App\Services;

use App\Mail\GenericNotificationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;


class MailService
{
    public function sendGenericMail($to, $subject, $message)
    {
        try {
            Mail::to($to)->send(new GenericNotificationMail($subject, $message));
            return true;
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'envoi du mail : " . $e->getMessage());
            return false;
        }
    }
}
