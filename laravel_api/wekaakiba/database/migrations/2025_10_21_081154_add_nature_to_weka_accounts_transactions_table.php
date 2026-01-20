<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNatureToWekaAccountsTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('weka_accounts_transactions', function (Blueprint $table) {
            
            if (!Schema::hasColumn('weka_accounts_transactions', 'nature')) {
                $table->enum('nature', ['account_account', 'tub_account', 'mobile_money_account','cash_virtual'])
                    ->default('cash_virtual');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('weka_accounts_transactions', function (Blueprint $table) {
             $table->dropColumn('nature');
        });
    }
}
