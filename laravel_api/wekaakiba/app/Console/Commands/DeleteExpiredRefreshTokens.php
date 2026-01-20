<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RefreshToken;
use Carbon\Carbon;

class DeleteExpiredRefreshTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refresh-tokens:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Supprime tous les refresh tokens déjà expirés';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = RefreshToken::where('expires_at', '<', Carbon::now())->delete();

        $this->info("✅ $count refresh token(s) expiré(s) supprimé(s).");
    }
}
