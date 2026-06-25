<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTwoFactorRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('two_factor_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // 🔗 Lien avec le login
            $table->string('challenge_id')->index();

            // 🔐 Sécurité
            $table->string('token')->unique();
            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending');

            // 🧾 Anti-replay
            $table->timestamp('consumed_at')->nullable();

            // 🖥️ Appareil & localisation
            $table->string('device')->nullable();
            $table->string('browser')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();

            // 🕵️ Audit validation
            $table->string('approved_ip')->nullable();
            $table->string('approved_user_agent')->nullable();

            // ⏱️ Timing
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            // 🔥 Index utiles
            $table->index(['user_id', 'challenge_id', 'status']);
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('two_factor_requests');
    }
}
