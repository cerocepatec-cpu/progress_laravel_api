<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Models\wekamemberaccounts;
use Carbon\Carbon;

class ReportService
{
    /**
     * 📅 Date de génération (timezone utilisateur)
     */
    public static function generatedAt(User $user): Carbon
    {
        return now('UTC')->setTimezone(
            $user->timezone ?? config('app.timezone')
        );
    }

    /**
     * 💰 Totaux par monnaie (SOURCE UNIQUE)
     */
    public static function totalsByCurrency(User $user): array
    {
        return wekamemberaccounts::getBalancesByUser($user);
    }

    /**
     * 🏦 Snapshot des comptes (léger)
     */
    public static function accountsSnapshot(int $userId): array
    {
        return wekamemberaccounts::query()
            ->leftJoin('moneys as M', 'wekamemberaccounts.money_id', '=', 'M.id')
            ->where('wekamemberaccounts.user_id', $userId)
            ->where('wekamemberaccounts.account_status', 'enabled')
            ->get([
                'wekamemberaccounts.id',
                'wekamemberaccounts.account_number',
                'wekamemberaccounts.sold',
                'M.abreviation as currency',
            ])
            ->map(function ($account) {
                return [
                    'account_id' => $account->id,
                    'account_number' => $account->account_number,
                    'currency' => $account->currency,
                    'available_balance' => (float) $account->sold,
                ];
            })
            ->toArray();
    }
}
