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
};