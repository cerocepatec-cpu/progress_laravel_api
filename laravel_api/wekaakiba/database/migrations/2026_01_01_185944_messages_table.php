<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users');
            $table->enum('type', ['text', 'system', 'media'])->default('text');
            $table->text('content');
            $table->enum('status', [
            'pending',
            'sent',
            'delivered',
            'seen',
            'failed',
            'deleted',
            ])->default('sent');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->uuid('client_uuid')->nullable(); // déduplication mobile
            $table->json('meta')->nullable(); // reactions, replies, edits
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
            $table->index('sender_id');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('messages');
    }
}
