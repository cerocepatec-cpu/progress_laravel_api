<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            if (!Schema::hasColumn('users', 'member_id')) {
                $table->text('member_id')->nullable()->after('member_code');
            }

            if (!Schema::hasColumn('users', 'lastname')) {
                $table->text('lastname')->nullable()->after('name');
            }

            if (!Schema::hasColumn('users', 'pseudo')) {
                $table->text('pseudo')->nullable()->after('lastname');
            }

            if (!Schema::hasColumn('users', 'telephone')) {
                $table->text('telephone')->nullable()->after('pseudo');
            }

            if (!Schema::hasColumn('users', 'gender')) {
                $table->text('gender')->nullable()->after('email');
            }

            if (!Schema::hasColumn('users', 'date')) {
                $table->dateTime('date')->nullable()->after('password');
            }

            if (!Schema::hasColumn('users', 'categorie_id')) {
                $table->integer('categorie_id')->nullable()->after('date');
            }

            if (!Schema::hasColumn('users', 'parent_code')) {
                $table->bigInteger('parent_code')->nullable()->after('categorie_id');
            }

            if (!Schema::hasColumn('users', 'sponsor_code')) {
                $table->bigInteger('sponsor_code')->nullable()->after('parent_code');
            }

            if (!Schema::hasColumn('users', 'e_mobile_number')) {
                $table->text('e_mobile_number')->nullable()->after('sponsor_code');
            }

            if (!Schema::hasColumn('users', 'bank_name')) {
                $table->text('bank_name')->nullable()->after('e_mobile_number');
            }

            if (!Schema::hasColumn('users', 'bank_account')) {
                $table->text('bank_account')->nullable()->after('bank_name');
            }

            if (!Schema::hasColumn('users', 'total_amount_e_wallet')) {
                $table->double('total_amount_e_wallet')->default(0)->after('bank_account');
            }

            if (!Schema::hasColumn('users', 'password_e_wallet')) {
                $table->text('password_e_wallet')->nullable()->after('total_amount_e_wallet');
            }

            if (!Schema::hasColumn('users', 'inscription_mode')) {
                $table->text('inscription_mode')->nullable()->after('password_e_wallet');
            }

            if (!Schema::hasColumn('users', 'member_statute')) {
                $table->text('member_statute')->nullable()->after('inscription_mode');
            }

            if (!Schema::hasColumn('users', 'actual_level')) {
                $table->integer('actual_level')->nullable()->after('member_statute');
            }

            if (!Schema::hasColumn('users', 'pdfpaquet')) {
                $table->double('pdfpaquet')->default(0)->after('actual_level');
            }

            if (!Schema::hasColumn('users', 'adress')) {
                $table->text('adress')->nullable()->after('pdfpaquet');
            }

            if (!Schema::hasColumn('users', 'city')) {
                $table->bigInteger('city')->nullable()->after('adress');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $columns = [
                'member_id',
                'lastname',
                'pseudo',
                'telephone',
                'gender',
                'date',
                'categorie_id',
                'parent_code',
                'sponsor_code',
                'e_mobile_number',
                'bank_name',
                'bank_account',
                'total_amount_e_wallet',
                'password_e_wallet',
                'inscription_mode',
                'member_statute',
                'actual_level',
                'pdfpaquet',
                'adress',
                'city',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};