<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSerdipaysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('serdipays', function (Blueprint $table) {
            $table->id();
            $table->string('token')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('password')->unique()->nullable();
            $table->string('merchantCode')->unique()->nullable();
            $table->double('c2b_fees')->default(0);
            $table->double('b2c_fees')->default(0);
            $table->double('b2b_fees')->default(0);
            $table->double('ussd_fees')->default(0);
            $table->double('additional_fees')->default(0);
            $table->double('withdraw_by_agent_fees')->default(0);
            $table->double('transfert_money_fees')->default(0);
            $table->bigInteger('enterprise_id')->unsigned();
            $table->foreign('enterprise_id')->references('id')->on('enterprises')->onDelete('cascade');
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
        Schema::dropIfExists('serdipays');
    }
}
