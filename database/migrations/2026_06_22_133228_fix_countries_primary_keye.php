<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Vérifier si une clé primaire existe déjà
        $primaryKey = DB::select("
            SELECT COUNT(*) as total
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'countries'
            AND CONSTRAINT_TYPE = 'PRIMARY KEY'
        ");

        if (($primaryKey[0]->total ?? 0) == 0) {
            // ÉTAPE DE SÉCURITÉ : Nettoyer les IDs en doublon (ex: l'ID 168) avant de poser la clé primaire
            $this->repairCountryDuplicateIds();

            DB::statement("
                ALTER TABLE countries
                ADD PRIMARY KEY (id)
            ");
        }

        // Transformer id en AUTO_INCREMENT
        DB::statement("
            ALTER TABLE countries
            MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE countries
            MODIFY id BIGINT UNSIGNED NOT NULL
        ");

        try {
            DB::statement("
                ALTER TABLE countries
                DROP PRIMARY KEY
            ");
        } catch (\Throwable $e) {
        }
    }

    /**
     * Assigne un identifiant incrémental unique à chaque pays existant
     * pour éliminer les conflits de doublons d'id (comme le 168) avant la clé primaire.
     */
    private function repairCountryDuplicateIds(): void
    {
        DB::statement("SET @count = 0;");
        DB::statement("UPDATE `countries` SET `id` = (@count:=@count+1);");
    }
};
