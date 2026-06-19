<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->hasPrimaryKey('products')) {
            DB::statement('ALTER TABLE products ADD PRIMARY KEY (id)');
        }

        DB::statement('ALTER TABLE products MODIFY id INT(11) NOT NULL AUTO_INCREMENT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE products MODIFY id INT(11) NOT NULL');

        if ($this->hasPrimaryKey('products')) {
            DB::statement('ALTER TABLE products DROP PRIMARY KEY');
        }
    }

    private function hasPrimaryKey(string $table): bool
    {
        $result = DB::selectOne(
            "SELECT COUNT(1) AS total
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = 'PRIMARY'",
            [$table]
        );

        return (int) ($result->total ?? 0) > 0;
    }
};
