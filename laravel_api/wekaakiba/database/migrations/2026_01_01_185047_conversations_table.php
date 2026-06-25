<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ConversationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
       Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['private', 'group', 'system']);
            $table->string('title')->nullable();
            $table->enum('status', [
                'active',
                'archived',
                'locked',
                'suspended',
                'closed',
            ])->default('active');
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('conversations');
    }
}
