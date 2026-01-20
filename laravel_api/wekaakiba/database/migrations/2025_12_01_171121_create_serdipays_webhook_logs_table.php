<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSerdipaysWebhookLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('serdipays_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('merchantCode')->nullable();
            $table->string('clientPhone')->nullable();
            $table->double('amount')->default(0);
            $table->string('currency', 10)->nullable();
            $table->string('telecom', 10)->nullable();
            $table->string('token')->unique();
            $table->string('sessionId')->unique();
            $table->integer('sessionStatus')->nullable();
            $table->string('transactionId')->unique();
            $table->string('wekatransactionId')->unique();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
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
        Schema::dropIfExists('serdipays_webhook_logs');
    }
}
