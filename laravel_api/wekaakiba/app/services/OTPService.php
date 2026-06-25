<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class OTPService
{
    /**
     * Génère un OTP et le stocke en cache
     *
     * @param int    $userId
     * @param string $context
     * @param int    $ttlMinutes Durée de validité (minutes) – défaut 5
     */
    public function generateOtp(
        int $userId,
        string $context,
        int $ttlMinutes = 5
    ): string {
        $otp = (string) random_int(100000, 999999);

        Cache::put(
            $this->key($userId, $context),
            $otp,
            now()->addMinutes($ttlMinutes)
        );

        return $otp;
    }

    /**
     * Vérifie un OTP et l'invalide s'il est correct
     */
    public function verifyOtp(
        int $userId,
        string $context,
        string $otp
    ): bool {
        $cached = Cache::get($this->key($userId, $context));

        if (!$cached || $otp !== $cached) {
            return false;
        }

        Cache::forget($this->key($userId, $context));
        return true;
    }

    private function key(int $userId, string $context): string
    {
        return "otp_{$userId}_{$context}";
    }
}

