<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequestHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('request_histories', function (Blueprint $table) {
            $table->id();
            $table->double('amount');
            $table->string('type');
            $table->string('status')->default('pending');
            $table->string('motif')->nullable();
            $table->integer('request_id')->nullable();
            $table->integer('invoice_id')->nullable();
            $table->integer('fence_id')->nullable();
            $table->bigInteger('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->bigInteger('fund_id')->unsigned();
            $table->foreign('fund_id')->references('id')->on('funds')->onDelete('cascade');
            $table->bigInteger('enterprise_id')->unsigned();
            $table->foreign('enterprise_id')->references('id')->on('enterprises')->onDelete('cascade');
            $table->double('sold')->nullable();
            $table->date('done_at')->nullable();
            $table->integer('account_id')->nullable();
            $table->integer('notebook_id')->nullable();
            $table->string('beneficiary')->nullable();
            $table->string('provenance')->nullable();
            $table->string('uuid')->nullable();
            $table->foreignId('fund_receiver_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->foreignId('expenditure_id')->nullable()->constrained('expenditures')->nullOnDelete();
            $table->foreignId('member_account_id')->nullable()->constrained('wekamemberaccounts')->nullOnDelete();
            $table->enum('nature', ['transfert', 'approvment', 'expenditure', 'other'])->default('other');
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
        Schema::dropIfExists('request_histories');
    }
}
