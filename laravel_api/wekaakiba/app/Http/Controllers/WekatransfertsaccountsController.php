<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\UsersEnterprise;
use App\Models\wekamemberaccounts;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\wekatransfertsaccounts;
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
            if (!$destinationId) return $this->errorResponse('vous devez fournir un compte bénéficiaire svp!', 422);
            if (!$sourceId) return $this->errorResponse('vous devez fournir le compte source svp!', 422);

            // 🔥 FINDBY corrigé
            $sourceAccount = wekamemberaccounts::where('account_number', $sourceId)->first();
            $destinationAccount = wekamemberaccounts::find($destinationId);

            if (!$destinationAccount)
                return $this->errorResponse('compte bénéficiaire introuvable!', 422);

            if (!$sourceAccount)
                return $this->errorResponse('compte source introuvable!', 422);

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

        return response()->json([
            'data' => $items,
            'current_page' => $ids->currentPage(),
            'last_page' => $ids->lastPage(),
            'per_page' => $ids->perPage(),
            'total' => $ids->total(),
        ]);
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
                'src.account_number as source_account_number',
                'dest.account_number as destination_account_number',
                'sc.abreviation as source_currency_code',
                'dc.abreviation as destination_currency_code',
                'u1.name as done_by_name',
                'u2.name as validated_by_name',
                'e.name as enterprise_name',
                'src.user_id as source_owner_id'
            ])
            ->leftJoin('wekamemberaccounts as src', 'src.id', '=', 'wekatransfertsaccounts.source')
            ->leftJoin('wekamemberaccounts as dest', 'dest.id', '=', 'wekatransfertsaccounts.destination')
            ->leftJoin('moneys as sc', 'sc.id', '=', 'wekatransfertsaccounts.source_currency_id')
            ->leftJoin('moneys as dc', 'dc.id', '=', 'wekatransfertsaccounts.destination_currency_id')
            ->leftJoin('users as u1', 'u1.id', '=', 'wekatransfertsaccounts.done_by')
            ->leftJoin('users as u2', 'u2.id', '=', 'wekatransfertsaccounts.validated_by')
            ->leftJoin('enterprises as e', 'e.id', '=', 'wekatransfertsaccounts.enterprise')
            ->where('wekatransfertsaccounts.id', $id)
            ->first();

        if (!$transfer) {
            return $this->errorResponse("Transfert introuvable", 404);
        }

        // Permission → il doit être propriétaire du compte source
        $transfer->can_validate = ($user->id == $transfer->source_owner_id);
        return $transfer;
    }

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
            $destination
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

                event(new \App\Events\MemberAccountUpdated(
                    $beneficiaryUser->id,
                    $destination
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
