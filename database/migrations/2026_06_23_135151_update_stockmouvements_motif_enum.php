<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ÉTAPE DE SÉCURITÉ : Arrêter l'exécution si la table n'existe pas
        if (! $this->tableExists('stockmouvements')) {
            return;
        }

        DB::statement("
            ALTER TABLE stockmouvements
            MODIFY motif ENUM(
                'internal',
                'transfert',
                'used',
                'sold',
                'products',
                'maj',
                'return',
                'adjustment',
                'inventory'
            ) NULL
        ");
    }

    public function down(): void
    {
        if (! $this->tableExists('stockmouvements')) {
            return;
        }

        DB::statement("
            ALTER TABLE stockmouvements
            MODIFY motif ENUM(
                'internal',
                'transfert',
                'used',
                'sold'
            ) NULL
        ");
    }

    /**
     * Vérifie si la table existe dans la base de données actuelle.
     */
    private function tableExists(string $table): bool
    {
        $result = DB::selectOne(
            "SELECT COUNT(1) AS total
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?",
            [$table]
        );

        return (int) ($result->total ?? 0) > 0;
    }
};
