<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SendOtpMember
{
    use Dispatchable, SerializesModels;

    /**
     * @param int    $memberId   ID du membre
     * @param string $otp        Code OTP
     * @param string $context    Contexte métier (ex: withdraw_validation)
     * @param string $channel    Canal d'envoi (sms | email)
     */
    public function __construct(
        public int $memberId,
        public string $otp,
        public string $context,
        public string $channel = 'sms'
    ) {}
}
