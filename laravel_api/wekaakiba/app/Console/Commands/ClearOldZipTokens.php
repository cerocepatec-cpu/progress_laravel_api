<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearOldZipTokens extends Command
{
    protected $signature = 'zip:clear-tokens';
    protected $description = 'Supprime les tokens ZIP expirés (utile si cache driver = file)';

    public function handle()
    {
        $this->info("Nettoyage des tokens ZIP...");

        // Ce bloc fonctionne uniquement si le driver cache le permet (ex: file, array)
        // foreach (Cache::getStore()->getIterator() as $key => $value) {
        //     if (str_contains($key, 'zip_token:')) {
        //         Cache::forget($key);
        //         $this->line("Clé supprimée : $key");
        //     }
        // }

        // $this->info("Nettoyage terminé.");
    }
}

