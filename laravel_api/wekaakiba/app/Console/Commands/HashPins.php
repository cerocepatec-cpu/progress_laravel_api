<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class HashPins extends Command
{
    protected $signature = 'users:hash-pins';
    protected $description = 'Hache tous les PIN des utilisateurs';

    public function handle()
    {
        $users = User::all();
        $count = 0;

        foreach ($users as $user) {
            // Vérifie si le PIN n'est pas vide et n'est pas déjà haché
            if (!empty($user->pin) && !Hash::needsRehash($user->pin)) {
                $user->pin = Hash::make($user->pin);
                $user->save();
                $count++;
            }
        }

        $this->info("Hachage terminé : $count PIN(s) mis à jour.");
    }
}
