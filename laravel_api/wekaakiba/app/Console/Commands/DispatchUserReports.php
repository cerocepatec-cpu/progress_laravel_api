<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Jobs\SendUserReportJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DispatchUserReports extends Command
{
    protected $signature = 'reports:dispatch';
    protected $description = 'Dispatch user reports based on timezone and preferences';

    public function handle(): int
    {
        $nowUtc = Carbon::now('UTC');

        Log::info('REPORTS DISPATCH START', [
            'utc_now' => $nowUtc->format('Y-m-d H:i:s'),
        ]);

        User::with('preferences')
            ->whereHas('preferences', function ($q) {
                $q->where(function ($q) {
                    $q->where('daily_report', true)
                      ->orWhere('weekly_report', true)
                      ->orWhere('monthly_report', true);
                })
                ->whereNotNull('reports_send_time');
            })
            ->chunkById(500, function ($users) use ($nowUtc) {

                Log::info('REPORTS DISPATCH CHUNK', [
                    'users_count' => $users->count(),
                ]);

                foreach ($users as $user) {

                    if (!$user->preferences) {
                        Log::warning('REPORT SKIPPED: no preferences', [
                            'user_id' => $user->id,
                        ]);
                        continue;
                    }

                    // 🕒 Heure locale utilisateur
                    $localNow = $nowUtc->copy()->setTimezone(
                        $user->timezone ?? config('app.timezone')
                    );

                    Log::info('REPORT CHECK TIME', [
                        'user_id' => $user->id,
                        'timezone' => $user->timezone,
                        'local_time' => $localNow->format('H:i'),
                        'expected_time' => $user->preferences->reports_send_time,
                    ]);

                    $expectedTime = $user->preferences->reports_send_time instanceof \Carbon\Carbon
    ? $user->preferences->reports_send_time->format('H:i')
    : substr((string) $user->preferences->reports_send_time, 0, 5);
                    // ⏰ Vérifier heure d’envoi
                    if ($localNow->format('H:i') !==$expectedTime) {
                        Log::info('REPORT SKIPPED: time mismatch', [
                            'user_id' => $user->id,
                        ]);
                        continue;
                    }

                    // 📊 Déterminer le type de rapport
                    $reportType = $this->resolveReportType($user, $localNow);

                    if (!$reportType) {
                        Log::info('REPORT SKIPPED: no report type today', [
                            'user_id' => $user->id,
                        ]);
                        continue;
                    }

                    Log::info('REPORT DISPATCHED', [
                        'user_id' => $user->id,
                        'report_type' => $reportType,
                    ]);

                    // 🚀 Dispatch du job
                    SendUserReportJob::dispatch(
                        $user->id,
                        $reportType
                    );
                }
            });

        Log::info('REPORTS DISPATCH END');

        return Command::SUCCESS;
    }

    private function resolveReportType($user, Carbon $localNow): ?string
    {
        $prefs = $user->preferences;

        if ($prefs->monthly_report && $localNow->isSameDay($localNow->copy()->startOfMonth())) {
            return 'monthly';
        }

        if ($prefs->weekly_report && $localNow->isMonday()) {
            return 'weekly';
        }

        if ($prefs->daily_report) {
            return 'daily';
        }

        return null;
    }
}
