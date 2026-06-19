<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('builder', 'idx_builder_member_id_status', 'ALTER TABLE builder ADD INDEX idx_builder_member_id_status (member_id(64), status)');

        $this->addIndexIfMissing('sapphire', 'idx_sapphire_member_code_status', 'ALTER TABLE sapphire ADD INDEX idx_sapphire_member_code_status (member_code, status)');
        $this->addIndexIfMissing('ruby', 'idx_ruby_member_code_status', 'ALTER TABLE ruby ADD INDEX idx_ruby_member_code_status (member_code, status)');
        $this->addIndexIfMissing('emerald', 'idx_emerald_member_code_status', 'ALTER TABLE emerald ADD INDEX idx_emerald_member_code_status (member_code, status)');
        $this->addIndexIfMissing('diamond', 'idx_diamond_member_code_status', 'ALTER TABLE diamond ADD INDEX idx_diamond_member_code_status (member_code, status)');
        $this->addIndexIfMissing('diamond_crowned', 'idx_diamond_crowned_member_code_status', 'ALTER TABLE diamond_crowned ADD INDEX idx_diamond_crowned_member_code_status (member_code, status)');
        $this->addIndexIfMissing('ambassador', 'idx_ambassador_member_code_status', 'ALTER TABLE ambassador ADD INDEX idx_ambassador_member_code_status (member_code, status)');
        $this->addIndexIfMissing('ambassador_crowned', 'idx_ambassador_crowned_member_code_status', 'ALTER TABLE ambassador_crowned ADD INDEX idx_ambassador_crowned_member_code_status (member_code, status)');

        $this->addIndexIfMissing('users', 'idx_users_member_id', 'ALTER TABLE users ADD INDEX idx_users_member_id (member_id(64))');
        $this->addIndexIfMissing('users', 'idx_users_member_code', 'ALTER TABLE users ADD INDEX idx_users_member_code (member_code)');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('builder', 'idx_builder_member_id_status');

        $this->dropIndexIfExists('sapphire', 'idx_sapphire_member_code_status');
        $this->dropIndexIfExists('ruby', 'idx_ruby_member_code_status');
        $this->dropIndexIfExists('emerald', 'idx_emerald_member_code_status');
        $this->dropIndexIfExists('diamond', 'idx_diamond_member_code_status');
        $this->dropIndexIfExists('diamond_crowned', 'idx_diamond_crowned_member_code_status');
        $this->dropIndexIfExists('ambassador', 'idx_ambassador_member_code_status');
        $this->dropIndexIfExists('ambassador_crowned', 'idx_ambassador_crowned_member_code_status');

        $this->dropIndexIfExists('users', 'idx_users_member_id');
        $this->dropIndexIfExists('users', 'idx_users_member_code');
    }

    private function addIndexIfMissing(string $table, string $index, string $sql): void
    {
        if (! $this->indexExists($table, $index)) {
            DB::statement($sql);
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            DB::statement("ALTER TABLE {$table} DROP INDEX {$index}");
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $result = DB::selectOne(
            "SELECT COUNT(1) as total
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?",
            [$table, $index]
        );

        return (int) ($result->total ?? 0) > 0;
    }
};