<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserSession;
use Carbon\Carbon;

class CleanupUserSessions extends Command
{
    protected $signature = 'sessions:cleanup';

    protected $description = 'Nettoie les sessions utilisateurs inactives (>35s)';

    public function handle()
    {
        $now = Carbon::now();

        // ⏱️ Seuil unique : 35 secondes
        $threshold = $now->copy()->subSeconds(35);

        $count = UserSession::where('status', 'active')
            ->where(function ($q) use ($threshold) {
                $q->where('last_seen_at', '<', $threshold)
                  ->orWhereNull('last_seen_at'); // sécurité
            })
            ->update([
                'status'     => 'revoked',
                'revoked_at' => $now,
            ]);

        $this->info("🧹 {$count} sessions nettoyées (inactives > 35s).");
    }
}
