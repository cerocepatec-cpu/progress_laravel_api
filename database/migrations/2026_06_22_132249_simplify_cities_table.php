<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cities', 'country_id')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->unsignedBigInteger('country_id')->nullable()->after('name');
            });
        }

        $columnsToDrop = [
            'state_id',
            'state_code',
            'state_name',
            'country_code',
            'country_name',
            'latitude',
            'longitude',
            'wikiDataId',
        ];

        foreach ($columnsToDrop as $column) {
            if (Schema::hasColumn('cities', $column)) {
                Schema::table('cities', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        $primaryKey = DB::select("
    SELECT COUNT(*) as total
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cities'
    AND CONSTRAINT_TYPE = 'PRIMARY KEY'
");

        if (($primaryKey[0]->total ?? 0) == 0) {
            DB::statement('ALTER TABLE cities ADD PRIMARY KEY (id)');
        }

        DB::statement("
    ALTER TABLE cities
    MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
");

        $primaryKey = DB::select("
            SELECT COUNT(*) as total
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'cities'
            AND CONSTRAINT_TYPE = 'PRIMARY KEY'
        ");

        if (($primaryKey[0]->total ?? 0) == 0) {
            DB::statement('ALTER TABLE cities ADD PRIMARY KEY (id)');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cities', 'country_id')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->dropColumn('country_id');
            });
        }

        DB::statement("
            ALTER TABLE cities
            MODIFY id BIGINT UNSIGNED NOT NULL
        ");

        Schema::table('cities', function (Blueprint $table) {
            if (! Schema::hasColumn('cities', 'state_id')) {
                $table->text('state_id')->nullable();
                $table->text('state_code')->nullable();
                $table->text('state_name')->nullable();
                $table->text('country_code')->nullable();
                $table->text('country_name')->nullable();
                $table->text('latitude')->nullable();
                $table->text('longitude')->nullable();
                $table->text('wikiDataId')->nullable();
            }
        });
    }
};
