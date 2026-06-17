<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'adhesionpointsettings',
        'ambassador',
        'ambassador_crowned',
        'builder',
        'cash_operations',
        'categories',
        'categoriesproducts',
        'cities',
        'coffrets_vip',
        'countries',
        'depositproducts',
        'deposits',
        'depositusers',
        'diamond',
        'diamond_crowned',
        'emerald',
        'entries_accountancy',
        'entry_account_member',
        'inscription_cost',
        'invoices',
        'invoice_details',
        'isb_account',
        'last_sign_on',
        'majpointsettings',
        'members',
        'notifications',
        'periodicmajsettings',
        'permutation_mouvements',
        'products',
        'ruby',
        'sapphire',
        'stockmouvements',
        'stocktransferts',
        'transactions_accounts',
        'uoms',
        'validation_member_expiration',
        'wayout_accountancy',
        'wayout_account_member',
        'withdrawals',
    ];

    public function up(): void
    {
        if (Schema::hasTable('members') && Schema::hasTable('categories')) {
            return;
        }

        $dumpPath = base_path('progress_business (1).sql');

        if (! is_file($dumpPath)) {
            throw new RuntimeException('Dump SQL introuvable: '.$dumpPath);
        }

        $currentSqlMode = DB::scalar('SELECT @@SESSION.sql_mode');

        DB::unprepared("SET SESSION sql_mode = ''");

        try {
            $this->importSqlDump($dumpPath, ['migrations', 'personal_access_tokens']);
        } finally {
            $this->restoreSqlMode($currentSqlMode);
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (array_reverse($this->tables) as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    private function importSqlDump(string $path, array $excludedTables): void
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Impossible d ouvrir le dump SQL.');
        }

        $statement = '';

        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }

            $statement .= $line;

            if (! str_ends_with($trimmed, ';')) {
                continue;
            }

            $sql = trim($statement);
            $statement = '';

            if ($this->shouldSkipStatement($sql, $excludedTables)) {
                continue;
            }

            DB::unprepared($this->sanitizeStatement($sql));
        }

        fclose($handle);
    }

    private function shouldSkipStatement(string $sql, array $excludedTables): bool
    {
        $upperSql = strtoupper($sql);

        if (
            str_starts_with($upperSql, 'START TRANSACTION')
            || str_starts_with($upperSql, 'COMMIT')
            || str_starts_with($upperSql, 'LOCK TABLES')
            || str_starts_with($upperSql, 'UNLOCK TABLES')
            || str_starts_with($upperSql, 'SET ')
            || str_starts_with($upperSql, '/*!')
        ) {
            return true;
        }

        foreach ($excludedTables as $table) {
            if (str_contains($sql, '`'.$table.'`')) {
                return true;
            }
        }

        return false;
    }

    private function sanitizeStatement(string $sql): string
    {
        $sql = str_replace(
            ["'0000-00-00 00:00:00'", "'0000-00-00'"],
            'NULL',
            $sql
        );

        if (! str_starts_with(strtoupper($sql), 'CREATE TABLE')) {
            return $sql;
        }

        return preg_replace_callback(
            '/enum\(([^)]*)\)/i',
            fn (array $matches): string => $this->deduplicateEnumDefinition($matches[0], $matches[1]),
            $sql
        ) ?? $sql;
    }

    private function deduplicateEnumDefinition(string $fullMatch, string $valueBlock): string
    {
        if (! preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $valueBlock, $matches)) {
            return $fullMatch;
        }

        $uniqueValues = [];

        foreach ($matches[1] as $rawValue) {
            $value = stripcslashes($rawValue);

            if (! array_key_exists($value, $uniqueValues)) {
                $uniqueValues[$value] = true;
            }
        }

        $normalizedValues = array_map(
            static fn (string $value): string => "'".str_replace(
                ['\\', "'"],
                ['\\\\', "\\'"],
                $value
            )."'",
            array_keys($uniqueValues)
        );

        return 'enum('.implode(',', $normalizedValues).')';
    }

    private function restoreSqlMode(mixed $sqlMode): void
    {
        if ($sqlMode === null) {
            DB::unprepared('SET SESSION sql_mode = DEFAULT');

            return;
        }

        DB::unprepared(
            "SET SESSION sql_mode = '".str_replace("'", "\\'", (string) $sqlMode)."'"
        );
    }
};
