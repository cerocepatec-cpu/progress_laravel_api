<?php
namespace App\Services;

use App\Models\serdipays;
use App\Models\TransactionFee;
use App\Models\wekamemberaccounts;
use Illuminate\Support\Facades\Log;

class FeesService
{
    public function gettingTransactionFees($amount, $accountId, $type)
    {
        $account = wekamemberaccounts::find($accountId);
        if (!$account) return 0;

        $wekaFee = 0;        // montant fixe
        $serdiPercent = 0;   // pourcentage

        // 1. Déterminer l’opération WEKA
        $operation = match ($type) {
            'mobile_deposit'     => 'send',
            'mobile_withdraw'    => 'withdraw',
            'account_to_account' => 'withdraw',
            default => null,
        };

        if (!$operation) return 0;

        // 2. Frais WEKA (FIXE)
        $fee = TransactionFee::calculateFee($amount, $account->money_id, $operation);
        if ($fee && isset($fee['fee'])) {
            $wekaFee = $fee['fee']; // ex. 0.50 USD
        }

        // 3. Frais SERDIPAY (POURCENTAGE)
        try {
            $cfg = serdipays::configFor("test");

            if ($type === 'mobile_deposit') {
                $serdiPercent = $cfg->c2b_fees; // ex. 2.5 (%)
            }

            if ($type === 'mobile_withdraw') {
                $serdiPercent = $cfg->b2c_fees; // ex. 3 (%)
            }

        } catch (\Exception $e) {
            Log::error("SERDIPAY CONFIG ERROR: {$e->getMessage()}");
            $serdiPercent = 0;
        }

        // 4. Calcul TOTAL
        $serdiFee = ($amount * $serdiPercent) / 100; // montant depuis %
        $total = $wekaFee + $serdiFee;

        return round($total, 2);
    }
}
