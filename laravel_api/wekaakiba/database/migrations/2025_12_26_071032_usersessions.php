<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->enum('device_type', ['web', 'mobile']);
            $table->string('device_name', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedBigInteger('access_token_id')->nullable();
            $table->foreign('access_token_id')
                ->references('id')
                ->on('personal_access_tokens')
                ->nullOnDelete();
            $table->enum('status', ['active', 'revoked', 'suspicious'])
                ->default('active');
             $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['device_type']);
            $table->index(['access_token_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
