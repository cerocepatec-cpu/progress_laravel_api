<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class OTPService
{
    public function generateOtp($userId)
    {
        $otp = rand(100000, 999999);

        Cache::put("otp_{$userId}", $otp, now()->addMinutes(5));

        return $otp;
    }

    public function verifyOtp($userId, $otp)
    {
        $cached = Cache::get("otp_{$userId}");

        if (!$cached || $otp != $cached) {
            return false;
        }

        Cache::forget("otp_{$userId}");
        return true;
    }
}
