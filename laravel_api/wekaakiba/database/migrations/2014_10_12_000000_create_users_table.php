<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('user_name');
            $table->string('full_name')->nullable();
            $table->string('user_mail')->nullable();
            $table->timestamp('email_verified_at');
            $table->string('user_phone')->nullable();
            $table->string('user_password');
            $table->string('user_type');
            $table->string('status');
            $table->string('note')->nullable();
            $table->string('avatar')->nullable();
            $table->string('pin')->default('0000')->max(4);
            $table->boolean('collector')->default(false);
            $table->integer('sponsored_by')->nullable();
            $table->double('collection_percentage')->nullable();
            $table->boolean('mobile_access')->default(false);
            $table->boolean('can_withdraw_on_mobile')->default(false);
            $table->boolean('can_withdraw_by_agent')->default(false);
            $table->integer('failed_attempts')->default(0);
            $table->rememberToken();
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
        Schema::dropIfExists('users');
    }
}
