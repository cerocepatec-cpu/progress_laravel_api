<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;
use App\Services\Preferences\PreferencesKwcService;

class UserPreferenceController extends Controller
{
    /**
     * GET /settings
     */
    public function show(Request $request,
        PreferencesKwcService $kwcService)
    {
        $user = $request->user();

        $preferences = $user->preferences()->firstOrCreate([]);

         $kwc = $kwcService->calculate($user);

        Redis::publish('user.preferences', json_encode([
            'type' => 'nbr-kwc',
            'data' => ['user_id' => $user->id,'kwc' => $kwc]
        ])); 
        
        Redis::publish('user.preferences', json_encode([
            'type' => 'list-data',
            'data' => ['user_id' => $user->id,'list' =>$preferences]
        ]));

        return $this->successResponse('success', $preferences);
    }


    public function invoke(
        Request $request,
        PreferencesKwcService $kwcService
    ) {
        $user = $request->user();

        $kwc = $kwcService->calculate($user);

        return $this->successResponse('success',$kwc);
    }

    /**
     * PUT /settings
     */
    public function update(Request $request,
        PreferencesKwcService $kwcService)
    {
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse("Utilisateur non authentifié.", 401);
        }

        $preferences = $user->preferences()->firstOrCreate([]);

        $data = $request->validate([
            'language' => 'nullable|string|in:fr,en',
            'visibility' => 'nullable|string|in:private,public',
            'dark_mode' => 'nullable|boolean',
            'push_enabled' => 'nullable|boolean',
            'email_notifications' => 'nullable|boolean',
            'sms_notifications' => 'nullable|boolean',
            'transaction_notifications' => 'nullable|boolean',
            'security_alerts' => 'nullable|boolean',
            'daily_report' => 'nullable|boolean',
            'weekly_report' => 'nullable|boolean',
            'monthly_report' => 'nullable|boolean',
            'reports_send_time' => 'nullable|date_format:H:i',
            'funds_reminder_start_time' => 'nullable|date_format:H:i',
            'funds_reminder_end_time' => 'nullable|date_format:H:i',
            'incident_reports' => 'nullable|boolean',
        ]);

        /**
         * 🌍 Timezone utilisateur
         */
        $timezone = $user->timezone ?? config('app.timezone');

        /**
         * 📊 Gestion intelligente de l’heure des rapports
         */
        $hasAnyReport =
            ($data['daily_report'] ?? false) ||
            ($data['weekly_report'] ?? false) ||
            ($data['monthly_report'] ?? false);

        if ($hasAnyReport && empty($data['reports_send_time'])) {

            // 🕕 Heure par défaut = 18:00 dans la timezone user
            $data['reports_send_time'] = Carbon::createFromTime(
                18,
                0,
                0,
                $timezone
            )->format('H:i');
        }

        /**
         * 🔔 Validation plage rappel dépôt / retrait
         */
        if (
            !empty($data['funds_reminder_start_time']) ||
            !empty($data['funds_reminder_end_time'])
        ) {
            if (
                empty($data['funds_reminder_start_time']) ||
                empty($data['funds_reminder_end_time'])
            ) {
                return $this->errorResponse(
                    "Veuillez définir une plage horaire complète pour les rappels.",
                    422
                );
            }

            if ($data['funds_reminder_start_time'] >= $data['funds_reminder_end_time']) {
                return $this->errorResponse(
                    "L'heure de début doit être inférieure à l'heure de fin.",
                    422
                );
            }
        }

        // 💾 Mise à jour finale
        $preferences->update($data);

        
         $kwc = $kwcService->calculate($user);

        Redis::publish('user.preferences', json_encode([ 
            'type' => 'nbr-kwc',
            'data' => ['user_id' => $user->id,'kwc' => $kwc]
        ])); 

        Redis::publish('user.preferences', json_encode([
            'type' => 'list-data',
            'data' => ['user_id' => $user->id,'list' =>$preferences]
        ]));

        return $this->successResponse('success', $preferences);
    }
}
