<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWithdrawRequestOtpsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::create('withdraw_request_otps', function (Blueprint $table) {
    $table->id();

    $table->unsignedBigInteger('withdraw_request_id');

    $table->enum('target', ['member', 'collector']);

    // OPTIONNEL mais valide
    $table->enum('sent_via', ['sms', 'email', 'push'])->nullable();

    $table->string('otp_hash');
    $table->timestamp('expires_at');
    $table->boolean('validated')->default(false);

    $table->timestamps();

    $table->unique(['withdraw_request_id', 'target']);

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
        Schema::dropIfExists('withdraw_request_otps');
    }
}
