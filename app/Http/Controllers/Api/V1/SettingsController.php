<?php

namespace App\Http\Controllers\Api\V1;

use App\Mail\ContactUsMail;
use App\Models\ContactUs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

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

    public function steps()
    {
        $levels = [
            ['table' => 'builder', 'name' => 'Builder', 'level' => 1],
            ['table' => 'sapphire', 'name' => 'Sapphire', 'level' => 2],
            ['table' => 'ruby', 'name' => 'Ruby', 'level' => 3],
            ['table' => 'emerald', 'name' => 'Emerald', 'level' => 4],
            ['table' => 'diamond', 'name' => 'Diamond', 'level' => 5],
            ['table' => 'diamond_crowned', 'name' => 'Diamond Crowned', 'level' => 6],
            ['table' => 'ambassador', 'name' => 'Ambassador', 'level' => 7],
            ['table' => 'ambassador_crowned', 'name' => 'Ambassador Crowned', 'level' => 8],
        ];

        return $this->ok(collect($levels)->map(function (array $level): array {
            if (! Schema::hasTable($level['table'])) {
                return [
                    'id' => $level['table'],
                    'step_name' => $level['name'],
                    'table_name' => $level['table'],
                    'step_level' => $level['level'],
                    'members_count' => 0,
                    'paid_count' => 0,
                    'unpaid_count' => 0,
                    'status' => 'missing',
                ];
            }

            return [
                'id' => $level['table'],
                'step_name' => $level['name'],
                'table_name' => $level['table'],
                'step_level' => $level['level'],
                'members_count' => DB::table($level['table'])->count(),
                'paid_count' => DB::table($level['table'])->where('status', 'paid')->count(),
                'unpaid_count' => DB::table($level['table'])->where('status', 'unpaid')->count(),
                'status' => 'available',
            ];
        })->values());
    }

    public function storeStep(Request $request)
    {
        return $this->fail(
            'Les etapes sont derivees des tables builder, sapphire, ruby, emerald, diamond, diamond_crowned, ambassador et ambassador_crowned.',
            422
        );
    }

    public function storeObservation(Request $request)
    {
        $data = $request->validate([
            'contact_names'   => ['required', 'string', 'max:255'],
            'contact_email'   => ['nullable', 'email', 'max:255'],
            'contact_phone'   => ['nullable', 'string', 'max:50'],
            'business_type'   => ['nullable', 'string', 'max:100'],
            'contact_message' => ['required', 'string', 'max:5000'],
        ]);

        // Log::info('Configuration mail utilisée.', [
        //     'mailer'     => config('mail.default'),
        //     'host'       => config('mail.mailers.smtp.host'),
        //     'port'       => config('mail.mailers.smtp.port'),
        //     'username'   => config('mail.mailers.smtp.username'),
        //     'from'       => config('mail.from.address'),
        //     'receiver'   => config('mail.contact_receiver'),
        // ]);
        ContactUs::create([
            'contact_names'   => $data['contact_names'],
            'contact_email'   => $data['contact_email'] ?? null,
            'contact_phone'   => $data['contact_phone'] ?? null,
            'business_type'   => $data['business_type'] ?? null,
            'contact_message' => $data['contact_message'],
            'contact_date'    => now(),
        ]);

        // Log::info('Nouveau message de contact enregistré.', [
        //     'name'  => $data['contact_names'],
        //     'email' => $data['contact_email'] ?? null,
        //     'phone' => $data['contact_phone'] ?? null,
        // ]);

        // try {

        //     $receiver = config('mail.contact_receiver');

        //     Log::info('Tentative d\'envoi du mail.', [
        //         'to' => $receiver,
        //     ]);

        //     Mail::to($receiver)->send(new ContactUsMail($data));

        //     Log::info('Mail envoyé avec succès.', [
        //         'to' => $receiver,
        //     ]);
        // } catch (\Throwable $e) {

        //     Log::error('Erreur lors de l\'envoi du mail.', [
        //         'to'        => config('mail.contact_receiver'),
        //         'message'   => $e->getMessage(),
        //         'file'      => $e->getFile(),
        //         'line'      => $e->getLine(),
        //         'trace'     => $e->getTraceAsString(),
        //     ]);
        // }

        return $this->ok(
            null,
            'Merci pour votre message. Nous vous répondrons dans les plus brefs délais.'
        );
    }

    public function observations(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        return $this->ok(
            DB::table('contact_us')
                ->select(
                    'id',
                    'contact_names',
                    'contact_email',
                    'contact_phone',
                    'business_type',
                    'contact_message',
                    'contact_date'
                )
                ->orderByDesc('id')
                ->paginate($perPage)
        );
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
