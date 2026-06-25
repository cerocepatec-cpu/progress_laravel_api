<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $excludedTables = [
        'migrations',
        'personal_access_tokens',
        'password_reset_tokens',
        'failed_jobs',
        'jobs',
        'job_batches',
        'cache',
        'cache_locks',
        'sessions',
    ];

    public function up(): void
    {
        $database = DB::getDatabaseName();

        $tables = DB::select("
            SELECT TABLE_NAME
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ?
              AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME
        ", [$database]);

        foreach ($tables as $row) {
            $table = $row->TABLE_NAME;

            if (in_array($table, $this->excludedTables, true)) {
                continue;
            }

            $autoIncrementColumn = DB::selectOne("
                SELECT COLUMN_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME = ?
                  AND EXTRA LIKE '%auto_increment%'
                LIMIT 1
            ", [$database, $table]);

            if ($autoIncrementColumn && $autoIncrementColumn->COLUMN_NAME !== 'id') {
                continue;
            }

            if (! Schema::hasColumn($table, 'id')) {
                DB::statement("
                    ALTER TABLE `$table`
                    ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST
                ");

                continue;
            }

            $primaryKey = DB::selectOne("
                SELECT COUNT(*) AS total
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME = ?
                  AND CONSTRAINT_TYPE = 'PRIMARY KEY'
            ", [$database, $table]);

            if (($primaryKey->total ?? 0) === 0) {
                // ÉTAPE DE SÉCURITÉ AUTOMATIQUE : Éliminer les doublons d'IDs (ex: l'id '1' de adhesionpointsettings)
                $this->repairDuplicateIdsInTable($table);

                DB::statement("
                    ALTER TABLE `$table`
                    ADD PRIMARY KEY (`id`)
                ");
            }

            $column = DB::selectOne("
                SELECT EXTRA
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = 'id'
            ", [$database, $table]);

            if (! str_contains(strtolower($column->EXTRA ?? ''), 'auto_increment')) {
                DB::statement("
                    ALTER TABLE `$table`
                    MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
                ");
            }
        }
    }

    public function down(): void
    {
        //
    }

    /**
     * Assigne un identifiant incrémental séquentiel unique à chaque ligne de la table
     * pour éliminer les conflits de doublons avant la création de la clé primaire.
     */
    private function repairDuplicateIdsInTable(string $table): void
    {
        DB::statement("SET @count = 0;");
        DB::statement("UPDATE `{$table}` SET `id` = (@count:=@count+1);");
    }
};
