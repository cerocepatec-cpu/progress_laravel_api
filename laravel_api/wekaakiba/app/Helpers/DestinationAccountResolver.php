<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\Wekamemberaccounts;

class DestinationAccountResolver
{
    /**
     * @param string|int $value   uuid user | account_number | account_id
     * @param int|null $moneyId
     * @return Wekamemberaccounts|null
     */
    public static function resolve($value, int $moneyId): ?Wekamemberaccounts
    {
         $user = User::where('uuid', $value)->first();
        if ($user) {
            $accountFind= Wekamemberaccounts::where('user_id', $user->id)
            ->whereIn('account_status', ['enabled', 'active'])
            ->when($moneyId, fn ($q) => $q->where('money_id', $moneyId))
            ->orderBy('id')
            ->first();
            if ($accountFind) {
                return $accountFind;
            }else{
                return null;
            }
        }

        $account =Wekamemberaccounts::where('account_number', $value)
            ->where('account_status','enabled')
            ->where('money_id',$moneyId)
            ->first();

        if ($account) {
            return $account;
        }

        return null;
    }
}
