<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\funds;
use App\Services\OTPService;
use Illuminate\Http\Request;
use App\Models\TransactionFee;
use App\Helpers\OtpQueueHelper;
use App\Models\WithdrawRequest;
use App\Services\WithdrawLogger;
use App\Models\wekamemberaccounts;
use App\Models\WithdrawRequestLog;
use App\Models\WithdrawRequestOtp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\WekamemberaccountsController;

class WithdrawRequestController extends Controller
{

    public function pendingCount()
    {
        $user = auth()->user();

        $query = WithdrawRequest::query()
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });

        if ($user->collector === 1) {
            // 👤 Collecteur : toutes les demandes pending non expirées
            // rien de plus à filtrer
        } else {
            // 👤 Membre : uniquement ses propres demandes
            $query->where('member_id', $user->id);
        }

        return response()->json([
            'message' => 'success',
            'count'   => $query->count(),
        ]);
    }

    /* =====================================================
    |  MEMBRE : CRÉER UNE DEMANDE
    |=====================================================*/
    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return $this->errorResponse("Vous n'êtes pas connecté.", 400);
        }

        $enterprise = $this->getEse($user->id);
        if (!$enterprise) {
            return $this->errorResponse(
                "Action terminée pour raison de sécurité. Veuillez contacter votre admin",
                400
            );
        }
        $enterpriseId = $enterprise->id;

        if ($user->collector === 1) {
            return $this->errorResponse("Opération reservée aux membres seuls", 400);
        }
        $totalAmount = 0;
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'money_id' => 'required|exists:moneys,id',
            'channel' => 'required|in:cash,mobile_money',
            'description' => 'nullable|string|max:500',
            'share_location' => 'boolean',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'duration_type' => 'required|in:duration,time_range,full_day',
            'start_time' => 'nullable|date_format:H:i',
        ]);

        $request->merge([
            'end_time' => $request->end_time ?: null,
            'duration_minutes' => $request->duration_minutes ?: null,
        ]);

        $request->validate([
            'duration_minutes' => 'nullable|required_if:duration_type,duration|integer|min:1',
            'end_time' => 'nullable|required_if:duration_type,time_range|date_format:H:i',
        ]);

        switch ($request->duration_type) {
            case 'duration':
                $expiresAt = now()->addMinutes($request->duration_minutes ?? 15);
                break;

            case 'time_range':
                $expiresAt = Carbon::today()->setTimeFromTimeString($request->end_time);
                if ($expiresAt->isPast()) {
                    return $this->errorResponse("Heure de fin invalide", 422);
                }
                break;

            case 'full_day':
                $expiresAt = now()->endOfDay();
                break;
        }


        try {
            $result = DB::transaction(function () use ($request, $user, $expiresAt, $totalAmount) {

                $memberAccount = wekamemberaccounts::where('user_id', $user->id)
                    ->where('account_status', 'enabled')
                    ->where('money_id', $request->money_id)
                    ->lockForUpdate()
                    ->first();
                if (!$memberAccount) {
                    throw new \Exception("Nous n'avons pas pu identifier le compte correspondant à cette action! Veuillez réessayer svp!");
                }

                $amount = $request->amount;

                $fees = TransactionFee::calculateFee(
                    $amount,
                    $memberAccount->money_id,
                    'withdraw'
                );

                $totalAmount = $amount + $fees['fee'];

                if ($memberAccount->sold < $totalAmount) {
                    throw new \Exception("Solde insuffisant.");
                }

                $memberAccount->decrement('sold', $totalAmount);
                $withdraw = WithdrawRequest::create([
                    'member_id' => $user->id,
                    'member_account_id' => $memberAccount->id,
                    'amount' => $amount,
                    'fees' => $fees['fee'],
                    'money_id' => $request->money_id,
                    'channel' => $request->channel,
                    'description' => $request->description,
                    'share_location' => $request->share_location,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'expires_at' => $expiresAt,
                    'sold_before' => $memberAccount->sold,
                    'sold_after' => null,
                    'uuid' => $this->getUuId("W", "RW")
                ]);

                WithdrawLogger::log(
                    withdraw: $withdraw,
                    action: 'created',
                    actorType: 'member',
                    actorId: $user->id,
                    event: 'workflow',
                    metadata: [
                        'amount' => $withdraw->amount,
                        'fees' => $fees['fee'],
                        'money_id' => $withdraw->money_id,
                        'expires_at' => $withdraw->expires_at,
                        'share_location' => $withdraw->share_location,
                    ]
                );

                return [
                    'totalAmount' => $totalAmount,
                    'withdraw' => $withdraw,
                    'memberAccount' => $memberAccount
                ];
            });

            $totalAmount = $result['totalAmount'];
            $withdraw = $this->getWithdrawWithContext($result['withdraw']->id, $user);
            $withdraw->load('money');
            $memberAccount = $result['memberAccount'];
            //sent notications to all collectors
            $collectorIds = User::allCollectorsFromEnterprise($enterpriseId);

            if (count($collectorIds) > 0) {
                $formattedAmount = number_format(
                    $withdraw->amount,
                    0,
                    ',',
                    ' '
                );
                $amountLabel = $formattedAmount . ' ' . $withdraw->money->abreviation;
                Redis::publish('collectors.notify', json_encode([
                    'type' => 'withdraw.created',
                    'collector_ids' => $collectorIds,
                    'data' => [
                        'userId' => $user->id,
                        'request' => $withdraw,
                        'withdraw_id' => $withdraw->id,
                        'amount' => $withdraw->amount,
                        'amount_label' => $amountLabel,
                    ]
                ]));
            } else {
                Log::warning(
                    "Aucun collecteur trouvé pour enterprise_id={$enterpriseId} (withdraw_id={$withdraw->id})"
                );
            }

            event(new \App\Events\UserRealtimeNotification(
                $user->id,
                'Demande de retrait public',
                "Nous avons débité temporairement votre compte d'une une somme de " . $totalAmount . $withdraw->money->abreviation,
                'success'
            ));

            $memberAccountCtrl = new WekamemberaccountsController();
            event(new \App\Events\MemberAccountUpdated(
                $user->id,
                $memberAccountCtrl->show($memberAccount)
            ));

            return $this->successResponse("success", $withdraw);
        } catch (\Throwable $th) {

            Log::error('❌ Withdraw workflow error', [
                'exception'   => get_class($th),
                'message'     => $th->getMessage(),
                'file'        => $th->getFile(),
                'line'        => $th->getLine(),
                'trace'       => collect($th->getTrace())->take(5), // éviter log trop lourd

                // 🔎 Contexte métier
                'user_id'     => auth()->id(),
                'is_collector' => auth()->user()?->collector ?? null,
                'withdraw_id' => $withdraw->id ?? null,
                'status'      => $withdraw->status ?? null,

                // 🌐 Contexte requête
                'route'       => request()->path(),
                'method'      => request()->method(),
                'ip'          => request()->ip(),
            ]);

            return $this->errorResponse(
                $th->getMessage(),
                400
            );
        }
    }

    public function cancel(WithdrawRequest $withdraw)
    {
        $user = auth()->user();

        $enterprise = $this->getEse($user->id);
        if (!$enterprise) {
            return $this->errorResponse(
                "Action terminée pour raison de sécurité. Veuillez contacter votre admin",
                400
            );
        }

        $enterpriseId = $enterprise->id;
        // 🔒 Sécurité de base
        if ($withdraw->member_id !== $user->id) {
            return $this->errorResponse("Action non autorisée.", 403);
        }

        // ⛔ Statut invalide
        if ($withdraw->status !== 'pending') {
            return $this->errorResponse("Cette demande ne peut plus être annulée.", 400);
        }

        // ⛔ Déjà prise ou expirée
        if ($withdraw->collector_id !== null) {
            return $this->errorResponse("La demande est déjà prise.", 400);
        }

        if ($withdraw->expires_at && $withdraw->expires_at->isPast()) {
            return $this->errorResponse("La demande est expirée.", 400);
        }

        try {
            $result = DB::transaction(function () use ($withdraw, $user) {

                // 🔒 Lock de la demande
                $withdraw = WithdrawRequest::where('id', $withdraw->id)
                    ->lockForUpdate()
                    ->first();

                // 🔒 Lock du compte membre
                $memberAccount = wekamemberaccounts::where('user_id', $user->id)
                    ->where('money_id', $withdraw->money_id)
                    ->lockForUpdate()
                    ->first();

                if (!$memberAccount) {
                    throw new \Exception("Compte membre introuvable.");
                }

                $soldBefore = $memberAccount->sold;
                $refundAmount = $withdraw->amount + $withdraw->fees;

                // 🔁 remboursement
                $memberAccount->increment('sold', $refundAmount);

                $soldAfter = $soldBefore + $refundAmount;

                // 🧾 écriture comptable (HISTORIQUE TRANSACTION)
                $newTransaction = $this->createTransaction(
                    amount: $refundAmount,
                    soldBefore: $soldBefore,
                    soldAfter: $soldAfter,
                    type: 'entry',
                    motif: 'withdraw_cancel',
                    userId: $user->id,
                    memberAccountId: $memberAccount->id,
                    memberId: $user->id,
                    accountId: $memberAccount->account_id ?? null,
                    operationDoneBy: 'member',
                    fees: 0,
                    phone: $user->phone ?? null,
                    adresse: $withdraw->adresse ?? null,
                    status: 'validated',
                    from_to_id: $withdraw->id,
                    sent_to_id: null
                );

                // 🔄 Mise à jour de la demande
                $withdraw->update([
                    'status'       => 'cancelled',
                    'cancelled_at' => now(),
                ]);

                // 🧾 Historique demande
                WithdrawLogger::log(
                    withdraw: $withdraw,
                    action: 'cancelled',
                    actorType: 'member',
                    actorId: $user->id,
                    event: 'workflow',
                    metadata: [
                        'refund' => $refundAmount,
                    ]
                );

                return [
                    'newtransaction' => $newTransaction,
                    'withdraw' => $withdraw,
                    'account'  => $memberAccount,
                    'refund'   => $refundAmount,
                ];
            });

            /* ============================
            🔔 EFFETS SECONDAIRES
            ============================ */

            $withdraw = $this->getWithdrawWithContext($withdraw->id, $user);
            $account  = $result['account'];
            $transaction = app(WekaAccountsTransactionsController::class)->show($result['newtransaction']);
            // 📡 Node / Redis / WS
            event(new \App\Events\TransactionSent(
                $user->id,
                $transaction
            ));

            // 🔔 Notification utilisateur
            event(new \App\Events\UserRealtimeNotification(
                $user->id,
                'Demande annulée',
                "Votre demande de retrait a été annulée et le montant a été recrédité.",
                'warning'
            ));

            // 🔄 Update compte en temps réel
            event(new \App\Events\MemberAccountUpdated(
                $account->user_id,
                app(WekamemberaccountsController::class)->show($account)
            ));

            $collectorIds = User::allCollectorsFromEnterprise($enterpriseId);


            // 🔄 Update demande for actual member
            Redis::publish('requests_withdraw', json_encode([
                'type' => 'withdraw.updated',
                'data' => [
                    'userId' => $user->id,
                    'collector_ids' => $collectorIds,
                    'request' => $withdraw
                ]
            ]));

            return $this->successResponse("success", $withdraw);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }
    protected function otpAttemptsExceeded(
        int $withdrawId,
        int $collectorId,
        int $maxAttempts = 4
    ): bool {
        return \App\Models\WithdrawRequestLog::where('withdraw_request_id', $withdrawId)
            ->where('actor_type', 'collector')
            ->where('actor_id', $collectorId)
            ->where('event', 'send_otp')
            ->where('action', 'validate')
            ->count() >= $maxAttempts;
    }

    public function validateRequest(
        WithdrawRequest $withdraw,
        OTPService $otpService
    ) {
        $user = auth()->user();

        // 🔒 Collecteur uniquement
        if ($user->collector !== 1) {
            return $this->errorResponse("Action réservée aux collecteurs.", 403);
        }

        // ⛔ Expirée
        if ($withdraw->expires_at && $withdraw->expires_at->isPast()) {
            return $this->errorResponse("Cette demande est expirée.", 400);
        }

        // ⛔ Trop de tentatives OTP
        if ($this->otpAttemptsExceeded($withdraw->id, $user->id)) {
            return $this->errorResponse(
                "Nombre maximal de tentatives OTP atteint.",
                429
            );
        }


        try {
            DB::transaction(function () use ($withdraw, $user) {

                $withdraw = WithdrawRequest::where('id', $withdraw->id)
                    ->lockForUpdate()
                    ->first();

                if ($withdraw->status !== 'taken') {
                    throw new \Exception("Cette demande ne peut pas être validée.");
                }

                if ($withdraw->collector_id !== $user->id) {
                    throw new \Exception("Action non autorisée.");
                }

                $withdraw->update([
                    'status'       => 'validated',
                    'validated_at' => now(),
                ]);

                WithdrawLogger::log(
                    withdraw: $withdraw,
                    action: 'validated',
                    actorType: 'collector',
                    actorId: $user->id,
                    event: 'workflow',
                    metadata: []
                );
            });

            /* =====================================================
           🔐 OTP — SOURCE UNIQUE : OTPService
        ===================================================== */

            $context = "withdraw_{$withdraw->id}";

            // 🔢 OTP valable 15 minutes
            $otp = $otpService->generateOtp(
                $withdraw->member_id,
                $context,
                15
            );

            // 🧾 Log tentative OTP (APRÈS succès)
            WithdrawRequestLog::create([
                'withdraw_request_id' => $withdraw->id,
                'actor_type' => 'collector',
                'actor_id' => $user->id,
                'event' => 'send_otp',
                'action' => 'validate',
            ]);

             $otpRecipient = User::find($withdraw->member_id);
            try {

                OtpQueueHelper::send(
                    $otpRecipient->user_phone,
                    $otpRecipient->collector,
                    $otpRecipient->id,
                    $otpRecipient->email,
                    $otp,
                    'sms'
                );
            } catch (\Exception $e) {
                return $this->errorResponse("Erreur lors de l'envoi de l'OTP : " . $e->getMessage());
            }
            

            /* =====================================================
           🔔 NOTIFICATIONS & SOCKETS
        ===================================================== */

            $withdraw = $this->getWithdrawWithContext($withdraw->id, $user);

            Redis::publish('requests_withdraw', json_encode([
                'type' => 'withdraw.updated',
                'data' => [
                    'collector_ids' => [$withdraw->collector_id],
                    'request' => $withdraw,
                ]
            ]));

            $memberWithdraw = $withdraw;
            $memberWithdraw['action'] = null;
            Redis::publish('requests_withdraw', json_encode([
                'type' => 'withdraw.updated',
                'data' => [
                    'userId' => $withdraw->member_id,
                    'collector_ids' => [],
                    'request' => $memberWithdraw,
                ]
            ]));

            event(new \App\Events\UserRealtimeNotification(
                $withdraw->member_id,
                'Code de validation envoyé',
                "Un code de validation a été envoyé. Transmettez-le au collecteur.",
                'info'
            ));

            event(new \App\Events\UserRealtimeNotification(
                $user->id,
                'OTP envoyé',
                "Le code OTP a été envoyé au membre.",
                'success'
            ));

            return $this->successResponse("success", $withdraw);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /* =====================================================
|  SYSTÈME : FINALISER LE RETRAIT
|=====================================================*/
    public function complete(
        Request $request,
        WithdrawRequest $withdraw,
        OTPService $otpService
    ) {
        $user = auth()->user(); // collecteur

        /* =====================================================
       🔒 SÉCURITÉ & PRÉCONDITIONS
    ===================================================== */

        if ($user->collector !== 1) {
            return $this->errorResponse("Action réservée aux collecteurs.", 403);
        }

        if ($withdraw->status !== 'validated') {
            return $this->errorResponse("Demande non validée.", 400);
        }

        if ($withdraw->collector_id !== $user->id) {
            return $this->errorResponse("Action non autorisée.", 403);
        }

        if ($withdraw->expires_at && $withdraw->expires_at->isPast()) {
            return $this->errorResponse("Demande expirée.", 400);
        }

        if ($withdraw->completed_at !== null) {
            throw new \Exception("Demande déjà complétée.");
        }

        $otp = $request->input('otp');
        if (!$otp) {
            return $this->errorResponse("OTP requis.", 422);
        }

        $context = "withdraw_{$withdraw->id}";
        if (!$otpService->verifyOtp($withdraw->member_id, $context, $otp)) {
            return $this->errorResponse("OTP invalide ou expiré.", 400);
        }

        Cache::forget("otp_{$withdraw->member_id}_{$context}");

        try {

            $result = DB::transaction(function () use ($withdraw, $user) {

                /* =====================================================
               🔒 LOCK
            ===================================================== */

                $withdraw = WithdrawRequest::where('id', $withdraw->id)
                    ->lockForUpdate()
                    ->first();

                if ($withdraw->status !== 'validated') {
                    throw new \Exception("Statut invalide.");
                }

                $memberAccount = wekamemberaccounts::find($withdraw->member_account_id);
                if (!$memberAccount || !$memberAccount->isavailable()) {
                    throw new \Exception("Compte membre indisponible.");
                }

                /* =====================================================
               👤 COMPTE COLLECTEUR
            ===================================================== */

                $collectorAccount = wekamemberaccounts::where('user_id', $user->id)
                    ->where('money_id', $withdraw->money_id)
                    ->lockForUpdate()
                    ->first();

                if (!$collectorAccount || !$collectorAccount->isavailable()) {
                    throw new \Exception("Compte collecteur indisponible.");
                }

                /* =====================================================
               💰 TRANSACTION MEMBRE (RETRAIT)
            ===================================================== */

                $totalAmount = $withdraw->amount + $withdraw->fees;

                $memberSoldBefore = $withdraw->sold_before;
                $memberSoldAfter  = $memberAccount->sold;

                $memberTransaction = $this->createTransaction(
                    $totalAmount,
                    $memberSoldBefore,
                    $memberSoldAfter,
                    'withdraw',
                    'Retrait public complété',
                    $user->id,
                    $memberAccount->id,
                    $withdraw->member_id,
                    null,
                    $user->full_name ?? $user->user_name,
                    $withdraw->fees,
                    null,
                    null,
                    'validated',
                    null,
                    $user->id // sent_to_id = collecteur
                );

                /* =====================================================
               💰 TRANSACTION COLLECTEUR (DÉPÔT)
            ===================================================== */

                $collectorSoldBefore = $collectorAccount->sold;
                $collectorAccount->sold += $withdraw->amount;
                $collectorAccount->save();

                $collectorTransaction = $this->createTransaction(
                    $withdraw->amount,
                    $collectorSoldBefore,
                    $collectorAccount->sold,
                    'entry',
                    'Dépôt retrait public',
                    $withdraw->member_id,
                    $collectorAccount->id,
                    $user->id,
                    null,
                    $withdraw->member->name,
                    0,
                    null,
                    null,
                    'validated',
                    $withdraw->member_id, // from_to_id
                    null
                );

                /* =====================================================
               🏦 FRAIS & CAISSE AUTOMATIQUE
            ===================================================== */

                $automatiFund = null;
                $feeEnteredInFund = 0;

                if ($withdraw->fees > 0) {
                    $automatiFund = funds::getAutomaticFund($withdraw->money_id);
                    if (!$automatiFund) {
                        throw new \Exception("Aucune caisse configurée.");
                    }

                    $automatiFund->sold += $withdraw->fees;
                    $automatiFund->save();
                    $feeEnteredInFund = $withdraw->fees;
                }

                /* =====================================================
               🎁 BONUS COLLECTEUR
            ===================================================== */

                if ($automatiFund && $withdraw->fees > 0) {
                    $percentage = (float) $user->collection_percentage;
                    if ($percentage > 0) {
                        $bonus = ($withdraw->fees * $percentage) / 100;

                        if ($bonus > 0) {
                            $automatiFund->sold -= $bonus;
                            $automatiFund->save();
                            $feeEnteredInFund -= $bonus;

                            app(\App\Services\BonusService::class)->createBonus(
                                $user->id,
                                $memberTransaction,
                                $bonus,
                                $collectorAccount->account_number,
                                "Bonus collecteur ({$percentage}%)."
                            );
                        }
                    }
                }

                /* =====================================================
               🧾 HISTORIQUE CAISSE
            ===================================================== */

                if ($automatiFund && $feeEnteredInFund > 0) {
                    $this->createLocalRequestHistory(
                        $user->id,
                        $automatiFund->id,
                        $feeEnteredInFund,
                        'Frais retrait public',
                        'entry',
                        $withdraw->id,
                        null,
                        null,
                        $automatiFund->sold,
                        null,
                        'weka-akiba',
                        'withdraw_fees',
                        null,
                        null,
                        $memberAccount->id,
                        'approvment'
                    );
                }

                /* =====================================================
               🔄 FINALISATION REQUEST
            ===================================================== */

                $withdraw->update([
                    'status'       => 'completed',
                    'completed_at' => now(),
                    'sold_after'   => $memberSoldAfter,
                ]);

                WithdrawLogger::log(
                    withdraw: $withdraw,
                    action: 'completed',
                    actorType: 'collector',
                    actorId: $user->id,
                    event: 'workflow',
                    metadata: []
                );

                return [
                    'collectorAccount' => $collectorAccount,
                    "memberTransaction" => $memberTransaction,
                    "collectorTransaction" => $collectorTransaction
                ];
            });

            /* =====================================================
           🔔 EVENTS & SOCKETS
        ===================================================== */

            $withdraw = $this->getWithdrawWithContext($withdraw->id, $user);

            Redis::publish('requests_withdraw', json_encode([
                'type' => 'withdraw.updated',
                'data' => [
                    'userId' => $withdraw->member_id,
                    'collector_ids' => [$withdraw->collector_id],
                    'request' => $withdraw,
                ]
            ]));

            $memberAccountCtrl = new WekamemberaccountsController();

            event(new \App\Events\MemberAccountUpdated(
                $user->id,
                $memberAccountCtrl->show($result['collectorAccount'])
            ));

            event(new \App\Events\TransactionSent(
                $withdraw->member_id,
                app(WekaAccountsTransactionsController::class)
                    ->show($result['memberTransaction'])
            ));

            event(new \App\Events\TransactionSent(
                $user->id,
                app(WekaAccountsTransactionsController::class)
                    ->show($result['collectorTransaction'])
            ));

            event(new \App\Events\UserRealtimeNotification(
                $withdraw->member_id,
                'Retrait complété',
                "Votre retrait a été effectué avec succès.",
                'info'
            ));

            return $this->successResponse(
                "success",
                $withdraw
            );
        } catch (\Throwable $e) {
            return $this->errorResponse("Erreur : " . $e->getMessage(), 400);
        }
    }

    /* =====================================================
     |  COLLECTEUR : VOIR DEMANDES DISPONIBLES
     |=====================================================*/
    public function available(Request $request)
    {
        $user = auth()->user();

        $query = WithdrawRequest::query()
            ->with([
                'money:id,abreviation,money_name',
                'member:id,name,user_phone,email',
                'collector:id,name,user_phone,email'
            ])
            ->latest();

        if ($user->collector === 1) {
            $query->whereIn('status', ['pending', 'taken', 'validated'])
                ->where('expires_at', '>', now());
        } else {
            $query->where('member_id', $user->id);
        }

        $withdraws = $query->paginate(15);

        // ✅ SOLUTION UNIVERSELLE
        foreach ($withdraws as $withdraw) {
            $withdraw->action = $this->resolveAction($withdraw, $user);
            $withdraw->member_phone = $withdraw->member?->user_phone;
            $withdraw->collector_phone = $withdraw->collector?->user_phone;

            $withdraw->member_email = $withdraw->member?->email;
            $withdraw->collector_email = $withdraw->collector?->email;
        }

        return response()->json([
            'message' => 'success',
            'data' => $withdraws
        ]);
    }

    public function resendOtp(
        WithdrawRequest $withdraw,
        OTPService $otpService
    ) {
        $user = auth()->user(); // collecteur

        /* =====================================================
       🔒 SÉCURITÉ
    ===================================================== */

        if ($user->collector !== 1) {
            return $this->errorResponse(
                "Action réservée aux collecteurs.",
                403
            );
        }

        if ($withdraw->collector_id !== $user->id) {
            return $this->errorResponse(
                "Vous n'êtes pas autorisé à renvoyer cet OTP.",
                403
            );
        }

        if ($withdraw->status !== 'validated') {
            return $this->errorResponse(
                "Impossible de renvoyer l'OTP à ce stade.",
                400
            );
        }

        if ($withdraw->expires_at && $withdraw->expires_at->isPast()) {
            return $this->errorResponse(
                "Cette demande est expirée.",
                400
            );
        }

        /* =====================================================
       ⛔ LIMITATION DES TENTATIVES
    ===================================================== */

        if ($this->otpAttemptsExceeded($withdraw->id, $user->id)) {
            return $this->errorResponse(
                "Nombre maximal de renvois OTP atteint.",
                429
            );
        }

        /* =====================================================
       🔐 GÉNÉRATION OTP
    ===================================================== */

        $context = "withdraw_{$withdraw->id}";

        // 🔢 Génération OTP (15 minutes)
        $otp = $otpService->generateOtp(
            $withdraw->member_id,
            $context,
            15
        );

        /* =====================================================
       🧾 LOG OTP
    ===================================================== */

        WithdrawRequestLog::create([
            'withdraw_request_id' => $withdraw->id,
            'actor_type'          => 'collector',
            'actor_id'            => $user->id,
            'event'               => 'send_otp',
            'action'              => 'resend',
        ]);

        /* =====================================================
       📩 ENVOI OTP AU MEMBRE
    ===================================================== */

          $otpRecipient = User::find($withdraw->member_id);
            try {

                OtpQueueHelper::send(
                    $otpRecipient->user_phone,
                    $otpRecipient->collector,
                    $otpRecipient->id,
                    $otpRecipient->email,
                    $otp,
                    'sms'
                );
            } catch (\Exception $e) {
                return $this->errorResponse("Erreur lors de l'envoi de l'OTP : " . $e->getMessage());
            }

        /* =====================================================
       🔔 NOTIFICATIONS
    ===================================================== */

        // 🔔 Membre
        event(new \App\Events\UserRealtimeNotification(
            $withdraw->member_id,
            'Nouveau code OTP',
            "Un nouveau code de validation vous a été envoyé.",
            'info'
        ));

        // 🔔 Collecteur
        event(new \App\Events\UserRealtimeNotification(
            $user->id,
            'OTP renvoyé',
            "Le code OTP a été renvoyé au membre.",
            'success'
        ));

        return $this->successResponse(
            "success",
            null
        );
    }

    public function show(int $id)
    {
        $user = auth()->user();

        try {
            $withdraw = $this->getWithdrawWithContext($id, $user);

            return response()->json([
                'message' => 'success',
                'data' => $withdraw
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Demande introuvable'
            ], 404);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {

            return response()->json([
                'message' => $e->getMessage()
            ], $e->getStatusCode());
        } catch (\Throwable $e) {

            // 🔥 LOG IMPORTANT
            Log::error('Withdraw show error', [
                'withdraw_id' => $id,
                'user_id'     => $user?->id,
                'collector'   => $user?->collector,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erreur interne'
            ], 500);
        }
    }

    private function getWithdrawWithContext(int $id, $user): WithdrawRequest
    {
        $withdraw = WithdrawRequest::with([
            'money:id,abreviation,money_name',
            'member:id,name',
            'collector:id,name'
        ])->findOrFail($id);

        // 🔒 Sécurité d’accès
        if ($user->collector !== 1 && $withdraw->member_id !== $user->id) {
            throw new \Exception('Accès refusé');
        }

        // ➕ Enrichissement métier
        $withdraw->action = $this->resolveAction($withdraw, $user);

        return $withdraw;
    }



    private function resolveAction(WithdrawRequest $withdraw, $user): ?string
    {
        // ⛔ Expirée = aucune action
        if ($withdraw->expires_at && $withdraw->expires_at->isPast()) {
            return null;
        }

        // 👤 MEMBER (propriétaire de la demande)
        if ($user->collector !== 1 && $withdraw->member_id === $user->id) {
            return $withdraw->status === 'pending'
                ? 'can_cancel'
                : null;
        }

        // 👤 COLLECTOR
        if ($user->collector === 1) {

            // 🟡 1. Demande libre → tous peuvent la prendre
            if ($withdraw->status === 'pending' && $withdraw->collector_id === null) {
                return 'can_take';
            }

            // ⛔ Si la demande a été prise par un AUTRE collecteur
            if ($withdraw->collector_id !== null && $withdraw->collector_id !== $user->id) {
                return null;
            }

            // 🟠 2. Collecteur gagnant → validation
            if ($withdraw->status === 'taken' && $withdraw->collector_id === $user->id) {
                return 'can_validate';
            }

            // 🔵 3. Validation → finalisation
            if ($withdraw->status === 'validated' && $withdraw->collector_id === $user->id) {
                return 'can_complete';
            }
        }

        return null;
    }


    /* =====================================================
     |  COLLECTEUR : PRENDRE LA DEMANDE (LOCK)
     |=====================================================*/
    public function take(WithdrawRequest $withdraw)
    {
        $user = auth()->user();
        $enterprise = $this->getEse($user->id);
        if (!$enterprise) {
            return $this->errorResponse(
                "Action terminée pour raison de sécurité. Veuillez contacter votre admin",
                400
            );
        }

        $enterpriseId = $enterprise->id;
        // 🔒 Sécurité : uniquement collecteur
        if ($user->collector !== 1) {
            return $this->errorResponse("Action réservée aux collecteurs.", 403);
        }

        // ⛔ Déjà expirée
        if ($withdraw->expires_at && $withdraw->expires_at->isPast()) {
            return $this->errorResponse("Cette demande est expirée.", 400);
        }

        try {
            $result = DB::transaction(function () use ($withdraw, $user) {

                // 🔒 Lock de la demande
                $withdraw = WithdrawRequest::where('id', $withdraw->id)
                    ->lockForUpdate()
                    ->first();

                // ⛔ Vérifications métier (dans la transaction)
                if ($withdraw->status !== 'pending') {
                    throw new \Exception("Cette demande n'est plus disponible.");
                }

                if ($withdraw->collector_id !== null) {
                    throw new \Exception("Cette demande a déjà été prise.");
                }

                // ✅ Attribution du collecteur
                $withdraw->update([
                    'status'       => 'taken',
                    'collector_id' => $user->id,
                    'taken_at'     => now(),
                ]);

                // 🧾 Log métier
                WithdrawLogger::log(
                    withdraw: $withdraw,
                    action: 'taken',
                    actorType: 'collector',
                    actorId: $user->id,
                    event: 'workflow',
                    metadata: []
                );

                return $withdraw;
            });

            /* =====================================================
            🔔 EFFETS SECONDAIRES (HORS TRANSACTION)
            ===================================================== */

            // 🔄 Charger relations + action
            $withdraw = $this->getWithdrawWithContext($result->id, $user);

            // 📡 Redis / Socket / Collectors + Owner
            Redis::publish('requests_withdraw', json_encode([
                'type' => 'withdraw.updated',
                'data' => [
                    'collector_ids' => [$user->id],
                    'request' => $withdraw,
                ]
            ]));

            $memberWithdraw = $withdraw;
            $memberWithdraw['action'] = null;
            Redis::publish('requests_withdraw', json_encode([
                'type' => 'withdraw.updated',
                'data' => [
                    'userId' => $withdraw->member_id,
                    'collector_ids' => [],
                    'request' => $memberWithdraw,
                ]
            ]));

            // 🔔 Notification propriétaire
            event(new \App\Events\UserRealtimeNotification(
                $withdraw->member_id,
                'Demande prise en charge',
                "Votre demande de retrait a été prise en charge par un collecteur.",
                'info'
            ));

            // 🔔 Notification collecteur
            event(new \App\Events\UserRealtimeNotification(
                $user->id,
                'Demande prise',
                "Vous avez pris en charge la demande #{$withdraw->id}.",
                'success'
            ));

            // 🔄 Event applicatif

            //  $collectorIds = User::allCollectorsFromEnterprise($enterpriseId);

            // // 🔄 Update demande for actual member
            //  Redis::publish('requests_withdraw', json_encode([
            //         'type' => 'withdraw.updated',
            //         'data' =>[
            //             'userId'=>$user->id,
            //             'collector_ids' => $collectorIds,
            //             'request'=>$withdraw
            //         ]
            // ]));

            return $this->successResponse(
                "success",
                $withdraw
            );
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /* =====================================================
     |  MEMBRE : CONFIRMATION OTP
     |=====================================================*/
    public function confirmMember(Request $request, WithdrawRequest $withdraw)
    {
        $request->validate(['otp' => 'required|string']);

        $user = auth()->user();

        if ($withdraw->member_id !== $user->id) {
            abort(403);
        }

        $otp = WithdrawRequestOtp::where([
            'withdraw_request_id' => $withdraw->id,
            'target' => 'member'
        ])->first();

        if (!$otp || !$otp->validateOtp($request->otp)) {
            return response()->json(['message' => 'OTP invalide'], 422);
        }

        WithdrawLogger::log($withdraw, 'member_validated', 'member', $user->id);

        return response()->json(['message' => 'success']);
    }

    /* =====================================================
     |  COLLECTEUR : CONFIRMATION OTP
     |=====================================================*/
    public function confirmCollector(Request $request, WithdrawRequest $withdraw)
    {
        $request->validate(['otp' => 'required|string']);

        $collector = auth()->user();

        if ($withdraw->collector_id !== $collector->id) {
            abort(403);
        }

        $otp = WithdrawRequestOtp::where([
            'withdraw_request_id' => $withdraw->id,
            'target' => 'collector'
        ])->first();

        if (!$otp || !$otp->validateOtp($request->otp)) {
            return response()->json(['message' => 'OTP invalide'], 422);
        }

        $withdraw->markAsValidated();

        WithdrawLogger::log($withdraw, 'collector_validated', 'collector', $collector->id);

        return response()->json(['message' => 'success']);
    }
}
