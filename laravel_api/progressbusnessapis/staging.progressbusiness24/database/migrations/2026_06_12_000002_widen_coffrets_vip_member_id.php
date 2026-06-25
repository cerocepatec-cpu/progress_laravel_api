<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coffrets_vip')) {
            return;
        }

        DB::statement('ALTER TABLE coffrets_vip MODIFY member_id BIGINT NOT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('coffrets_vip')) {
            return;
        }

        DB::statement('UPDATE coffrets_vip SET member_id = CASE WHEN member_id > 2147483647 THEN 2147483647 ELSE member_id END');
        DB::statement('ALTER TABLE coffrets_vip MODIFY member_id INT NOT NULL');
    }
};
