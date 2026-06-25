<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWithdrawRequestLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('withdraw_request_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('withdraw_request_id');
            $table->string('event')->default('workflow'); 
            $table->enum('actor_type', ['member', 'collector', 'system']);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); 
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('withdraw_request_id');
            $table->index('event');
            $table->foreign('withdraw_request_id')
                ->references('id')
                ->on('withdraw_requests')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('withdraw_request_logs');
    }
}
