<?php

namespace App\Http\Controllers;

use Exception;
use App\Helpers\PhoneHelper;
use Illuminate\Http\Request;
use App\Helpers\OtpQueueHelper;
use App\Http\Controllers\Controller;
use App\Models\MobileMoneyProviders;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Models\UsersMobileMoneyProviders;
use Illuminate\Support\Facades\Validator;

class UsersMobileMoneyProvidersController extends Controller
{
    public function generateOtp(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return $this->errorResponse('Utilisateur non authentifié', 401);
            }

            $validator = Validator::make($request->all(), [
                'mobile_money_provider_id' => 'required|integer',
                'phone_number'             => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Données invalides', 422);
            }

            if (!PhoneHelper::isValidPhoneNumber($request->phone_number, 'CD')) {
                return $this->errorResponse("Numéro invalide", 422);
            }

            $provider = MobileMoneyProviders::where('id', $request->mobile_money_provider_id)
                ->where('status', 'enabled')
                ->first();

            if (!$provider) {
                return $this->errorResponse('Provider invalide', 404);
            }

            // 🔁 Déjà actif ?
            $exists = UsersMobileMoneyProviders::where([
                'user_id'                  => $user->id,
                'mobile_money_provider_id' => $provider->id,
                'phone_number'             => $request->phone_number,
                'status'                   => 'active',
            ])->exists();

            if ($exists) {
                return $this->errorResponse('Ce numéro est déjà validé', 409);
            }

            // 🔐 OTP
            $otp = random_int(100000, 999999);

            $cacheKey = "mobile_money_otp:{$user->id}:{$provider->id}";

            Cache::put($cacheKey, [
                'otp'           => $otp,
                'phone_number'  => $request->phone_number,
                'provider_id'   => $provider->id,
            ], now()->addMinutes(5));

            // 📩 Envoi SMS
            try {

                
                Mail::raw(
                    "Votre OTP de confirmation est : {$otp}",
                    function ($message) use ($user) {
                        $message->to($user->email)
                            ->subject('OTP de confirmation');
                    }
                );
                // OtpQueueHelper::send(
                //     $request->phone_number,
                //     $user->collector,
                //     $user->id,
                //     $user->email,
                //     $otp,
                //     'sms'
                // );
            } catch (\Exception $e) {
                Cache::forget($cacheKey);
                return $this->errorResponse(
                    "Erreur lors de l'envoi de l'OTP : " . $e->getMessage(),
                    500
                );
            }

            return response()->json([
                'status'  => 200,
                'message' => 'OTP envoyé avec succès',
                'data'    => [
                    'expires_in' => 300
                ],
            ]);

        } catch (\Throwable $e) {
            return $this->errorResponse('Erreur interne', 500);
        }
    }

    public function validateOtp(Request $request)
{
    $request->validate([
        'mobile_money_provider_id' => 'required|integer',
        'otp'                      => 'required|string',
    ]);

    $user       = Auth::user();
    $providerId = (int) $request->mobile_money_provider_id;

    /*
    |--------------------------------------------------------------------------
    | 🔐 Vérification OTP
    |--------------------------------------------------------------------------
    */
    $cacheKey = "mobile_money_otp:{$user->id}:{$providerId}";
    $cached   = Cache::get($cacheKey);

    if (!$cached) {
        return $this->errorResponse("OTP expiré ou invalide", 410);
    }

    if ((int) $cached['otp'] !== (int) $request->otp) {
        return $this->errorResponse("OTP incorrect", 422);
    }

    /*
    |--------------------------------------------------------------------------
    | 💾 Activation définitive
    |--------------------------------------------------------------------------
    */
    UsersMobileMoneyProviders::updateOrCreate(
        [
            'user_id'                  => $user->id,
            'mobile_money_provider_id' => $providerId,
        ],
        [
            'phone_number' => $cached['phone_number'],
            'status'       => 'active',
        ]
    );

    Cache::forget($cacheKey);

    /*
    |--------------------------------------------------------------------------
    | 🔁 Recharger UNIQUEMENT le provider concerné
    |--------------------------------------------------------------------------
    */
    $enterprise = $this->getEse($user->id);
    if (!$enterprise) {
        return $this->errorResponse("Entreprise introuvable", 404);
    }

    $provider = MobileMoneyProviders::query()
        ->where('mobile_money_providers.enterprise_id', $enterprise->id)
        ->where('mobile_money_providers.status', 'enabled')
        ->where('mobile_money_providers.id', $providerId)
        ->leftJoin(
            'users_mobile_money_providers as ummp',
            function ($join) use ($user) {
                $join->on(
                    'mobile_money_providers.id',
                    '=',
                    'ummp.mobile_money_provider_id'
                )->where('ummp.user_id', $user->id);
            }
        )
        ->select([
            'mobile_money_providers.id',
            'mobile_money_providers.provider',
            'mobile_money_providers.name',
            'mobile_money_providers.metadata',
            'ummp.phone_number as user_phone',
            'ummp.status as user_status',
        ])
        ->first();

    if (!$provider) {
        return $this->errorResponse("Provider introuvable", 404);
    }

    /*
    |--------------------------------------------------------------------------
    | 🧠 Mapping IDENTIQUE AU GET
    |--------------------------------------------------------------------------
    */
    $data = [
        'id'            => $provider->id,
        'provider'      => $provider->provider,
        'name'          => $provider->name,
        'metadata'      => $provider->metadata,
        'path'          => collect($provider->metadata)
                            ->firstWhere('key', 'logo')['path'] ?? null,
        'user_phone'    => $provider->user_phone,
        'status'        => $provider->user_status,
        'is_configured' => !is_null($provider->user_phone),
    ];

    /*
    |--------------------------------------------------------------------------
    | 📡 PUSH REDIS — UNE SEULE OCCURRENCE
    |--------------------------------------------------------------------------
    */
    Redis::publish('user.mobilemoneyproviders', json_encode([
        'type' => 'update',
        'data' => [
            'userId' => $user->id,
            'record' => $data,
        ]
    ]));

    return response()->json([
        'status'  => 200,
        'message' => 'Numéro Mobile Money validé',
        'data'    => $data,
    ]);
}


    // public function indexWithUserConfig($enterpriseId)
    // {
    //     $user = Auth::user();
    //     if (!$user) {
    //         return $this->errorResponse('Utilisateur non authentifié', 401);
    //     }

    //     if (!is_numeric($enterpriseId)) {
    //         return $this->errorResponse('Enterprise ID invalide', 400);
    //     }

    //     $enterprise = $this->getEse($user->id);
    //     if (!$enterprise) {
    //         return $this->errorResponse('Entreprise introuvable', 404);
    //     }

    //     if ($enterprise->id != $enterpriseId) {
    //         return $this->errorResponse("Vous n'appartenez pas à cette entreprise", 403);
    //     }

    //     try {
    //        $providers = MobileMoneyProviders::where('enterprise_id', $enterpriseId)
    // ->where('status', 'enabled')
    // ->with(['users' => function ($q) use ($user) {
    //     $q->wherePivot('user_id', $user->id);
    // }])
    // ->get();


    //         if ($providers->isEmpty()) {
    //             return $this->errorResponse(
    //                 'Aucun provider mobile money trouvé pour cette entreprise',
    //                 404
    //             );
    //         }

    //         $data = $this->mapProvidersWithUserConfig($providers, $user);

    //         return response()->json([
    //             'status'  => 200,
    //             'message' => 'success',
    //             'error'   => null,
    //             'data'    => $data,
    //         ]);

    //     } catch (\Throwable $e) {
    //         return $this->errorResponse($e->getMessage(), 500);
    //     }
    // }


     public function indexWithUserConfig($enterpriseId)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Utilisateur non authentifié', 401);
        }

        if (!is_numeric($enterpriseId)) {
            return $this->errorResponse('Enterprise ID invalide', 400);
        }

        $enterprise = $this->getEse($user->id);
        if (!$enterprise) {
            return $this->errorResponse('Entreprise introuvable', 404);
        }

        if ($enterprise->id != $enterpriseId) {
            return $this->errorResponse("Vous n'appartenez pas à cette entreprise", 403);
        }

        try {
            // Providers actifs de l'entreprise
            $providers = MobileMoneyProviders::where('enterprise_id', $enterpriseId)
                ->where('status', 'enabled')
                ->with(['users' => function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                }])
                ->get();

            if ($providers->isEmpty()) {
                return $this->errorResponse(
                    'Aucun provider mobile money trouvé pour cette entreprise',
                    404
                );
            }

            // Mapping clean pour le frontend
            $data = $providers->map(function ($provider) {

                $userPivot = $provider->users->first()?->pivot;

                return [
                    'id'            => $provider->id,
                    'provider'      => $provider->provider,
                    'name'          => $provider->name,
                    'metadata'      => $provider->metadata,
                    'path'          => collect($provider->metadata)
                                        ->firstWhere('key', 'logo')['path'] ?? null,
                    'user_phone'    => $userPivot?->phone_number,
                    'status'        => $userPivot?->status,
                    'is_configured' => $userPivot !== null,
                ];
            });

            return response()->json([
                'status'  => 200,
                'message' => 'success',
                'error'   => null,
                'data'    => $data,
            ]);

        } catch (\Throwable $e) {
            return $this->errorResponse(
                $e->getMessage(),
                500
            );
        }
    } 

    private function mapProvidersWithUserConfig($providers, $user)
    {
        return $providers->map(function ($provider) use ($user) {

            $userPivot = $provider->users
                ->where('user_id', $user->id)
                ->first()?->pivot;

            return [
                'id'            => $provider->id,
                'provider'      => $provider->provider,
                'name'          => $provider->name,
                'metadata'      => $provider->metadata,
                'path'          => collect($provider->metadata)
                                    ->firstWhere('key', 'logo')['path'] ?? null,

                // 🔑 CONFIG USER
                'user_phone'    => $userPivot?->phone_number,
                'status'        => $userPivot?->status,
                'is_configured' => $userPivot !== null,
            ];
        });
    }

    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return $this->errorResponse('Utilisateur non authentifié', 401);
            }

            $validator = Validator::make($request->all(), [
                'user_id'                  => 'required|integer',
                'mobile_money_provider_id' => 'required|integer',
                'phone_number'             => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Données invalides', 422);
            }

            // 🔐 user connecté uniquement
            if ((int) $request->user_id !== $user->id) {
                return $this->errorResponse('Action non autorisée', 403);
            }

            // 📞 Validation pays (helper existant)
            if (!PhoneHelper::isValidPhoneNumber($request->phone_number, "CD")) {
                return $this->errorResponse("Numéro invalide pour le pays sélectionné", 422);
            }

            // ✅ Provider valide & actif
            $provider = MobileMoneyProviders::where('id', $request->mobile_money_provider_id)
                ->where('status', 'enabled')
                ->first();

            if (!$provider) {
                return $this->errorResponse('Provider mobile money invalide ou inactif', 404);
            }

            // 🏢 Vérifier entreprise
            $enterprise = $this->getEse($user->id);
            if (!$enterprise || $enterprise->id !== $provider->enterprise_id) {
                return $this->errorResponse("Ce provider n'appartient pas à votre entreprise", 403);
            }

            // 📶 Validation par PRÉFIXE PROVIDER (metadata)
            $prefixes = collect($provider->metadata)
            ->pluck('prefix')
            ->flatten()
            ->filter()
            ->values()
            ->toArray();

            $raw = preg_replace('/\D/', '', $request->phone_number);

            // Normalisation RDC
            if (str_starts_with($raw, '243')) {
                $msisdn = substr($raw, 3);
            } elseif (str_starts_with($raw, '0')) {
                $msisdn = substr($raw, 1);
            } else {
                $msisdn = $raw;
            }

            if (strlen($msisdn) !== 9) {
                return $this->errorResponse("Numéro invalide", 422);
            }

            // Préfixe réel
            $numberPrefix = substr($msisdn, 0, 2);

            // Vérification PROVIDER
            if (!empty($prefixes) && !in_array($numberPrefix, $prefixes, true)) {
                return $this->errorResponse(
                    "Ce numéro ne correspond pas au réseau {$provider->name}",
                    422
                );
            }

            // 🔁 Numéro déjà utilisé sur un autre provider ?
            $alreadyUsed = UsersMobileMoneyProviders::where('user_id', $user->id)
                ->where('phone_number', $request->phone_number)
                ->where('mobile_money_provider_id', '!=', $provider->id)
                ->first();

            if ($alreadyUsed) {
                return $this->errorResponse(
                    'Ce numéro est déjà associé à un autre réseau mobile',
                    409
                );
            }

            // 💾 Save (update ou create)
            $record = UsersMobileMoneyProviders::updateOrCreate(
                [
                    'user_id'                  => $user->id,
                    'mobile_money_provider_id' => $provider->id,
                ],
                [
                    'phone_number' => $request->phone_number,
                    'status'       => 'active',
                ]
            );

            $datatosend=$this->show($record);
            //    Redis::publish('user.mobilemoneyproviders', json_encode([
            //         'type' => 'update',
            //         'data' => [
            //             'userId'=> $record->user_id,
            //             'record' => $datatosend,
            //         ]
            //     ]));

            return response()->json([
                'status'  => 200,
                'message' => 'success',
                'error'   => null,
                'data'    => $datatosend,
            ]);

        } catch (\Throwable $e) {
            return $this->errorResponse(
                'Erreur lors de la configuration du Mobile Money',
                500
            );
        }
    }

   public function show(UsersMobileMoneyProviders $usermobileprovider)
    {
        $data = UsersMobileMoneyProviders::join(
                'mobile_money_providers',
                'users_mobile_money_providers.mobile_money_provider_id',
                '=',
                'mobile_money_providers.id'
            )
            ->where('users_mobile_money_providers.id', $usermobileprovider->id)
            ->first([
                'users_mobile_money_providers.*',
                'mobile_money_providers.provider',
                'mobile_money_providers.country',
                'mobile_money_providers.name',
                'mobile_money_providers.metadata',
            ]);

        // 🔐 Sécurité
        if (!$data) {
            return null;
        }

        // ✅ Extraction du logo path
        $metadata = is_string($data->metadata)
        ? collect(json_decode($data->metadata, true))
        : collect($data->metadata);

        $data->path = $metadata
            ->firstWhere('key', 'logo')['path'] ?? null;

        return $data;
    }

}
