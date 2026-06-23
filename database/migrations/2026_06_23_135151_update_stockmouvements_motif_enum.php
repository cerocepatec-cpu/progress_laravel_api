<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE stockmouvements
            MODIFY motif ENUM(
                'internal',
                'transfert',
                'used',
                'sold',
                'products',
                'maj',
                'return',
                'adjustment',
                'inventory'
            ) NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE stockmouvements
            MODIFY motif ENUM(
                'internal',
                'transfert',
                'used',
                'sold'
            ) NULL
        ");
    }
};