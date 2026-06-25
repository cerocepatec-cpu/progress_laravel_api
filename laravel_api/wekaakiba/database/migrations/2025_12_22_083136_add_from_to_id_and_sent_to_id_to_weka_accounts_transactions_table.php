<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('weka_accounts_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('from_to_id')->nullable()->after('id');
            $table->unsignedBigInteger('sent_to_id')->nullable()->after('from_to_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weka_accounts_transactions', function (Blueprint $table) {
            $table->dropColumn(['from_to_id', 'sent_to_id']);
        });
    }
};
