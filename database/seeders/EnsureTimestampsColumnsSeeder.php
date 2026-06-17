<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnsureTimestampsColumnsSeeder extends Seeder
{
    public function run(): void
    {
        $tables = collect(DB::select('SHOW TABLES'))
            ->map(fn (object $row): string => array_values((array) $row)[0])
            ->reject(fn (string $table): bool => in_array($table, ['migrations'], true));

        foreach ($tables as $table) {
            if (! Schema::hasColumn($table, 'created_at')) {
                DB::statement("ALTER TABLE `{$table}` ADD `created_at` TIMESTAMP NULL DEFAULT NULL");
            }

            if (! Schema::hasColumn($table, 'updated_at')) {
                DB::statement("ALTER TABLE `{$table}` ADD `updated_at` TIMESTAMP NULL DEFAULT NULL");
            }
        }
    }
}
