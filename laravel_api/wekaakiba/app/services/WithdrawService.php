<?php

namespace App\Services;

use App\Models\WithdrawRequest;
use App\Models\wekamemberaccounts;
use App\Models\wekaAccountsTransactions;
use App\Models\User;
use App\Services\WithdrawLogger;
use Illuminate\Support\Facades\DB;

class WithdrawService
{
    /* =====================================================
       🧾 CRÉATION TRANSACTION COMPTABLE
    ===================================================== */

    public function createTransaction(
        float $amount,
        float $soldBefore,
        float $soldAfter,
        string $type,
        string $motif,
        int $userId,
        int $memberAccountId,
        int $memberId,
        ?int $accountId,
        string $operationDoneBy,
        float $fees,
        ?string $phone,
        ?string $adresse,
        string $status = 'validated',
        ?int $fromToId = null,
        ?int $sentToId = null
    ): wekaAccountsTransactions {

        return wekaAccountsTransactions::create([
            'amount'             => $amount,
            'sold_before'        => $soldBefore,
            'sold_after'         => $soldAfter,
            'type'               => $type,
            'motif'              => $motif,
            'user_id'            => $userId,
            'member_account_id'  => $memberAccountId,
            'member_id'          => $memberId,
            'enterprise_id'      => app()->make('App\Http\Controllers\Controller')
                                          ->getEse($userId)['id'],
            'done_at'            => now()->toDateString(),
            'account_id'         => $accountId,
            'operation_done_by'  => $operationDoneBy,
            'uuid'               => app()->make('App\Http\Controllers\Controller')
                                          ->getUuId('WT', 'WK'),
            'fees'               => $fees,
            'transaction_status' => $status,
            'phone'              => $phone,
            'adresse'            => $adresse,
            'from_to_id'         => $fromToId,
            'sent_to_id'         => $sentToId,
        ]);
    }

    /* =====================================================
       🔎 WITHDRAW ENRICHI (relations + actions)
    ===================================================== */

    public function getWithdrawWithContext(int $withdrawId, ?User $user = null): WithdrawRequest
    {
        $withdraw = WithdrawRequest::with([
            'money:id,abreviation,money_name',
            'member:id,name',
            'collector:id,name',
            'memberAccount'
        ])->findOrFail($withdrawId);

        if ($user) {
            $withdraw->action = $this->resolveAction($withdraw, $user);
        }

        return $withdraw;
    }

    /* =====================================================
       🎯 RÉSOLUTION DES ACTIONS (FRONT)
    ===================================================== */

    public function resolveAction(WithdrawRequest $withdraw, User $user): ?string
    {
        // ⛔ Expirée
        if ($withdraw->expires_at && $withdraw->expires_at->isPast()) {
            return null;
        }

        // 👤 MEMBRE
        if ($user->collector !== 1 && $withdraw->member_id === $user->id) {
            return $withdraw->status === 'pending'
                ? 'can_cancel'
                : null;
        }

        // 👤 COLLECTEUR
        if ($user->collector === 1) {

            if ($withdraw->status === 'pending' && !$withdraw->collector_id) {
                return 'can_take';
            }

            if ($withdraw->collector_id !== null && $withdraw->collector_id !== $user->id) {
                return null;
            }

            if ($withdraw->status === 'taken') {
                return 'can_validate';
            }

            if ($withdraw->status === 'validated') {
                return 'can_complete';
            }
        }

        return null;
    }

    /* =====================================================
       📝 LOG MÉTIER
    ===================================================== */

    public function log(
        WithdrawRequest $withdraw,
        string $action,
        string $actorType,
        ?int $actorId,
        string $event,
        array $metadata = []
    ): void {
        WithdrawLogger::log(
            withdraw: $withdraw,
            action: $action,
            actorType: $actorType,
            actorId: $actorId,
            event: $event,
            metadata: $metadata
        );
    }
}
