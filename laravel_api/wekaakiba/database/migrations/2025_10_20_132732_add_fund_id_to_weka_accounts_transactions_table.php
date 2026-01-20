<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFundIdToWekaAccountsTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('weka_accounts_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('weka_accounts_transactions', 'fund_id')) {
                $table->foreignId('fund_id')
                    ->nullable()
                    ->constrained('funds')
                    ->nullOnDelete();
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
            $table->dropConstrainedForeignIdIfExists('fund_id');
        });
    }
}
