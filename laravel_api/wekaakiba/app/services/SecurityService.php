<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class SecurityService
{
    protected int $maxAttempts = 5;
    protected int $lockoutSeconds = 900; // 15 minutes

    /**
     * Vérifie si l'utilisateur peut effectuer une opération sensible
     * @param string|null $pin PIN fourni pour l'opération
     */
    public function validateUserForOperation($pin = null)
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            return ['success' => false, 'message' => 'Utilisateur non authentifié', 'code' => 401];
        }

        if ($user->status === 'disabled') {
            return ['success' => false, 'message' => 'Compte désactivé', 'code' => 403];
        }

        if ($pin) {
            if ($user->failed_attempts >= $this->maxAttempts && $user->pin_locked_until && now()->lt($user->pin_locked_until)) {
                $secondsLeft = now()->diffInSeconds($user->pin_locked_until);
                return ['success' => false, 'message' => "PIN temporairement bloqué. Réessayez dans {$secondsLeft} secondes", 'code' => 429];
            }

            if (!Hash::check($pin, $user->pin)) {
                $user->failed_attempts++;
                if ($user->failed_attempts >= $this->maxAttempts) {
                    $user->status = 'disabled';
                    $user->pin_locked_until = now()->addSeconds($this->lockoutSeconds);
                }
                $user->save();

                $remaining = max(0, $this->maxAttempts - $user->failed_attempts);
                $message = $user->status === 'disabled'
                    ? "PIN incorrect. Compte désactivé temporairement"
                    : "PIN incorrect. Il vous reste {$remaining} tentative(s)";

                return ['success' => false, 'message' => $message, 'code' => 403];
            }

            // PIN correct → reset
            $user->failed_attempts = 0;
            $user->pin_locked_until = null;
            $user->save();
        }

        return ['success' => true, 'message' => 'Utilisateur autorisé', 'code' => 200];
    }
}
