<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\AccountingService;
use App\Services\MlmService;
use Illuminate\Http\Request;

class AccountingController extends ApiController
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly MlmService $mlm,
    ) {
    }

    public function summary(Request $request)
    {
        return $this->ok($this->accounting->summary($request->user()));
    }

    public function reports(Request $request)
    {
        return $this->ok($this->accounting->reports($request->user(), $request->all()));
    }

    public function storeEntry(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric'],
            'wording' => ['required', 'string'],
            'provenance_entry' => ['nullable', 'string'],
            'point' => ['nullable', 'numeric'],
        ]);

        $id = $this->accounting->storeEntry($request->user(), $data);

        return $this->ok(['id' => $id], 'Entree comptable enregistree.', 201);
    }

    public function storeWayout(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric'],
            'wording' => ['required', 'string'],
            'destination' => ['nullable', 'string'],
        ]);

        $id = $this->accounting->storeWayout($request->user(), $data);

        return $this->ok(['id' => $id], 'Sortie comptable enregistree.', 201);
    }

    public function transfer(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric'],
            'wording' => ['required', 'string'],
            'member_id_destination' => ['required', 'string'],
        ]);

        $result = $this->accounting->transfer($request->user(), $data);

        return $this->ok($result, 'Transfert effectue.');
    }

    public function memberLedger(string $identifier)
    {
        $member = $this->mlm->resolveMember($identifier);

        return $this->ok($this->accounting->memberLedger($member));
    }

    public function cashOperations(Request $request)
    {
        return $this->ok($this->accounting->cashOperations($request->user()));
    }

    public function showCashOperation(Request $request, int $cashId)
    {
        return $this->ok($this->accounting->cashOperation($request->user(), $cashId));
    }

    public function storeCashOperation(Request $request)
    {
        $data = $request->validate([
            'cash_mount' => ['required', 'numeric'],
            'cash_mode' => ['required', 'string'],
            'bank_name' => ['nullable', 'string'],
            'own_bank_account' => ['nullable', 'string'],
            'bank_account_number' => ['nullable', 'string'],
            'mobile_number' => ['nullable', 'string'],
        ]);

        $cashId = $this->accounting->storeCashOperation($request->user(), $data);

        return $this->ok(['cash_id' => $cashId], 'Operation CASH creee.', 201);
    }

    public function validateCashOperation(int $cashId)
    {
        $this->accounting->validateCashOperation($cashId);

        return $this->ok(null, 'Operation CASH validee.');
    }

    public function deleteCashOperation(int $cashId)
    {
        $this->accounting->deleteCashOperation($cashId);

        return $this->ok(null, 'Operation CASH supprimee.');
    }

    public function vipPackets(Request $request)
    {
        return $this->ok($this->accounting->vipPackets($request->user()));
    }

    public function buyVipPackets(Request $request)
    {
        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1'],
            'total' => ['required', 'numeric', 'gt:0'],
        ]);

        return $this->ok(
            $this->accounting->buyVipPackets($request->user(), $data),
            'Coffret VIP achete avec succes.',
            201
        );
    }
}
