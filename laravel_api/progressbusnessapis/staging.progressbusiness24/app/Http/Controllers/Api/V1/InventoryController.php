<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends ApiController
{
    public function __construct(
        private readonly InventoryService $inventory,
    ) {
    }

    public function deposits(Request $request)
    {
        return $this->ok($this->inventory->deposits($request->user()));
    }

    public function storeDeposit(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'method' => ['nullable', 'string'],
        ]);

        $id = $this->inventory->storeDeposit($data, $request->user());

        return $this->ok(['id' => $id], 'Depot enregistre.', 201);
    }

    public function showDeposit(int $deposit)
    {
        return $this->ok($this->inventory->showDeposit($deposit));
    }

    public function deleteDeposit(int $deposit)
    {
        $this->inventory->deleteDeposit($deposit);

        return $this->ok(null, 'Depot supprime.');
    }

    public function attachUser(Request $request, int $deposit)
    {
        $data = $request->validate([
            'member_identifier' => ['required', 'string'],
            'access' => ['required', Rule::in(['entry', 'withdraw', 'all'])],
        ]);

        $this->inventory->attachUser($deposit, $data['member_identifier'], $data['access'], $request->user());

        return $this->ok(null, 'Affectation depot enregistree.');
    }

    public function deleteAffectation(int $affectationId)
    {
        $this->inventory->deleteAffectation($affectationId);

        return $this->ok(null, 'Affectation supprimee.');
    }

    public function depositInventory(int $deposit)
    {
        return $this->ok($this->inventory->depositInventory($deposit));
    }

    public function updateInventoryItem(Request $request, int $affectationId)
    {
        $data = $request->validate([
            'point' => ['required', 'numeric'],
            'stock_alert' => ['required', 'numeric'],
            'price_gros' => ['required', 'numeric'],
            'price_detail' => ['required', 'numeric'],
            'price_sold' => ['required', 'numeric'],
        ]);

        $this->inventory->updateInventoryItem($affectationId, $data);

        return $this->ok(null, 'Fiche inventaire mise a jour.');
    }

    public function movements(Request $request)
    {
        return $this->ok($this->inventory->movements($request->user()));
    }

    public function storeMovement(Request $request)
    {
        $data = $request->validate([
            'deposit_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(['entry', 'withdraw'])],
            'quantity' => ['required', 'numeric'],
            'price' => ['nullable', 'numeric'],
            'done_at' => ['nullable', 'date'],
            'expiration_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'motif' => ['nullable', Rule::in(['internal', 'transfert', 'used', 'sold'])],
        ]);

        $id = $this->inventory->storeMovement($data, $request->user());

        return $this->ok(['id' => $id], 'Mouvement de stock enregistre.', 201);
    }

    public function transfers(Request $request)
    {
        return $this->ok($this->inventory->transfers($request->user()));
    }

    public function storeTransfer(Request $request)
    {
        $data = $request->validate([
            'affectation_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric'],
            'destination_deposit_id' => ['required', 'integer'],
        ]);

        $id = $this->inventory->storeTransfer($data, $request->user());

        return $this->ok(['id' => $id], 'Transfert de stock enregistre.', 201);
    }

    public function validateTransfer(Request $request, int $transferId)
    {
        $data = $request->validate([
            'quantity_received' => ['required', 'numeric'],
        ]);

        $this->inventory->validateTransfer($transferId, (float) $data['quantity_received'], $request->user());

        return $this->ok(null, 'Transfert valide.');
    }

    public function denyTransfer(Request $request, int $transferId)
    {
        $this->inventory->denyTransfer($transferId, $request->user());

        return $this->ok(null, 'Transfert refuse et reequilibre.');
    }

    public function stockValues(Request $request)
    {
        return $this->ok($this->inventory->stockValues($request->user()));
    }
}
