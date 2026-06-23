<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $primaryKey = DB::select("
            SELECT COUNT(*) as total
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'transactions_accounts'
            AND CONSTRAINT_TYPE = 'PRIMARY KEY'
        ");

        if (($primaryKey[0]->total ?? 0) == 0) {
            DB::statement("
                ALTER TABLE transactions_accounts
                ADD PRIMARY KEY (id)
            ");
        }

        DB::statement("
            ALTER TABLE transactions_accounts
            MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE transactions_accounts
            MODIFY id BIGINT UNSIGNED NOT NULL
        ");

        try {
            DB::statement("
                ALTER TABLE transactions_accounts
                DROP PRIMARY KEY
            ");
        } catch (\Throwable $e) {
        }
    }
};