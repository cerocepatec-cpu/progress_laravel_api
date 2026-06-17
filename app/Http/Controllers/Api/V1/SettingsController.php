<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends ApiController
{
    public function majPoints()
    {
        return $this->ok(DB::table('majpointsettings')->first());
    }

    public function updateMajPoints(Request $request)
    {
        $data = $request->validate([
            'pointvalue' => ['required', 'numeric'],
            'status' => ['nullable', 'string'],
        ]);

        $this->upsertSingleRowConfig(
            'majpointsettings',
            [
                'pointvalue' => $data['pointvalue'],
                'status' => $data['status'] ?? null,
            ],
            ['id' => 1, 'status' => 'available']
        );

        return $this->ok(null, 'Configuration MAJ mise a jour.');
    }

    public function adhesionPoints()
    {
        return $this->ok(DB::table('adhesionpointsettings')->first());
    }

    public function updateAdhesionPoints(Request $request)
    {
        $data = $request->validate([
            'pointvalue' => ['required', 'numeric'],
            'status' => ['nullable', 'string'],
        ]);

        $this->upsertSingleRowConfig(
            'adhesionpointsettings',
            [
                'pointvalue' => $data['pointvalue'],
                'status' => $data['status'] ?? null,
            ],
            ['id' => 1, 'status' => 'available']
        );

        return $this->ok(null, 'Configuration des points d adhesion mise a jour.');
    }

    public function inscriptionCost()
    {
        return $this->ok(DB::table('inscription_cost')->first());
    }

    public function updateInscriptionCost(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric'],
        ]);

        $this->upsertSingleRowConfig(
            'inscription_cost',
            ['amount' => $data['amount']],
            []
        );

        return $this->ok(null, 'Montant d inscription mis a jour.');
    }

    public function validationExpiration()
    {
        return $this->ok(DB::table('validation_member_expiration')->first());
    }

    public function updateValidationExpiration(Request $request)
    {
        $data = $request->validate([
            'days_fixed' => ['required', 'integer', 'min:0'],
        ]);

        $this->upsertSingleRowConfig(
            'validation_member_expiration',
            ['days_fixed' => $data['days_fixed']],
            []
        );

        return $this->ok(null, 'Delai de validation membre mis a jour.');
    }

    public function periodicMaj()
    {
        return $this->ok(
            DB::table('periodicmajsettings as p')
                ->leftJoin('cities as c', 'p.citie_id', '=', 'c.id')
                ->select('p.*', 'c.name as city_name', 'c.country_name')
                ->orderByDesc('p.id')
                ->get()
        );
    }

    public function storePeriodicMaj(Request $request)
    {
        $data = $request->validate([
            'citie_id' => ['required', 'integer'],
            'begin_at' => ['required', 'date'],
            'end_at' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $id = (int) ((DB::table('periodicmajsettings')->max('id') ?? 0) + 1);
        DB::table('periodicmajsettings')->insert([
            'id' => $id,
            'citie_id' => $data['citie_id'],
            'begin_at' => $data['begin_at'],
            'end_at' => $data['end_at'],
            'description' => $data['description'] ?? null,
            'added_by' => auth()->user()?->member_id,
            'created_at' => now(),
            'updated_at' => now(),
            'status' => 'available',
        ]);

        return $this->ok(['id' => $id], 'Periode MAJ creee.', 201);
    }

    private function upsertSingleRowConfig(string $table, array $attributes, array $insertDefaults): void
    {
        $current = DB::table($table)->first();
        $payload = $attributes;

        foreach ($payload as $key => $value) {
            if ($value === null) {
                $payload[$key] = $current->{$key} ?? $insertDefaults[$key] ?? null;
            }
        }

        if ($current) {
            DB::table($table)->update($payload);

            return;
        }

        DB::table($table)->insert(array_merge($insertDefaults, $payload));
    }
}
