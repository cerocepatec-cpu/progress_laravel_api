<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Str;
use App\Services\OTPService;
use Illuminate\Http\Request;
use App\Helpers\OtpQueueHelper;
use App\Jobs\OTP\SendOtpSmsJob;
use App\Services\BulkSmsService;
use App\Models\wekamemberaccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use App\Models\wekatransfertsaccounts;
use App\Helpers\DestinationAccountResolver;
use App\Http\Controllers\WekaAccountsTransactionsController;

class WekatransfertsaccountsController extends Controller
{
    public function store(Request $request)
    {
        $user=Auth::user();
        if(!$user){
            $this->errorResponse("Vous n'êtes pas connecté. Nous sommes désolé.",400);
            return ;
        }

        $request->validate([
            'source' => 'required|array',
            'destination' => 'required|array',
            'original_amount' => 'required|numeric|min:1',
            'motif' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $sourceId = $request->source['id'] ?? null;
            $destinationId = $request->destination['id'] ?? null;

            if (!$sourceId || !$destinationId) {
                return $this->errorResponse('account_id manquant dans source ou destination',422);
            }

            $sourceAccount = \App\Models\wekamemberaccounts::find($sourceId);
            $destinationAccount = \App\Models\Wekamemberaccounts::find($destinationId);

            if (!$sourceAccount || !$destinationAccount) {
                return $this->errorResponse('Compte source ou destination introuvable',404);
            }

            // Vérifie que l'utilisateur est bien affecté à une entreprise
            $affectation = \App\Models\UsersEnterprise::where('user_id', $request->done_by)->first();
            if (!$affectation) {
                return $this->errorResponse('Affectation utilisateur-entreprise introuvable',403);
            }

            if ($affectation->enterprise_id != $request->enterprise) {
                return $this->errorResponse('Entreprise non autorisée pour cet utilisateur', 403);
            }

            // Vérifie les soldes
            if ($sourceAccount->sold < $request->original_amount) {
                return $this->errorResponse('Solde insuffisant sur le compte source', 422);
            }

            $sourceCurrencyId = $sourceAccount->currency_id;
            $destinationCurrencyId = $destinationAccount->currency_id;

            $conversionRate = 1;
            $convertedAmount = $request->original_amount;

            if ($sourceCurrencyId != $destinationCurrencyId) {
                $conversionRate = $this->getConversionRate($sourceCurrencyId, $destinationCurrencyId);

                if (!$conversionRate || $conversionRate <= 0) {
                    return $this->errorResponse('Taux de conversion invalide ou indisponible', 422);
                }

                $convertedAmount = round($request->original_amount * $conversionRate, 2);
            }

            // Création du transfert
            $transfer =wekatransfertsaccounts::create([
                'enterprise' =>$this->getEse($user->id)->id,
                'done_by' => $user->id,
                'validated_by' => null,
                'source' => $sourceAccount->id,
                'destination' => $destinationAccount->id,
                'source_currency_id' => $sourceCurrencyId,
                'destination_currency_id' => $destinationCurrencyId,
                'original_amount' => $request->original_amount,
                'converted_amount' => $convertedAmount,
                'conversion_rate' => $conversionRate,
                'pin' => $request->pin ?? null,
                'transfert_status' => 'pending',
            ]);

            DB::commit();

            return response()->json(
                ["status"=>200,
                "message"=>"success",
                "error"=>null,
                "data"=>$this->show($transfer)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status"=>500,
                "message"=>"error",
                "error"=>$e->getMessage(),
                "data"=>null
            ]);;
        }
    }
    
    public function directDepositViaAgent(Request $request, BulkSmsService $sms)
{
    $user = Auth::user();
    if (!$user) {
        return $this->errorResponse("Vous n'êtes pas connecté.", 400);
    }

    $request->validate([
        'source' => 'required',
        'destination' => 'required|numeric',
        'original_amount' => 'required|numeric|min:1'
    ]);

    try {

        $source = $request->source;
        $destination = (int) $request->destination;
        $amount = $request->original_amount;

        // 🔵 Compte destination (appartient à l'initiateur)
        $destinationAccount = wekamemberaccounts::find($destination);
        if (!$destinationAccount) {
            return $this->errorResponse('Compte destination introuvable!', 422);
        }

        // 🔴 Compte source (donneur)
        $sourceAccount = DestinationAccountResolver::resolve(
            $source,
            $destinationAccount->money_id
        );

        if (!$sourceAccount) {
            return $this->errorResponse('Compte source introuvable!', 422);
        }

        // 🔐 Même monnaie
        if ($sourceAccount->money_id !== $destinationAccount->money_id) {
            return $this->errorResponse(
                "Les deux comptes doivent être de la même monnaie.",
                422
            );
        }

        if ($sourceAccount->id === $destinationAccount->id) {
            return $this->errorResponse(
                'Le compte source et le compte destination ne peuvent pas être les mêmes.',
                422
            );
        }

        if ($sourceAccount->sold < $amount) {
            return $this->errorResponse(
                'Le compte source ne dispose pas de fonds suffisants.',
                422
            );
        }

        // 🔐 Vérifier agent
        $agent = User::find($sourceAccount->user_id);
        if (!$agent || !$agent->collector) {
            return $this->errorResponse(
                'Le compte source doit appartenir à un agent valide.',
                422
            );
        }

        if ($this->getEse($agent->id)->id !== $this->getEse($user->id)->id) {
            return $this->errorResponse(
                "L'agent source n'appartient pas à votre entreprise.",
                422
            );
        }

        // ===============================
        // 🔥 PHASE OTP (ON S'ARRÊTE ICI)
        // ===============================

        $operationId = (string) Str::uuid();

        $otp = app(OTPService::class)->generateOtp(
            $sourceAccount->user_id,
            $operationId
        );

        if(!$agent->email){
            return $this->errorResponse(
                "L'agent n'a pas d'email valide pour l'envoi de l'OTP.",
                422
            );
        }
         try {
            // new SendOtpSmsJob( $agent->user_phone, 
            //    $agent->collector, 
            //    $agent->id,  
            //    $agent->email, 
            //    $otp,  
            //    'sms')->handle($sms);
            OtpQueueHelper::send(
                $agent->user_phone,
                $agent->collector,
                $agent->id,
                $agent->email,
                $otp,
                $otpChannel ?? 'sms'
            );

            // réponse immédiate
            // return response()->json([
            //     'message' => "OTP en cours d'envoi",
            //     'status' => "success"
            // ]);
        } catch (\Exception $e) {
            return $this->errorResponse("Erreur lors de l'envoi de l'OTP : " . $e->getMessage());
        }
        // 📧 Envoi OTP AU PROPRIÉTAIRE DU COMPTE SOURCE
        // Mail::raw(
        //     "Votre code OTP pour confirmer la transaction est : {$otp}\n\nCe code expire dans 5 minutes.",
        //     function ($message) use ($agent) {
        //         $message->to($agent->email)
        //                 ->subject("Confirmation de transaction");
        //     }
        // );

        // 🧠 Stocker l'opération en attente
        Cache::put(
            "pending_operation_{$operationId}",
            [
                'source_account_id' => $sourceAccount->id,
                'destination_account_id' => $destinationAccount->id,
                'amount' => $amount,
                'initiator_id' => $user->id,
            ],
            now()->addMinutes(5)
        );

        // ✅ RÉPONSE ATTENTE OTP
        return response()->json([
            "status" => 200,
            "message" => "otp_required",
            "error" => null,
            "data" => [
                "operation_id" => $operationId,
                "expires_in" => 300,
                "channel" => "email"
            ]
        ]);

    } catch (\Throwable $e) {
        DB::rollBack();

        return response()->json([
            "status" => 500,
            "message" => "error",
            "error" => $e->getMessage(),
            "data" => null
        ]);
    }
}

public function validateDirectDepositOtp(Request $request)
{
     $user = Auth::user();
    if (!$user) {
        return $this->errorResponse("Vous n'êtes pas connecté.", 400);
    }

    $request->validate([
        'operation_id' => 'required|string',
        'otp' => 'required|string|min:6'
    ]);

    // 🔎 Récupérer l'opération en attente
    $pending = Cache::get("pending_operation_{$request->operation_id}");

    if (!$pending) {
        return $this->errorResponse(
            'Transaction expirée ou invalide.',
            422
        );
    }

    $sourceAccount = wekamemberaccounts::find($pending['source_account_id']);
    if (!$sourceAccount) {
        return $this->errorResponse(
            'Compte source introuvable.',
            422
        );
    }

    // 🔐 Vérifier OTP
    $isValid = app(\App\Services\OTPService::class)->verifyOtp(
        $sourceAccount->user_id,
        $request->operation_id,
        $request->otp
    );

    if (!$isValid) {
        return $this->errorResponse(
            'OTP invalide ou expiré.',
            422
        );
    }

    DB::beginTransaction();

    try {
        $destinationAccount = wekamemberaccounts::find(
            $pending['destination_account_id']
        );

        $amount = $pending['amount'];

        // 🔥 SÉCURITÉ FINALE
        if ($sourceAccount->sold < $amount) {
            throw new \Exception('Solde insuffisant.');
        }

        // 🔥 DÉBIT / CRÉDIT
        $sourceAccount->decrement('sold', $amount);
        $destinationAccount->increment('sold', $amount);

        Cache::forget("pending_operation_{$request->operation_id}");

        
        $motif = "Dépôt via agent";

        // Soldes AVANT
        $sourceSoldBefore = $sourceAccount->sold;
        $beneficiarySoldBefore = $destinationAccount->sold;

        // Soldes APRÈS
        $sourceSoldAfter = $sourceSoldBefore - $amount;
        $beneficiarySoldAfter = $beneficiarySoldBefore + $amount;

        // Pas de frais
        $totalAmount = $amount;
        $fees = ['fee' => 0];

        $sourceTransaction = $this->createTransaction(
            $totalAmount,
            $sourceSoldBefore,
            $sourceSoldAfter,
            "withdraw",
            $motif,
            $destinationAccount->user_id,                         // initiateur
            $sourceAccount->id,               // compte source
            $sourceAccount->user_id,          // propriétaire du compte source
            null,
            $user->full_name ?: $user->user_name,
            $fees['fee'],                     // 0
            $user->user_phone,
            $user->adress
        );

        $beneficiaryTransaction = $this->createTransaction(
            $amount,
            $beneficiarySoldBefore,
            $beneficiarySoldAfter,
            "entry",
            $motif,
            $sourceAccount->user_id,                         // initiateur
            $destinationAccount->id,          // compte bénéficiaire
            $destinationAccount->user_id,     // propriétaire du compte bénéficiaire
            null,
            $user->full_name ?: $user->user_name,
            0,                                 // pas de frais
            $user->user_phone,
            $user->adress
        );

        DB::commit();

        $memberAccountCtrl = new WekamemberaccountsController();
        event(new \App\Events\MemberAccountUpdated(
           $sourceAccount->user_id,
            $memberAccountCtrl->show($sourceAccount)
        ));  
        
        event(new \App\Events\MemberAccountUpdated(
             $destinationAccount->user_id,
            $memberAccountCtrl->show($destinationAccount)
        )); 

        $wekaAccountTransactionCtrl = new WekaAccountsTransactionsController();
        event(new \App\Events\TransactionSent(
            $sourceAccount->user_id,
            $wekaAccountTransactionCtrl->show($sourceTransaction)
        ));
        
        event(new \App\Events\TransactionSent(
            $destinationAccount->user_id,
            $wekaAccountTransactionCtrl->show($beneficiaryTransaction)
        ));

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'error' => null,
            'data' =>$wekaAccountTransactionCtrl->show($beneficiaryTransaction)
        ]);

    } catch (\Throwable $e) {
        DB::rollBack();

        return response()->json([
            'status' => 500,
            'message' => 'error',
            'error' => $e->getMessage(),
            'data' => null
        ]);
    }
}


    public function sosStore(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse("Vous n'êtes pas connecté.", 400);
        }

        $request->validate([
            'source' => 'required|string',
            'destination' => 'required|integer',
            'original_amount' => 'required|numeric|min:1',
            'motif' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $sourceId = $request->source;
            $destinationId = $request->destination;
            $amount = $request->original_amount;

            // Sécurités
            if (!$destinationId){
                return $this->errorResponse('vous devez fournir un compte bénéficiaire svp!', 422);
            } 
            if (!$sourceId){
                return $this->errorResponse('vous devez fournir le compte source svp!', 422);
            } 

        
            $destinationAccount = wekamemberaccounts::find($destinationId);

            if (!$destinationAccount){
                 return $this->errorResponse('compte bénéficiaire introuvable!', 422);
            }

            // 🔥 FINDBY corrigé
            $sourceAccount= DestinationAccountResolver::resolve(
                $sourceId,
                $destinationAccount->money_id
            );

            if (!$sourceAccount){
                return $this->errorResponse('compte source introuvable!', 422);
            }
                

            // 🔥 Vérifier même monnaie
            if ($sourceAccount->money_id !== $destinationAccount->money_id) {
                return $this->errorResponse(
                    "Les deux comptes doivent être dans la même monnaie.",
                    422
                );
            }

            if ($amount <= 0) {
                return $this->errorResponse('Vous devez fournir un montant svp', 422);
            }

            // Création du transfert
            $transfer = wekatransfertsaccounts::create([
                'enterprise' => $this->getEse($user->id)->id,
                'done_by' => $user->id,
                'validated_by' => null,
                'source' => $sourceAccount->id,
                'destination' => $destinationAccount->id,
                'source_currency_id' => $sourceAccount->money_id,
                'destination_currency_id' => $destinationAccount->money_id,
                'original_amount' => $amount,
                'converted_amount' => $amount,
                'conversion_rate' => 1,
                'pin' => $request->pin ?? null,
                'transfert_status' => 'pending',
                'motif' => $request->motif,
            ]);

            // 🔥 NOTIFICATIONS REDIS
            event(new \App\Events\UserRealtimeNotification(
                $sourceAccount->user_id,
                'Nouveau SOS Transaction',
                'Vous avez reçu une demande de dépôt de ' . $amount . ' ' .
                wekamemberaccounts::getMoneyAbreviationByAccountNumber($sourceAccount->account_number),
                'success'
            ));
            $toreturn=$this->showLite($transfer->id);
            // GIVE SOURCE OWNER ACCOUNT TO VALIDATE 
            $source_transfer=$toreturn;
            $source_transfer->can_validate=true;
            event(new \App\Events\SosAccountEvent($sourceAccount->user_id,$source_transfer));
            event(new \App\Events\SosAccountEvent($destinationAccount->user_id,$toreturn));
             DB::commit();
            return response()->json([
                "status" => 200,
                "message" => "success",
                "error" => null,
                "data" =>$toreturn 
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status" => 500,
                "message" => "error",
                "error" => $e->getMessage(),
                "data" => null
            ]);
        }
    }

public function indexPending()
{
    $user = auth()->user();

    // ===============================
    // 🔢 COMPTEURS can_validate
    // ===============================
    $baseCountQuery = wekatransfertsaccounts::query()
        ->leftJoin('wekamemberaccounts as src', 'src.id', '=', 'wekatransfertsaccounts.source')
        ->leftJoin('wekamemberaccounts as dest', 'dest.id', '=', 'wekatransfertsaccounts.destination')
        ->where('wekatransfertsaccounts.transfert_status', 'pending')
        ->where(function ($q) use ($user) {
            $q->where('src.user_id', $user->id)
              ->orWhere('dest.user_id', $user->id);
        });

    // ✅ Peut valider (destinataire)
    $countCanValidateTrue = (clone $baseCountQuery)
        ->where('dest.user_id', $user->id)
        ->count();

    // ❌ Ne peut pas valider (source)
    $countCanValidateFalse = (clone $baseCountQuery)
        ->where('src.user_id', $user->id)
        ->count();

    // ===============================
    // 📄 LISTE PAGINÉE
    // ===============================
    $ids = wekatransfertsaccounts::query()
        ->leftJoin('wekamemberaccounts as src', 'src.id', '=', 'wekatransfertsaccounts.source')
        ->leftJoin('wekamemberaccounts as dest', 'dest.id', '=', 'wekatransfertsaccounts.destination')
        ->where('wekatransfertsaccounts.transfert_status', 'pending')
        ->where(function ($q) use ($user) {
            $q->where('src.user_id', $user->id)
              ->orWhere('dest.user_id', $user->id);
        })
        ->orderByRaw("CASE WHEN src.user_id = ? THEN 0 ELSE 1 END", [$user->id])
        ->orderBy('wekatransfertsaccounts.id', 'asc')
        ->select('wekatransfertsaccounts.id')
        ->paginate(10);

    $items = collect($ids->items())->map(function ($item) {
        return $this->showLite($item->id);
    });

    // ===============================
    // 🚀 RESPONSE
    // ===============================
    return response()->json([
        'data' => $items,

        // pagination
        'current_page' => $ids->currentPage(),
        'last_page' => $ids->lastPage(),
        'per_page' => $ids->perPage(),
        'total' => $ids->total(),

        // compteurs
        'count_can_validate_true' => $countCanValidateTrue,
        'count_can_validate_false' => $countCanValidateFalse,
    ]);
}

//    public function indexPending()
//     {
//         $user = auth()->user();
//         $ids = wekatransfertsaccounts::query()
//             ->leftJoin('wekamemberaccounts as src', 'src.id', '=', 'wekatransfertsaccounts.source')
//             ->leftJoin('wekamemberaccounts as dest', 'dest.id', '=', 'wekatransfertsaccounts.destination')
//             ->where('wekatransfertsaccounts.transfert_status', 'pending')
//             ->where(function ($q) use ($user) {
//                 $q->where('src.user_id', $user->id)
//                 ->orWhere('dest.user_id', $user->id);
//             })
//             ->orderByRaw("CASE WHEN src.user_id = ? THEN 0 ELSE 1 END", [$user->id])
//             ->orderBy('wekatransfertsaccounts.id', 'asc')
//             ->select('wekatransfertsaccounts.id')
//             ->paginate(10);

//         $items = collect($ids->items())->map(function ($item) {
//             return $this->showLite($item->id);
//         });

//         return response()->json([
//             'data' => $items,
//             'current_page' => $ids->currentPage(),
//             'last_page' => $ids->lastPage(),
//             'per_page' => $ids->perPage(),
//             'total' => $ids->total(),
//         ]);
//     }
    
    public function getNbrSosPending()
    {
        $user = auth()->user();
        if (!$user) {
           return $this->errorResponse("Vous n'êtes pas authentifié!",401);
        }

        try {
            $ids = wekatransfertsaccounts::query()
            ->leftJoin('wekamemberaccounts as src', 'src.id', '=', 'wekatransfertsaccounts.source')
            ->leftJoin('wekamemberaccounts as dest', 'dest.id', '=', 'wekatransfertsaccounts.destination')
            ->where('wekatransfertsaccounts.transfert_status', 'pending')
            ->where(function ($q) use ($user) {
                $q->where('src.user_id', $user->id)
                ->orWhere('dest.user_id', $user->id);
            })->get();
            return $this->successResponse("success",$ids->count());
        } catch (\Throwable $th) {
           return $this->errorResponse($th->getMessage(),500);
        }
        
    }


   public function getTransfersList(Request $request)
    {
        $from = $request->from ?? date('Y-m-d');
        $to = $request->to ?? date('Y-m-d');

        if (!$request->filled('enterprise')) {
            return response()->json([
                'status' => 422,
                'message' => 'Le champ enterprise est obligatoire.'
            ], 422);
        }

        try {
            $query =wekatransfertsaccounts::with([
                'sourceAccount',
                'destinationAccount',
                'sourceCurrency',
                'destinationCurrency',
                'doneBy'
                // 'enterprise'
            ]);

            // ✅ Clause obligatoire
            $query->where('enterprise', $request->enterprise);

            // ✅ Période
            $query->whereBetween('created_at', ["$from 00:00:00", "$to 23:59:59"]);

            if (!empty($request->source)) {
                $query->whereIn('source', is_array($request->source) ? $request->source : [$request->source]);
            }

            if (!empty($request->destination)) {
                $query->whereIn('destination', is_array($request->destination) ? $request->destination : [$request->destination]);
            }

            if (!empty($request->transfert_status)) {
                $query->whereIn('transfert_status', is_array($request->transfert_status) ? $request->transfert_status : [$request->transfert_status]);
            }

            if (!empty($request->source_currency_id)) {
                $query->whereIn('source_currency_id', is_array($request->source_currency_id) ? $request->source_currency_id : [$request->source_currency_id]);
            }

            // ✅ Tous les IDs
            $allIds = [];
            (clone $query)->select('id')->orderBy('id')->chunk(1000, function ($transfers) use (&$allIds) {
                foreach ($transfers as $t) {
                    $allIds[] = $t->id;
                }
            });

            // ✅ Pagination
            $limit = $request->get('limit', 50);
            $paginated = $query->orderBy('created_at', 'desc')->paginate($limit);

            $data = $paginated->getCollection();
            $paginated->setCollection($data);

            // ✅ Totaux par monnaie
            $totalsByMoney = $data->groupBy('source_currency_id')->map(function ($items, $money_id) {
                return [
                    'money_id'        => $money_id,
                    'abreviation'     => optional($items->first()->sourceCurrency)->abreviation ?? '',
                    'total_original'  => $items->sum('original_amount'),
                    'total_converted' => $items->sum('converted_amount'),
                ];
            })->values();

            // ✅ Totaux par statut
            $totalsByStatus = $data->groupBy('transfert_status')->map(function ($items, $status) {
                return [
                    'status' => $status,
                    'total' => $items->sum('converted_amount'),
                    'count' => $items->count(),
                ];
            })->values();

            return response()->json([
                'status' => 200,
                'from' => $from,
                'to' => $to,
                'message' => 'success',
                'data' => $paginated,
                'all_ids' => $allIds,
                'totals_by_money' => $totalsByMoney,
                'totals_by_status' => $totalsByStatus,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Erreur lors du chargement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(wekatransfertsaccounts $wekatransfertsaccounts)
    {
        $transfer = wekatransfertsaccounts::with([
            'sourceAccount',
            'destinationAccount',
            'sourceCurrency',
            'destinationCurrency',
            'doneBy',
            'validatedBy',
            'enterprise'
        ])->find($wekatransfertsaccounts->id);

        if (!$transfer) {
            return $this->errorResponse("Transfert introuvable", 404);
        }

        return $transfer;
    }


public function showLite($id)
{
    $user = auth()->user();

    $transfer = wekatransfertsaccounts::query()
        ->select([
            'wekatransfertsaccounts.id',
            'wekatransfertsaccounts.original_amount',
            'wekatransfertsaccounts.converted_amount',
            'wekatransfertsaccounts.conversion_rate',
            'wekatransfertsaccounts.motif',
            'wekatransfertsaccounts.transfert_status',
            'wekatransfertsaccounts.created_at',
            'wekatransfertsaccounts.updated_at',
            'wekatransfertsaccounts.done_by',

            // Comptes
            'src.account_number as source_account_number',
            'dest.account_number as destination_account_number',

            // Devises
            'sc.abreviation as source_currency_code',
            'dc.abreviation as destination_currency_code',

            // 👤 Utilisateur ayant effectué l'opération
            'u_done.name as done_by_name',

            // 👤 Utilisateur ayant validé
            'u_valid.name as validated_by_name',

            // 🏢 Entreprise
            'e.name as enterprise_name',

            // 🔥 SENT_TO = PROPRIÉTAIRE DU COMPTE SOURCE
            'src.user_id as sent_to_id',
            'u_src.name as sent_to_name',
        ])
        ->leftJoin('wekamemberaccounts as src', 'src.id', '=', 'wekatransfertsaccounts.source')
        ->leftJoin('wekamemberaccounts as dest', 'dest.id', '=', 'wekatransfertsaccounts.destination')
        ->leftJoin('moneys as sc', 'sc.id', '=', 'wekatransfertsaccounts.source_currency_id')
        ->leftJoin('moneys as dc', 'dc.id', '=', 'wekatransfertsaccounts.destination_currency_id')
        ->leftJoin('users as u_done', 'u_done.id', '=', 'wekatransfertsaccounts.done_by')
        ->leftJoin('users as u_valid', 'u_valid.id', '=', 'wekatransfertsaccounts.validated_by')
        ->leftJoin('users as u_src', 'u_src.id', '=', 'src.user_id')
        ->leftJoin('enterprises as e', 'e.id', '=', 'wekatransfertsaccounts.enterprise')
        ->where('wekatransfertsaccounts.id', $id)
        ->first();

    if (!$transfer) {
        return $this->errorResponse("Transfert introuvable", 404);
    }

    // 🔐 Permission
    $transfer->can_validate = ($user->id == $transfer->sent_to_id);

    /*
    |--------------------------------------------------------------------------
    | ⏱️ CALCUL DU DÉLAI MÉTIER
    |--------------------------------------------------------------------------
    */
    $createdAt = Carbon::parse($transfer->created_at);

    if (in_array($transfer->transfert_status, ['validated', 'rejected'])) {
        $endAt = Carbon::parse($transfer->updated_at);
        $label = 'après la création';
    } else {
        $endAt = now();
        $label = 'depuis la création';
    }

    $seconds = $createdAt->diffInSeconds($endAt);

    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);

    if ($days > 0) {
        $human = "{$days}j {$hours}h {$label}";
    } elseif ($hours > 0) {
        $human = "{$hours}h {$minutes}m {$label}";
    } elseif ($minutes > 0) {
        $human = "{$minutes}m {$label}";
    } else {
        $human = "{$seconds}s {$label}";
    }

    $transfer->delay_seconds = $seconds;
    $transfer->delay_human   = $human;

    return $transfer;
}





    // public function showLite($id)
    // {
    //     $user = auth()->user();

    //     $transfer = wekatransfertsaccounts::query()
    //         ->select([
    //             'wekatransfertsaccounts.id',
    //             'wekatransfertsaccounts.original_amount',
    //             'wekatransfertsaccounts.converted_amount',
    //             'wekatransfertsaccounts.conversion_rate',
    //             'wekatransfertsaccounts.motif',
    //             'wekatransfertsaccounts.transfert_status',
    //             'wekatransfertsaccounts.created_at',
    //             'wekatransfertsaccounts.updated_at',
    //             'src.account_number as source_account_number',
    //             'dest.account_number as destination_account_number',
    //             'sc.abreviation as source_currency_code',
    //             'dc.abreviation as destination_currency_code',
    //             'u1.name as done_by_name',
    //             'u2.name as validated_by_name',
    //             'e.name as enterprise_name',
    //             'src.user_id as source_owner_id'
    //         ])
    //         ->leftJoin('wekamemberaccounts as src', 'src.id', '=', 'wekatransfertsaccounts.source')
    //         ->leftJoin('wekamemberaccounts as dest', 'dest.id', '=', 'wekatransfertsaccounts.destination')
    //         ->leftJoin('moneys as sc', 'sc.id', '=', 'wekatransfertsaccounts.source_currency_id')
    //         ->leftJoin('moneys as dc', 'dc.id', '=', 'wekatransfertsaccounts.destination_currency_id')
    //         ->leftJoin('users as u1', 'u1.id', '=', 'wekatransfertsaccounts.done_by')
    //         ->leftJoin('users as u2', 'u2.id', '=', 'wekatransfertsaccounts.validated_by')
    //         ->leftJoin('enterprises as e', 'e.id', '=', 'wekatransfertsaccounts.enterprise')
    //         ->where('wekatransfertsaccounts.id', $id)
    //         ->first();

    //     if (!$transfer) {
    //         return $this->errorResponse("Transfert introuvable", 404);
    //     }

    //     // Permission → il doit être propriétaire du compte source
    //     $transfer->can_validate = ($user->id == $transfer->source_owner_id);
    //     return $transfer;
    // }

   public function validateTransfer($id)
    {
        DB::beginTransaction();

        try {

            $user = auth()->user();
            $transfer = wekatransfertsaccounts::lockForUpdate()->findOrFail($id);

            // -------------------------------------------
            // VALIDATIONS DE BASE
            // -------------------------------------------
            if ($transfer->transfert_status !== 'pending') {
                DB::rollBack();
                return $this->errorResponse("Cette transaction n'est plus en attente.");
            }

            // Récupération du compte source
            $source = wekamemberaccounts::find($transfer->source);
            if (!$source) {
                DB::rollBack();
                return $this->errorResponse("Compte source introuvable.");
            }

            // Seul le propriétaire du compte source peut valider
            if ($source->user_id !== $user->id) {
                DB::rollBack();
                return $this->errorResponse("Vous n'êtes pas autorisé à valider ce transfert.", 403);
            }

            // Récupération du compte bénéficiaire (destination)
            $destination = wekamemberaccounts::find($transfer->destination);
            if (!$destination) {
                DB::rollBack();
                return $this->errorResponse("Compte bénéficiaire introuvable.");
            }

            // Vérifier la disponibilité des comptes
            if (!$source->isavailable()) {
                DB::rollBack();
                return $this->errorResponse("Le compte source est temporairement indisponible.");
            }

            if (!$destination->isavailable()) {
                DB::rollBack();
                return $this->errorResponse("Le compte bénéficiaire est temporairement indisponible.");
            }

            // Vérifier les monnaies
            if ($source->money_id != $destination->money_id) {
                DB::rollBack();
                return $this->errorResponse("Les comptes source et destination n'utilisent pas la même monnaie.");
            }

            $amount = floatval($transfer->original_amount);

            // Solde suffisant
            if ($source->sold < $amount) {
                DB::rollBack();
                return $this->errorResponse("Solde insuffisant pour valider ce transfert.");
            }

            // -------------------------------------------
            // DEBIT SOURCE
            // -------------------------------------------
            $sourceBefore = $source->sold;
            $source->sold -= $amount;
            $source->save();
            $sourceAfter = $source->sold;

            // Historique côté source
            $sourceTransaction = $this->createTransaction(
                $amount,
                $sourceBefore,
                $sourceAfter,
                "withdraw",
                $transfer->motif,
                $user->id,
                $source->id,
                $source->user_id,
                null,
                $user->name,
                0,
                $user->phone ?? null,
                $user->adress ?? null
            );

            // -------------------------------------------
            // CREDIT BÉNÉFICIAIRE
            // -------------------------------------------
            $destinationBefore = $destination->sold;
            $destination->sold += $amount;
            $destination->save();
            $destinationAfter = $destination->sold;

            $beneficiaryUser = User::find($destination->user_id);

            // Historique côté bénéficiaire
            $beneficiaryTransaction = $this->createTransaction(
                $amount,
                $destinationBefore,
                $destinationAfter,
                "entry",
                $transfer->motif,
                $user->id,
                $destination->id,
                $destination->user_id,
                null,
                $user->name,
                0,
                $user->phone ?? null,
                $user->adress ?? null
            );

            // -------------------------------------------
            // MISE À JOUR DU TRANSFERT
            // -------------------------------------------
            $transfer->validated_by = $user->id;
            $transfer->transfert_status ='validated';
            $transfer->save();

        // -----------------------------------------------------------
        // 📧 EMAILS (logique unifiée comme demandée)
        // -----------------------------------------------------------
        $sourceUser = $user;
        $sourceAccountNumber = $source->account_number;
        $beneficiaryAccountNumber = $destination->account_number;

        // 🔥 EMAIL SOURCE (Validation = retrait du compte source)
        $this->sendTransactionEmail(
            $sourceUser,
            "Notification de Retrait",
            "Un transfert SOS a été validé et un retrait a été effectué sur votre compte.",
            $sourceTransaction,
            0, // Aucun frais pour SOS
            $sourceTransaction->sold_before,
            $sourceAfter,
            $sourceAccountNumber,
            $beneficiaryAccountNumber
        );

        // 🔥 EMAIL BÉNÉFICIAIRE (Validation = dépôt sur le compte destination)
        $this->sendTransactionEmail(
            $beneficiaryUser,
            "Notification de Dépôt",
            "Vous avez reçu un dépôt suite à une validation de transaction SOS.",
            $beneficiaryTransaction,
            0, // Aucun frais
            $beneficiaryTransaction->sold_before,
            $destinationAfter,
            $sourceAccountNumber,
            $beneficiaryAccountNumber
        );


        // -------------------------------------------
        // REALTIME EVENTS
        // -------------------------------------------
        $memberAccountCtrl = new WekamemberaccountsController();
        $transactionCtrl = new WekaAccountsTransactionsController();
        event(new \App\Events\UserRealtimeNotification(
            $beneficiaryUser->id,
            'Nouveau transfert confirmé',
            'Vous avez reçu un transfert de '.$amount.' '.wekamemberaccounts::getMoneyAbreviationByAccountNumber($destination->account_number),
            'success'
        ));

        event(new \App\Events\TransactionSent(
            $beneficiaryUser->id,
            $transactionCtrl->show($beneficiaryTransaction)
        ));

        event(new \App\Events\TransactionSent(
            $source->user_id,
            $transactionCtrl->show($sourceTransaction)
        ));

        event(new \App\Events\MemberAccountUpdated(
            $beneficiaryUser->id,
            $memberAccountCtrl->show($destination)
        )); 
        
        event(new \App\Events\MemberAccountUpdated(
            $source->user_id,
            $memberAccountCtrl->show($source)
        ));

        $toreturn=$this->showLite($transfer->id);
        $toreturn->can_validate=false;
        event(new \App\Events\SosAccountUpdateEvent($user->id,$toreturn));
        event(new \App\Events\SosAccountUpdateEvent($beneficiaryUser->id,$toreturn));

        DB::commit();
        return $this->successResponse("success",$toreturn);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse("Erreur interne : ".$e->getMessage());
        }
    }


    public function rejectTransfer(Request $request, $id)
    {
        DB::beginTransaction();

        try {

            $user = auth()->user();

            $reason = $request->reason ?? "Rejeté par le propriétaire du compte source.";

            // Récupération transaction en attente
            $transfer = wekatransfertsaccounts::lockForUpdate()->findOrFail($id);

            // Déjà traité ?
            if ($transfer->transfert_status !== 'pending') {
                DB::rollBack();
                return $this->errorResponse("Ce transfert n'est plus en attente.");
            }

            // Récupération comptes
            $source = wekamemberaccounts::find($transfer->source);
            if (!$source) {
                DB::rollBack();
                return $this->errorResponse("Compte source introuvable.");
            }

            // Seul le propriétaire peut rejeter
            if ($source->user_id !== $user->id) {
                DB::rollBack();
                return $this->errorResponse("Vous n'êtes pas autorisé à rejeter ce transfert.", 403);
            }

            $destination = wekamemberaccounts::find($transfer->destination);
            if (!$destination) {
                DB::rollBack();
                return $this->errorResponse("Compte bénéficiaire introuvable.");
            }

            // Récupération du bénéficiaire
            $beneficiaryUser = User::find($destination->user_id);

            // -------------------------------------------
            // METTRE À JOUR LE TRANSFERT
            // -------------------------------------------
            $transfer->validated_by = $user->id;
            $transfer->transfert_status = 'denied';
            $transfer->motif = $reason;
            $transfer->save();

            // *********************************************************
            // 📧 EMAILS SELON TA LOGIQUE sendTransactionEmail()
            // *********************************************************

            $sourceUser = $user;
            $sourceAccountNumber = $source->account_number;
            $beneficiaryAccountNumber = $destination->account_number;

            // 🔥 EMAIL POUR LE PROPRIÉTAIRE (INFORMÉ DU REJET)
            $this->sendTransactionEmail(
                $sourceUser,
                "Transfert SOS rejeté",
                "Vous avez rejeté une demande de transfert SOS.",
                null, // Pas de transaction mouvementée
                0,
                null,
                null,
                $sourceAccountNumber,
                $beneficiaryAccountNumber
            );

            // 🔥 EMAIL POUR LE BÉNÉFICIAIRE (IMPORTANT)
            if ($beneficiaryUser) {
                $this->sendTransactionEmail(
                    $beneficiaryUser,
                    "Transfert SOS refusé",
                    "Votre demande de transfert SOS a été rejetée par le propriétaire du compte source.",
                    null,
                    0,
                    null,
                    null,
                    $sourceAccountNumber,
                    $beneficiaryAccountNumber
                );
            }


            // *********************************************************
            // 🔥 REALTIME EVENTS REDIS / WEBSOCKET
            // *********************************************************

            // Notif bénéficiaire
            if ($beneficiaryUser) {
                event(new \App\Events\UserRealtimeNotification(
                    $beneficiaryUser->id,
                    'Transfert SOS rejeté',
                    'Votre demande SOS a été rejetée.',
                    'warning'
                ));

            }

            // Notif propriétaire
            event(new \App\Events\UserRealtimeNotification(
                $sourceUser->id,
                'Transfert SOS rejeté',
                'Vous avez rejeté une demande SOS.',
                'warning'
            ));
            
             DB::commit();
            return $this->successResponse("success",$this->showLite($transfer->id));

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse("Erreur interne : " . $e->getMessage());
        }
    }


}
