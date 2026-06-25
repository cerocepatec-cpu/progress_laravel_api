<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWithdrawRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('withdraw_requests', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            /* ======================================================
             |  ACTEURS
             ======================================================*/
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('collector_id')->nullable();

            /* ======================================================
             |  COMPTE SOURCE (FIGÉ)
             ======================================================*/
            $table->unsignedBigInteger('member_account_id');

            /* ======================================================
             |  DONNÉES RETRAIT
             ======================================================*/
            $table->decimal('amount', 18, 2);
            $table->decimal('fees', 18, 2);
            $table->unsignedBigInteger('money_id');
            $table->enum('channel', ['cash', 'mobile_money']);

            /* ======================================================
             |  HISTORIQUE SOLDES
             ======================================================*/
            $table->decimal('sold_before', 18, 2);
            $table->decimal('sold_after', 18, 2)->nullable();

            /* ======================================================
             |  INFORMATIONS COMPLÉMENTAIRES
             ======================================================*/
            $table->text('description')->nullable();

            /* ======================================================
             |  LOCALISATION (CONSENTEMENT)
             ======================================================*/
            $table->boolean('share_location')->default(false);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            /* ======================================================
             |  DURÉE / VALIDITÉ
             ======================================================*/
            $table->enum('duration_type', [
                'duration',
                'time_range',
                'full_day'
            ])->default('duration');

            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            /* ======================================================
             |  STATUT WORKFLOW
             ======================================================*/
            $table->enum('status', [
                'pending',
                'taken',
                'validated',
                'completed',
                'cancelled',
                'expired'
            ])->default('pending');

            /* ======================================================
             |  TIMESTAMPS MÉTIER
             ======================================================*/
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            /* ======================================================
             |  INDEX
             ======================================================*/
            $table->index(['status', 'money_id']);
            $table->index('member_id');
            $table->index('collector_id');
            $table->index('member_account_id');

            /* ======================================================
             |  CLÉS ÉTRANGÈRES
             ======================================================*/
            $table->foreign('member_id')
                ->references('id')->on('users');

            $table->foreign('collector_id')
                ->references('id')->on('users');

            $table->foreign('member_account_id')
                ->references('id')->on('wekamemberaccounts')
                ->restrictOnDelete();

            $table->foreign('money_id')
                ->references('id')->on('moneys')
                ->restrictOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('withdraw_requests');
    }
}
