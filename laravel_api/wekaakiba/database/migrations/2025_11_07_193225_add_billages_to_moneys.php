<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBillagesToMoneys extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('moneys', function (Blueprint $table) {
            // On ajoute la colonne billages sous format JSON
            $table->json('billages')->nullable()->after('enterprise_id')
                  ->comment('Liste des billets disponibles pour cette monnaie (ex: [10000, 5000, 2000, 1000, 500, 100])');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moneys', function (Blueprint $table) {
            $table->dropColumn('billages');
        });
    }
}
