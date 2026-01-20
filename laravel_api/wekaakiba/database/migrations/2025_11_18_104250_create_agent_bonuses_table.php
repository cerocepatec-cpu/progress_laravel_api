<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentBonusesTable extends Migration
{
    public function up()
    {
        Schema::create('agent_bonuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->string('transaction_type');
            $table->unsignedBigInteger('currency_id');
            $table->decimal('amount', 18, 2);

            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->dateTime('paid_at')->nullable();

            // nouvelle date : quand l’agent retire vraiment l’argent
            $table->dateTime('withdrawn_at')->nullable();

            $table->string('month_key', 7);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('agent_bonuses');
    }
}
