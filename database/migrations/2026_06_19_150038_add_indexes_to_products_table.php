<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing(
            'products',
            'idx_products_name',
            'ALTER TABLE products ADD INDEX idx_products_name (name(120))'
        );

        $this->addIndexIfMissing(
    'products',
    'idx_products_status',
    'ALTER TABLE products ADD INDEX idx_products_status (status)'
);

        $this->addIndexIfMissing(
            'products',
            'idx_products_category_id',
            'ALTER TABLE products ADD INDEX idx_products_category_id (category_id)'
        );

        $this->addIndexIfMissing(
            'products',
            'idx_products_uom_id',
            'ALTER TABLE products ADD INDEX idx_products_uom_id (uom_id)'
        );

        $this->addIndexIfMissing(
            'products',
            'idx_products_added_by',
            'ALTER TABLE products ADD INDEX idx_products_added_by (added_by)'
        );

      $this->addIndexIfMissing(
    'products',
    'idx_products_status_id',
    'ALTER TABLE products ADD INDEX idx_products_status_id (status, id)'
);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('products', 'idx_products_name');
        $this->dropIndexIfExists('products', 'idx_products_status');
        $this->dropIndexIfExists('products', 'idx_products_category_id');
        $this->dropIndexIfExists('products', 'idx_products_uom_id');
        $this->dropIndexIfExists('products', 'idx_products_added_by');
        $this->dropIndexIfExists('products', 'idx_products_status_id');
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