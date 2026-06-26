<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('members')
            ->orderBy('id')
            ->chunkById(500, function ($members) {
                $now = now();

                $existingMemberCodes = DB::table('users')
                    ->whereIn('member_code', $members->pluck('member_code')->filter()->values())
                    ->pluck('member_code')
                    ->toArray();

                $rows = [];

                foreach ($members as $member) {
                    if ($member->member_code && in_array($member->member_code, $existingMemberCodes)) {
                        continue;
                    }

                    $rows[] = [
                        'member_code' => $member->member_code,
                        'member_id' => $member->member_id,
                        'name' => $member->name,
                        'lastname' => $member->lastname,
                        'pseudo' => $member->pseudo,
                        'telephone' => $member->telephone,
                        'username' => $member->username,
                        'email' => $member->email,
                        'gender' => $member->gender,

                        'password' => $this->hashValue($member->password),
                        'password_e_wallet' => $this->hashValue($member->password_e_wallet),

                        'date' => $member->date,
                        'categorie_id' => $member->categorie_id,
                        'parent_code' => $member->parent_code,
                        'sponsor_code' => $member->sponsor_code,
                        'e_mobile_number' => $member->e_mobile_number,
                        'bank_name' => $member->bank_name,
                        'bank_account' => $member->bank_account,
                        'total_amount_e_wallet' => $member->total_amount_e_wallet,
                        'inscription_mode' => $member->inscription_mode,
                        'member_statute' => $member->member_statute,
                        'actual_level' => $member->actual_level,
                        'pdfpaquet' => $member->pdfpaquet,
                        'adress' => $member->adress,
                        'city' => $member->city,

                        'email_verified_at' => null,
                        'remember_token' => null,
                        'last_connection' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($rows)) {
                    DB::table('users')->insertOrIgnore($rows);
                }
            }, 'id');
    }

    public function down(): void
    {
        // Sécurité : on ne supprime rien automatiquement.
        // Les données copiées dans users peuvent déjà être utilisées par le système.
    }

    private function hashValue(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (Hash::needsRehash($value)) {
            return Hash::make($value);
        }

        return $value;
    }
};