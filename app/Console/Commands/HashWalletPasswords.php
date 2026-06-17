<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class HashWalletPasswords extends Command
{
    // Le nom de la commande à taper dans le terminal
    protected $signature = 'wallet:hash-passwords';
    protected $description = 'Hache les password_e_wallet en clair';

    public function handle()
    {
        // Récupère uniquement les champs non vides et non null
        $utilisateurs = DB::table('users') // Remplacez par le nom de votre table
            ->whereNotNull('password_e_wallet')
            ->where('password_e_wallet', '!=', '')
            ->get(['id', 'password_e_wallet']);

        $count = 0;

        foreach ($utilisateurs as $user) {
            // Hash::needsRehash vérifie automatiquement si la chaîne n'est pas déjà un hash valide
            if (Hash::needsRehash($user->password_e_wallet)) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['password_e_wallet' => Hash::make($user->password_e_wallet)]);
                
                $count++;
            }
        }

        $this->info("Opération réussie ! $count mots de passe ont été hachés.");
    }
}
