<?php

namespace App\Http\Controllers;

use App\Services\BonusService;
use Illuminate\Http\Request;

class AgentBonusController extends Controller
{
    protected $bonusService;

    public function __construct(BonusService $bonusService)
    {
        $this->bonusService = $bonusService;
    }

    /**
     * Bonus en attente groupés par monnaie
     */
    public function pending(Request $request)
    {
        $agentId = $request->user()->id;

        $grouped = $this->bonusService->getPendingByCurrency($agentId);

        $result = $grouped->map(function ($row) {
            return [
                'currency_id' => $row->currency_id,
                'currency' => $row->currency->abreviation ?? null,
                'total' => $row->total,
            ];
        });

        return response()->json([
            'bonuses_by_currency' => $result,
        ]);
    }

    /**
     * Retirer tous les bonus d’une monnaie donnée
     */
    public function withdrawByCurrency(Request $request)
    {
        $agentId = $request->user()->id;
        $currencyId = $request->currency_id;

        return response()->json(
            $this->bonusService->withdrawMonthlyBonusByCurrency($agentId, $currencyId)
        );
    }

    /**
     * Retirer toutes les monnaies (USD + CDF + ...)
     */
    public function withdrawAll(Request $request)
    {
        $agentId = $request->user()->id;

        return response()->json(
            $this->bonusService->withdrawAllCurrencies($agentId)
        );
    }
}
