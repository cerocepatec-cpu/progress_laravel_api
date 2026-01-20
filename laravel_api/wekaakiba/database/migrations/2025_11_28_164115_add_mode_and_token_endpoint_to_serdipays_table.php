<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddModeAndTokenEndpointToSerdipaysTable extends Migration
{
    public function up()
    {
        Schema::table('serdipays', function (Blueprint $table) {
            $table->string('mode')->nullable()->after('merchantCode');
            $table->string('token_endpoint')->nullable()->after('mode');
        });
    }

    public function down()
    {
        Schema::table('serdipays', function (Blueprint $table) {
            $table->dropColumn(['mode', 'token_endpoint']);
        });
    }
}
