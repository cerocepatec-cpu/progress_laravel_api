<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ConversationParticipantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('conversation_participants', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('conversation_id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->enum('role', ['admin', 'member'])->default('member');
                $table->timestamp('last_read_at')->nullable();
                $table->boolean('notifications_enabled')->default(true);
                $table->boolean('muted')->default(false);
                $table->timestamp('muted_until')->nullable();
                $table->boolean('pinned')->default(false);
                $table->timestamp('pinned_at')->nullable();
                $table->boolean('archived')->default(false);
                $table->timestamp('archived_at')->nullable();
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('left_at')->nullable();
                $table->unique(['conversation_id', 'user_id']);
                $table->index(['user_id', 'archived', 'pinned']);
                $table->index('last_read_at');
            });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('conversation_participants');
    }
}
