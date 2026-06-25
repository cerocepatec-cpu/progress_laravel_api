<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\WithdrawRequest;
use App\Services\WithdrawLogger;
use App\Services\WithdrawService;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

class ExpireWithdrawRequests implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public function handle()
    {
        try {
            // WithdrawRequest::with('memberAccount')
            //     ->where('status', 'pending')
            //     ->whereNotNull('expires_at')
            //     ->where('expires_at', '<', now())
            WithdrawRequest::where('status', 'pending')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->whereHas('memberAccount', function ($q) {
                    $q->where('account_status', "enabled");
                })
                ->chunkById(50, function ($withdraws) {

                    foreach ($withdraws as $withdraw) {

                        try {

                            $result = DB::transaction(function () use ($withdraw) {

                                // 🔒 LOCK REQUEST
                                $withdraw = WithdrawRequest::where('id', $withdraw->id)
                                    ->lockForUpdate()
                                    ->first();

                                if (!$withdraw) {
                                    Log::error('Withdraw not found after lock', [
                                        'withdraw_id' => $withdraw?->id
                                    ]);
                                    return null;
                                }

                                if ($withdraw->status !== 'pending') {
                                    return null;
                                }

                                if ($withdraw->completed_at !== null) {
                                    return null;
                                }

                                $memberAccount = $withdraw->memberAccount;

                                if (!$memberAccount) {
                                    Log::error('Member account missing for withdraw', [
                                        'withdraw_id' => $withdraw->id,
                                        'member_account_id' => $withdraw->member_account_id,
                                    ]);
                                    return null;
                                }

                                if (!method_exists($memberAccount, 'isavailable') || !$memberAccount->isavailable()) {
                                    Log::error('Member account not available', [
                                        'withdraw_id' => $withdraw->id,
                                        'member_account_id' => $memberAccount->id,
                                    ]);
                                    return null;
                                }

                                // 💰 ROLLBACK SOLDE MEMBRE
                                $refundAmount = $withdraw->amount + $withdraw->fees;

                                $soldBefore = $memberAccount->sold;
                                $memberAccount->sold += $refundAmount;
                                $memberAccount->save();

                                // 🧾 TRANSACTION REMBOURSEMENT
                                $transaction = app(WithdrawService::class)
                                    ->createTransaction(
                                        $refundAmount,
                                        $soldBefore,
                                        $memberAccount->sold,
                                        'entry',
                                        'Expiration demande de retrait',
                                        $withdraw->member_id,
                                        $memberAccount->id,
                                        $withdraw->member_id,
                                        null,
                                        'system',
                                        0,
                                        null,
                                        null,
                                        'validated'
                                    );

                                // 🔄 UPDATE REQUEST
                                $withdraw->update([
                                    'status' => 'expired'
                                ]);

                                WithdrawLogger::log(
                                    withdraw: $withdraw,
                                    action: 'expired',
                                    actorType: 'system',
                                    actorId: null,
                                    event: 'cron',
                                    metadata: []
                                );

                                return [
                                    'withdraw'      => $withdraw,
                                    'memberAccount' => $memberAccount,
                                    'transaction'   => $transaction
                                ];
                            });

                            if (!$result) {
                                continue;
                            }

                            // 🔔 EVENTS & TEMPS RÉEL (APRÈS COMMIT)
                            $withdrawService = app(WithdrawService::class);

                            $withdraw = $withdrawService->getWithdrawWithContext(
                                $result['withdraw']->id,
                                null
                            );
                            $withdraw->member->enterprise_id=  app(\App\Http\Controllers\Controller::class)
                                    ->getEse($withdraw->member->id)->id;
                            $collectorIds = User::allCollectorsFromEnterprise(
                                $withdraw->member->enterprise_id
                            );

                            Redis::publish('requests_withdraw', json_encode([
                                'type' => 'withdraw.updated',
                                'data' => [
                                    'userId'        => $withdraw->member_id,
                                    'collector_ids' => $collectorIds,
                                    'request'       => $withdraw,
                                ]
                            ]));

                            event(new \App\Events\MemberAccountUpdated(
                                $withdraw->member_id,
                                app(\App\Http\Controllers\WekamemberaccountsController::class)
                                    ->show($result['memberAccount'])
                            ));

                            event(new \App\Events\TransactionSent(
                                $withdraw->member_id,
                                app(\App\Http\Controllers\WekaAccountsTransactionsController::class)
                                    ->show($result['transaction'])
                            ));

                            event(new \App\Events\UserRealtimeNotification(
                                $withdraw->member_id,
                                'Demande expirée',
                                'Votre demande de retrait a expiré. Le montant a été recrédité.',
                                'error'
                            ));
                        } catch (Throwable $e) {

                            Log::error('Error processing withdraw expiration', [
                                'withdraw_id' => $withdraw->id ?? null,
                                'message'     => $e->getMessage(),
                                'file'        => $e->getFile(),
                                'line'        => $e->getLine(),
                                'trace'       => substr($e->getTraceAsString(), 0, 3000),
                            ]);

                            // on continue avec les autres retraits
                            continue;
                        }
                    }
                });
        } catch (Throwable $e) {

            Log::error('ExpireWithdrawRequests job failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => substr($e->getTraceAsString(), 0, 3000),
            ]);

            // on laisse Laravel gérer le retry / failed_jobs
            throw $e;
        }
    }
}

// class ExpireWithdrawRequests implements ShouldQueue
// {
//     use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

//     public function handle()
//     {
//         WithdrawRequest::with('memberAccount')
//             ->where('status', 'pending')
//             ->whereNotNull('expires_at')
//             ->where('expires_at', '<', now())
//             ->chunkById(50, function ($withdraws) {

//                 foreach ($withdraws as $withdraw) {

//                     $result = DB::transaction(function () use ($withdraw) {

//                         $withdraw = WithdrawRequest::where('id', $withdraw->id)
//                             ->lockForUpdate()
//                             ->first();

//                         if ($withdraw->status !== 'pending') {
//                             return null;
//                         }

//                         if ($withdraw->completed_at !== null) {
//                             return null;
//                         }

//                         $memberAccount = $withdraw->memberAccount;
//                         if (!$memberAccount || !$memberAccount->isavailable()) {
//                             return null;
//                         }

                        

//                         $refundAmount = $withdraw->amount + $withdraw->fees;

//                         $soldBefore = $memberAccount->sold;
//                         $memberAccount->sold += $refundAmount;
//                         $memberAccount->save();

                        

//                         $transaction = app(WithdrawService::class)
//                             ->createTransaction(
//                                 $refundAmount,
//                                 $soldBefore,
//                                 $memberAccount->sold,
//                                 'entry',
//                                 'Expiration demande de retrait',
//                                 $withdraw->member_id,     // user_id
//                                 $memberAccount->id,
//                                 $withdraw->member_id,
//                                 null,
//                                 'system',
//                                 0,
//                                 null,
//                                 null,
//                                 'validated'
//                             );

                       

//                         $withdraw->update([
//                             'status' => 'expired'
//                         ]);

//                         WithdrawLogger::log(
//                             withdraw: $withdraw,
//                             action: 'expired',
//                             actorType: 'system',
//                             actorId: null,
//                             event: 'cron',
//                             metadata: []
//                         );

//                         return [
//                             'withdraw'      => $withdraw,
//                             'memberAccount' => $memberAccount,
//                             'transaction'   => $transaction
//                         ];
//                     });

//                     if (!$result) {
//                         continue;
//                     }

                

//                     $withdrawService = app(WithdrawService::class);

//                     $withdraw = $withdrawService->getWithdrawWithContext(
//                         $result['withdraw']->id,
//                         null
//                     );

//                     $collectorIds = User::allCollectorsFromEnterprise(
//                         $withdraw->member->enterprise_id
//                     );
//                     Redis::publish('requests_withdraw', json_encode([
//                         'type' => 'withdraw.updated',
//                         'data' => [
//                             'userId'         => $withdraw->member_id,
//                             'collector_ids'  => $collectorIds,
//                             'request'        => $withdraw,
//                         ]
//                     ]));

//                     event(new \App\Events\MemberAccountUpdated(
//                         $withdraw->member_id,
//                         app(\App\Http\Controllers\WekamemberaccountsController::class)
//                             ->show($result['memberAccount'])
//                     ));

//                     event(new \App\Events\TransactionSent(
//                         $withdraw->member_id,
//                         app(\App\Http\Controllers\WekaAccountsTransactionsController::class)
//                             ->show($result['transaction'])
//                     ));

//                     event(new \App\Events\UserRealtimeNotification(
//                         $withdraw->member_id,
//                         'Demande expirée',
//                         'Votre demande de retrait a expiré. Le montant a été recrédité.',
//                         'error'
//                     ));
//                 }
//             });
//     }
// }
