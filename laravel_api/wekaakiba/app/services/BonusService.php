<?php

namespace App\Services;

use App\Models\AgentBonus;
use App\Models\User;
use App\Models\wekamemberaccounts;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BonusService
{
    /**
     * Créer un bonus
     */
    public function createBonus($agentId, $transaction, $amount,$accountNumber, $description = null)
    {
        return AgentBonus::create([
            'agent_id'       => $agentId,
            'transaction_id' => $transaction->id,
            'transaction_type' => $transaction->type,
            'currency_id'     => wekamemberaccounts::getMoneyByAccountNumber($accountNumber)->id??0,
            'amount'          => $amount,
            'status'          => 'pending',
            'month_key'       => now()->format('Y-m'),
            'description'     => $description,
        ]);
    }

    /**
     * Total des bonus en attente groupés par monnaie
     */
   public function getPendingByCurrency($agentId)
    {
        return AgentBonus::where('agent_id', $agentId)
            ->where('status', 'pending')
            ->with('currency')
            ->select('currency_id', DB::raw('SUM(amount) as total'))
            ->groupBy('currency_id')
            ->get();
    }

    /**
     * Bonus mensuels en attente groupés par monnaie
     */
    public function getMonthlyPendingGrouped($agentId)
    {
        return AgentBonus::where('agent_id', $agentId)
        ->where('status', 'pending')
        ->where('month_key', now()->format('Y-m'))
        ->select('currency_id', DB::raw('SUM(amount) as total'))
        ->groupBy('currency_id')
        ->get();
    }

    /**
     * Retrait mensuel des bonus pour une monnaie donnée
     */
    public function withdrawMonthlyBonusByCurrency($agentId, $currencyId, $accountId = null)
    {
        return DB::transaction(function () use ($agentId, $currencyId, $accountId) {

            // ON RETIRE TOUS LES MOIS NON PAYÉS JUSQU’AU MOIS PRÉCÉDENT
            $currentMonthKey = now()->format('Y-m');

            $bonuses = AgentBonus::where('agent_id', $agentId)
                ->where('currency_id', $currencyId)
                ->where('status', 'pending')
                ->where('month_key', '<', $currentMonthKey) // 🔥 retire TOUT sauf le mois en cours
                ->lockForUpdate()
                ->get();

            if ($bonuses->isEmpty()) {
                return [
                    'status' => 400,
                    "error"=>"Aucun bonus disponible valides pour cette monnaie.",
                    'message' => "error"
                ];
            }

            // TOTAL DU BONUS À RETIRER
            $totalBonus = $bonuses->sum('amount');

            // DÉTERMINATION DE LA PÉRIODE (motif)
            $oldestMonth = $bonuses->min('month_key');
            $newestMonth = $bonuses->max('month_key');

            $prettyOldest = Carbon::createFromFormat('Y-m', $oldestMonth)->translatedFormat('F Y');
            $prettyNewest = Carbon::createFromFormat('Y-m', $newestMonth)->translatedFormat('F Y');

            if ($prettyOldest === $prettyNewest) {
                $periodMotif = "du mois de " . ucfirst($prettyNewest);
            } else {
                $periodMotif = "des mois de " . ucfirst($prettyOldest) . " à " . ucfirst($prettyNewest);
            }

            // 2. CHOIX DU COMPTE
            if ($accountId) {

                $account = wekamemberaccounts::where('id', $accountId)
                    ->where('user_id', $agentId)
                    ->lockForUpdate()
                    ->first();

                if (!$account) {
                    throw new \Exception("Compte introuvable.");
                }

                if ($account->money_id != $currencyId) {
                    throw new \Exception("La monnaie du compte ne correspond pas à celle des bonus.");
                }

            } else {

                $account = wekamemberaccounts::where('user_id', $agentId)
                    ->where('money_id', $currencyId)
                    ->lockForUpdate()
                    ->first();

                if (!$account) {
                    throw new \Exception("Aucun compte trouvé pour cette monnaie.");
                }
            }

            // CONTEXTE AGENT
            $agent = User::find($agentId);

            // 3. UPDATE SOLDE
            $oldSold = $account->sold;
            $newSold = $oldSold + $totalBonus;

            $account->update(['sold' => $newSold]);

            // 4. MOTIF AVEC LA PÉRIODE
            $motif = "Retrait des bonus cumulés $periodMotif";

            // 5. CREATION TRANSACTION
            $controller = app(\App\Http\Controllers\Controller::class);

            $controller->createTransaction(
                $totalBonus,
                $oldSold,
                $newSold,
                "entry",
                $motif,
                $agentId,
                $account->id,
                $agentId,
                null,
                $agent->full_name ?: $agent->user_name,
                0,
                $agent->user_phone,
                $agent->adress
            );

            // 6. MARQUER LES BONUS COMME PAYÉS
            foreach ($bonuses as $bonus) {
                $bonus->update([
                    'status'        => 'paid',
                    'paid_at'       => now(),
                    'withdrawn_at'  => now(),
                ]);
            }

            return [
                'message'   =>'success',
                'status'          =>200,
                'currency_id'     => $currencyId,
                'total_paid'      => $totalBonus,
                'period'          => [
                    'from' => $prettyOldest,
                    'to'   => $prettyNewest,
                ],
                'credited_account'=> $account->id,
            ];
        });
    }

    /**
     * Retrait de TOUTES les monnaies (groupé)
     */
    public function withdrawAllCurrencies($agentId)
    {
        return DB::transaction(function () use ($agentId) {

            // Récupération de TOUTES les monnaies ayant des bonus "pending"
            $currentMonthKey = now()->format('Y-m');

            $grouped = AgentBonus::where('agent_id', $agentId)
                ->where('status', 'pending')
                ->where('month_key', '<', $currentMonthKey) // 🔥 plusieurs mois
                ->select('currency_id')
                ->groupBy('currency_id')
                ->get();

            if ($grouped->isEmpty()) {
                return [
                    'status' => false,
                    'message' => "Aucun bonus à retirer pour aucune monnaie."
                ];
            }

            $results = [];

            foreach ($grouped as $row) {

                // Appelle TA méthode améliorée, qui gère tout
                $results[] = $this->withdrawMonthlyBonusByCurrency(
                    $agentId,
                    $row->currency_id,
                    null  // laisse le service choisir le bon compte automatiquement
                );
            }

            return [
                'status' => true,
                'results' => $results
            ];
        });
    }

}
