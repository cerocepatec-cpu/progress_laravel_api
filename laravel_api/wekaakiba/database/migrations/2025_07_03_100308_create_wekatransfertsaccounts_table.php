<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWekatransfertsaccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wekatransfertsaccounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('enterprise')->nullable();
            $table->unsignedBigInteger('done_by')->nullable();
            $table->unsignedBigInteger('validated_by')->nullable();

            $table->unsignedBigInteger('source');
            $table->unsignedBigInteger('destination');
            
            $table->unsignedBigInteger('source_currency_id')->nullable();
            $table->unsignedBigInteger('destination_currency_id')->nullable();

           
            $table->decimal('original_amount', 15, 2);
            $table->decimal('converted_amount', 15, 2);
            $table->decimal('conversion_rate', 15, 6)->nullable();

            $table->string('pin')->nullable();
            $table->string('motif')->nullable();
            $table->enum('transfert_status', ['pending', 'validated', 'denied', 'failed'])->default('pending');

            $table->timestamps();

            $table->foreign('enterprise')->references('id')->on('enterprises')->nullOnDelete();
            $table->foreign('done_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('validated_by')->references('id')->on('users')->nullOnDelete();

            $table->foreign('source')->references('id')->on('wekamemberaccounts')->cascadeOnDelete();
            $table->foreign('destination')->references('id')->on('wekamemberaccounts')->cascadeOnDelete();

            $table->foreign('source_currency_id')->references('id')->on('moneys')->nullOnDelete();
            $table->foreign('destination_currency_id')->references('id')->on('moneys')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wekatransfertsaccounts');
    }
}
