<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_histories', function (Blueprint $table) {
            // Ajout des nouvelles colonnes seulement si elles n’existent pas déjà

            if (!Schema::hasColumn('request_histories', 'fund_receiver_id')) {
                $table->foreignId('fund_receiver_id')
                    ->nullable()
                    ->constrained('funds')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('request_histories', 'expenditure_id')) {
                $table->foreignId('expenditure_id')
                    ->nullable()
                    ->constrained('expenditures')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('request_histories', 'member_account_id')) {
                $table->foreignId('member_account_id')
                    ->nullable()
                    ->constrained('wekamemberaccounts')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('request_histories', 'nature')) {
                $table->enum('nature', ['transfert', 'approvment', 'expenditure', 'other'])
                    ->default('other');
            }
        });
    }

    public function down(): void
    {
        Schema::table('request_histories', function (Blueprint $table) {
            // Suppression sécurisée des colonnes si rollback
            $table->dropConstrainedForeignIdIfExists('fund_receiver_id');
            $table->dropConstrainedForeignIdIfExists('expenditure_id');
            $table->dropConstrainedForeignIdIfExists('member_account_id');
            $table->dropColumn('nature');
        });
    }
};
