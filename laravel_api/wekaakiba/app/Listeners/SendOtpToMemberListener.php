<?php

namespace App\Listeners;

use App\Models\User;
use App\Events\SendOtpMember;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendOtpToMemberListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(SendOtpMember $event): void
    {
        $member = User::find($event->memberId);

        if (!$member) {
            Log::warning("OTP non envoyé : membre introuvable", [
                'member_id' => $event->memberId,
                'context' => $event->context
            ]);
            return;
        }

        try {
            if ($event->channel === 'sms') {
                $this->sendSms($member, $event->otp, $event->context);
            } else {
                $this->sendEmail($member, $event->otp, $event->context);
            }
        } catch (\Throwable $e) {
            Log::error("Erreur envoi OTP", [
                'member_id' => $member->id,
                'channel' => $event->channel,
                'context' => $event->context,
                'error' => $e->getMessage()
            ]);

            // optionnel : relancer le job
            throw $e;
        }
    }

    /* ===========================
       SMS
    =========================== */
    protected function sendSms(User $member, string $otp, string $context): void
    {
        if (!$member->user_phone) {
            throw new \Exception("Numéro de téléphone manquant pour le membre.");
        }

        $message = match ($context) {
            'withdraw_validation' =>
                "Votre code de validation de retrait est : {$otp}. Valable 15 minutes.",
            default =>
                "Votre code de confirmation est : {$otp}."
        };

        // 👉 Exemple Twilio / autre gateway
        // SmsService::send($member->user_phone, $message);

        Log::info("OTP SMS envoyé", [
            'member_id' => $member->id,
            'phone' => $member->user_phone,
            'context' => $context
        ]);
    }

    /* ===========================
       EMAIL
    =========================== */
    protected function sendEmail(User $member, string $otp, string $context): void
    {
        if (!$member->email) {
            throw new \Exception("Email manquant pour le membre.");
        }

        $subject = match ($context) {
            'withdraw_validation' =>
                'Code de validation de retrait',
            default =>
                'Code de confirmation'
        };

        $body = match ($context) {
            'withdraw_validation' =>
                "Bonjour {$member->name},\n\nVotre code de validation de retrait est : {$otp}.\nIl est valable 15 minutes.\n\nNe le partagez qu'avec le collecteur.",
            default =>
                "Bonjour {$member->name},\n\nVotre code de confirmation est : {$otp}."
        };

        Mail::raw($body, function ($message) use ($member, $subject) {
            $message->to($member->email)
                    ->subject($subject);
        });

        Log::info("OTP Email envoyé", [
            'member_id' => $member->id,
            'email' => $member->email,
            'context' => $context
        ]);
    }
}
