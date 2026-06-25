<?php

namespace App\Services\Preferences;

use App\Models\User;

class PreferencesKwcService
{
    /**
     * Calcule le pourcentage KWC pour un utilisateur
     */
    public function calculate(User $user): int
    {
        $preferences = $user->preferences()->firstOrCreate([]);

        $fields = [
            'language',
            'visibility',
            'dark_mode',
            'push_enabled',
            'email_notifications',
            'sms_notifications',
            'transaction_notifications',
            'security_alerts',
            'daily_report',
            'weekly_report',
            'monthly_report',
            'reports_send_time',
            'funds_reminder_start_time',
            'funds_reminder_end_time',
            'incident_reports',
        ];

        $total = count($fields);
        $completed = 0;

        foreach ($fields as $field) {
            if (!is_null($preferences->{$field})) {
                $completed++;
            }
        }

        return (int) round(($completed / $total) * 100);
    }
}
