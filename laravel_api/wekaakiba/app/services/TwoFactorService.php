<?php

namespace App\Services;

use Throwable;
use App\Models\User;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;
use App\Models\TwoFactorRequest;
use Illuminate\Support\Facades\DB;
use App\Mail\TwoFactorMagicLinkMail;
use Illuminate\Support\Facades\Mail;

class TwoFactorService
{
        public static function initiate(User $user, string $challengeId)
        {
            return DB::transaction(function () use ($user, $challengeId) {

            $token = Str::uuid();
            $agent = new Agent();

                // 🔐 1️⃣ Création requête 2FA liée AU challenge
                $twoFa = TwoFactorRequest::create([
                    'user_id'      => $user->id,
                    'challenge_id' => $challengeId,
                    'token'        => $token,
                    'status'       => 'pending',
                    'ip_address'   => request()->ip(),
                    'browser'      => request()->userAgent(),
                    'device'       => $agent->device(),
                    'expires_at'   => now()->addMinutes(10),
                ]);

                try {
                    // 📧 2️⃣ Envoi email magique
                    Mail::to($user->email)
                        ->send(new TwoFactorMagicLinkMail($twoFa));

                    if (count(Mail::failures()) > 0) {
                        throw new \Exception('MAIL_NOT_SENT');
                    }

                } catch (\Throwable $e) {
                    // ❌ rollback automatique
                    throw $e;
                }

                // ✅ commit auto si OK
                return $twoFa;
            });
        }


    public static function test(User $user)
    {
        return DB::transaction(function () use ($user) {

            $token = Str::uuid();
            $agent = new Agent();

            // 1️⃣ Création 2FA (NON commitée tant que la transaction n’est pas validée)
            $twoFa = TwoFactorRequest::create([
                'user_id'    => $user->id,
                'token'      => $token,
                'ip_address' => request()->ip(),
                'browser'    => $agent->browser(),
                'device'     => $agent->device(),
                'expires_at' => now()->addMinutes(10),
            ]);

            try {
                // 2️⃣ Envoi email
                Mail::to($user->email)
                    ->send(new TwoFactorMagicLinkMail($twoFa));

                // 3️⃣ Vérification échec silencieux
                if (count(Mail::failures()) > 0) {
                    throw new \Exception('MAIL_NOT_SENT');
                }

            } catch (Throwable $e) {
                // 🔥 Exception = rollback automatique
                throw $e;
            }

            // 4️⃣ OK → commit automatique
            return response()->json([
                'message' => '2FA_REQUIRED',
                'token'   => app()->environment('local') ? $token : null,
            ], 403);
        });
    }

}
