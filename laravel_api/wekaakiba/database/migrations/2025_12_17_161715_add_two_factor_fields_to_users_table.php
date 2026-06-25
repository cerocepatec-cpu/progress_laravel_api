<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // 🔐 2FA activé ou non
            $table->boolean('two_factor_enabled')
                  ->default(false)
                  ->after('password');

            // 📡 Canal 2FA : email | sms (prévu pour extension future)
            $table->enum('two_factor_channel', ['email', 'sms'])
                  ->nullable()
                  ->after('two_factor_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_enabled',
                'two_factor_channel',
            ]);
        });
    }
};
