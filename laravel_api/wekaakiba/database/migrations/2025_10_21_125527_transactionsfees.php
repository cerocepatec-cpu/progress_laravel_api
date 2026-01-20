<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_fees', function (Blueprint $table) {
            $table->id();

            // 🔗 Entreprise (obligatoire)
            $table->foreignId('enterprise_id')
                ->constrained('enterprises')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // 🔗 Utilisateur qui a défini le tarif
            $table->foreignId('setby')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // 🔗 Devise (1 = FC, 2 = USD)
            $table->foreignId('money_id')
                ->constrained('moneys')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // 💵 Tranches et taux
            $table->decimal('min_amount', 15, 2);
            $table->decimal('max_amount', 15, 2)->nullable(); // null = illimité
            $table->decimal('withdraw_percent', 5, 2);
            $table->decimal('send_percent', 5, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_fees');
    }
};
