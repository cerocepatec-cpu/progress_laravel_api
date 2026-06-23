<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cities', 'wikiDatald')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->dropColumn('wikiDatald');
            });
        }

        if (Schema::hasColumn('cities', 'wikiDataId')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->dropColumn('wikiDataId');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('cities', 'wikiDatald')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->text('wikiDatald')->nullable();
            });
        }
    }
};