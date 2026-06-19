<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
        'deposits',
        'depositusers',
        'depositproducts',
        'stockmouvements',
        'stocktransferts',
        'uoms',
        'categoriesproducts',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! $this->tableExists($table)) {
                continue;
            }

            if (! $this->hasPrimaryKey($table)) {
                DB::statement("ALTER TABLE `{$table}` ADD PRIMARY KEY (`id`)");
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT");
        }

        $this->addIndexIfMissing('depositusers', 'idx_depositusers_deposit_user', 'ALTER TABLE `depositusers` ADD INDEX `idx_depositusers_deposit_user` (`deposit_id`, `user_id`(64))');
        $this->addIndexIfMissing('depositproducts', 'idx_depositproducts_deposit_product', 'ALTER TABLE `depositproducts` ADD INDEX `idx_depositproducts_deposit_product` (`deposit_id`, `product_id`)');
        $this->addIndexIfMissing('stockmouvements', 'idx_stockmouvements_deposit_product', 'ALTER TABLE `stockmouvements` ADD INDEX `idx_stockmouvements_deposit_product` (`deposit_id`, `product_id`)');
        $this->addIndexIfMissing('stocktransferts', 'idx_stocktransferts_sender_receiver', 'ALTER TABLE `stocktransferts` ADD INDEX `idx_stocktransferts_sender_receiver` (`deposit_sender`, `deposit_receiver`)');
        $this->addIndexIfMissing('uoms', 'idx_uoms_name', 'ALTER TABLE `uoms` ADD INDEX `idx_uoms_name` (`name`(120))');
        $this->addIndexIfMissing('categoriesproducts', 'idx_categoriesproducts_name', 'ALTER TABLE `categoriesproducts` ADD INDEX `idx_categoriesproducts_name` (`name`(120))');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('depositusers', 'idx_depositusers_deposit_user');
        $this->dropIndexIfExists('depositproducts', 'idx_depositproducts_deposit_product');
        $this->dropIndexIfExists('stockmouvements', 'idx_stockmouvements_deposit_product');
        $this->dropIndexIfExists('stocktransferts', 'idx_stocktransferts_sender_receiver');
        $this->dropIndexIfExists('uoms', 'idx_uoms_name');
        $this->dropIndexIfExists('categoriesproducts', 'idx_categoriesproducts_name');

        foreach (array_reverse($this->tables) as $table) {
            if (! $this->tableExists($table)) {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `id` INT(11) NOT NULL");

            if ($this->hasPrimaryKey($table)) {
                DB::statement("ALTER TABLE `{$table}` DROP PRIMARY KEY");
            }
        }
    }

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

    private function indexExists(string $table, string $index): bool
    {
        $result = DB::selectOne(
            "SELECT COUNT(1) AS total
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?",
            [$table, $index]
        );

        return (int) ($result->total ?? 0) > 0;
    }

    private function addIndexIfMissing(string $table, string $index, string $sql): void
    {
        if ($this->tableExists($table) && ! $this->indexExists($table, $index)) {
            DB::statement($sql);
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->tableExists($table) && $this->indexExists($table, $index)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }
};