<?php

namespace App\Helpers;

use App\Jobs\OTP\SendOtpSmsJob;


class OtpQueueHelper
{
    public static function send( $user_phone,  $is_collector, $user_id, $user_email,  string $otp, ?string $channel = 'sms'): void
    {
        SendOtpSmsJob::dispatch(
            $user_phone,
            $is_collector,
            $user_id,
            $user_email,
            $otp,
            $channel ?? 'sms'
        )->onQueue('otp');
    }
}
