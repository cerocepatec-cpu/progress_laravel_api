<?php

namespace App\Services;

use App\Models\Deposit;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function deposits(User $actor)
    {
        $query = DB::table('deposits')->orderBy('id');

        if (! $this->canManageAll($actor)) {
            $query->whereIn('id', function ($sub) use ($actor): void {
                $sub->select('deposit_id')
                    ->from('depositusers')
                    ->where('user_id', $actor->member_id);
            });
        }

        return $query->get();
    }

    public function storeDeposit(array $payload, User $actor): int
    {
        if (! empty($payload['id'])) {
            DB::table('deposits')
                ->where('id', (int) $payload['id'])
                ->update([
                    'name' => $payload['name'],
                    'description' => $payload['description'] ?? null,
                    'type' => $payload['type'] ?? 'group',
                    'method' => $payload['method'] ?? 'fifo',
                ]);

            return (int) $payload['id'];
        }

        $nextId = (int) (DB::table('deposits')->max('id') ?? 0) + 1;

        DB::table('deposits')->insert([
            'id' => $nextId,
            'added_by' => $actor->member_id,
            'name' => $payload['name'],
            'description' => $payload['description'] ?? null,
            'type' => $payload['type'] ?? 'group',
            'created_at' => now(),
            'updated_at' => now(),
            'method' => $payload['method'] ?? 'fifo',
        ]);

        return $nextId;
    }

    public function showDeposit(int $depositId): array
    {
        $deposit = DB::table('deposits')->where('id', $depositId)->first();

        if (! $deposit) {
            throw (new ModelNotFoundException())->setModel(Deposit::class, [$depositId]);
        }

        $users = DB::table('depositusers as du')
            ->join('users as m', 'du.user_id', '=', 'm.member_id')
            ->where('du.deposit_id', $depositId)
            ->select('du.id as affectation_id', 'du.access', 'm.member_code', 'm.member_id', 'm.name', 'm.lastname', 'm.pseudo')
            ->orderBy('m.member_code')
            ->get();

        return [
            'deposit' => $deposit,
            'users' => $users,
        ];
    }

    public function deleteDeposit(int $depositId): void
    {
        DB::table('deposits')->where('id', $depositId)->delete();
    }

    public function attachUser(int $depositId, string|int $memberIdentifier, string $access, User $actor): void
    {
        $member = $this->resolveMember($memberIdentifier);
        $existing = DB::table('depositusers')
            ->where('deposit_id', $depositId)
            ->where('user_id', $member->member_id)
            ->first();

        if ($existing) {
            DB::table('depositusers')
                ->where('id', $existing->id)
                ->update([
                    'access' => $access,
                    'updated_at' => now(),
                ]);

            return;
        }

        $nextId = (int) (DB::table('depositusers')->max('id') ?? 0) + 1;

        DB::table('depositusers')->insert([
            'id' => $nextId,
            'deposit_id' => $depositId,
            'user_id' => $member->member_id,
            'access' => $access,
            'created_at' => now(),
            'updated_at' => now(),
            'added_by' => $actor->member_id,
        ]);
    }

    public function deleteAffectation(int $affectationId): void
    {
        DB::table('depositusers')->where('id', $affectationId)->delete();
    }

    public function depositInventory(int $depositId)
    {
        return DB::table('depositproducts as dp')
            ->join('products as p', 'dp.product_id', '=', 'p.id')
            ->leftJoin('categoriesproducts as c', 'p.category_id', '=', 'c.id')
            ->leftJoin('uoms as u', 'p.uom_id', '=', 'u.id')
            ->where('dp.deposit_id', $depositId)
            ->select(
                'dp.id as affectation_id',
                'dp.deposit_id',
                'dp.product_id',
                'dp.quantity',
                'dp.quantity_sold',
                'dp.price_sold',
                'dp.point',
                'dp.stock_alert',
                'dp.price_gros',
                'dp.price_detail',
                'p.name as product_name',
                'p.description as product_description',
                'c.name as category_name',
                'u.name as uom_name'
            )
            ->orderBy('p.name')
            ->get();
    }

    public function updateInventoryItem(int $affectationId, array $payload): void
    {
        DB::table('depositproducts')
            ->where('id', $affectationId)
            ->update([
                'point' => (float) $payload['point'],
                'stock_alert' => (float) $payload['stock_alert'],
                'price_gros' => (float) $payload['price_gros'],
                'price_detail' => (float) $payload['price_detail'],
                'price_sold' => (float) $payload['price_sold'],
                'updated_at' => now(),
            ]);
    }

    public function movements(User $actor)
    {
        return DB::table('stockmouvements as s')
            ->join('deposits as d', 'd.id', '=', 's.deposit_id')
            ->join('depositusers as du', 'du.deposit_id', '=', 'd.id')
            ->join('products as p', 'p.id', '=', 's.product_id')
            ->leftJoin('uoms as u', 'u.id', '=', 'p.uom_id')
            ->where('du.user_id', $actor->member_id)
            ->select(
                's.*',
                'u.name as uom',
                'p.name as product_name',
                'p.description as product_description',
                'd.name as deposit_name',
                'd.description as deposit_description'
            )
            ->orderByDesc('s.done_at')
            ->orderByDesc('s.id')
            ->get();
    }

    public function stockValues(User $actor)
    {
        $base = DB::table('depositproducts as dp')
            ->join('deposits as d', 'd.id', '=', 'dp.deposit_id')
            ->join('depositusers as du', 'du.deposit_id', '=', 'd.id')
            ->where('du.user_id', $actor->member_id);

        $totals = (clone $base)
            ->selectRaw('
            SUM(dp.quantity * dp.price_gros) as wholesale_value,
            SUM(dp.quantity * dp.price_detail) as retail_value,
            SUM(dp.quantity * dp.price_sold) as sale_value
        ')
            ->first();

        $deposits = (clone $base)
            ->selectRaw('
            dp.deposit_id,
            d.name as deposit_name,
            d.description as deposit_description,
            SUM(dp.quantity * dp.price_gros) as wholesale_value,
            SUM(dp.quantity * dp.price_detail) as retail_value,
            SUM(dp.quantity * dp.price_sold) as sale_value
        ')
            ->groupBy('dp.deposit_id', 'd.name', 'd.description')
            ->orderBy('d.name')
            ->get();

        return [
            'totals' => [
                'wholesale_value' => (float) ($totals->wholesale_value ?? 0),
                'retail_value' => (float) ($totals->retail_value ?? 0),
                'sale_value' => (float) ($totals->sale_value ?? 0),
            ],
            'deposits' => $deposits,
        ];
    }

    public function storeMovement(array $payload, User $actor): int
    {
        $type = $payload['type'];
        $quantity = (float) $payload['quantity'];
        $depositId = (int) $payload['deposit_id'];
        $productId = (int) $payload['product_id'];
        $price = (float) ($payload['price'] ?? 0);

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'La quantite doit etre superieure a zero.',
            ]);
        }

        return DB::transaction(function () use ($type, $quantity, $depositId, $productId, $price, $payload, $actor): int {
            $row = DB::table('depositproducts')
                ->where('deposit_id', $depositId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (! $row && $type === 'withdraw') {
                throw ValidationException::withMessages([
                    'product_id' => 'Ce produit nest pas affecte a ce depot.',
                ]);
            }

            $quantityBefore = (float) ($row->quantity ?? 0);

            if ($type === 'withdraw' && $quantityBefore < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stock insuffisant pour effectuer cette sortie.',
                ]);
            }

            if (! $row) {
                $nextId = (int) (DB::table('depositproducts')->max('id') ?? 0) + 1;
                DB::table('depositproducts')->insert([
                    'id' => $nextId,
                    'deposit_id' => $depositId,
                    'product_id' => $productId,
                    'quantity' => 0,
                    'quantity_sold' => 0,
                    'price_sold' => 0,
                    'point' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'added_by' => $actor->member_id,
                    'stock_alert' => 0,
                    'price_gros' => 0,
                    'price_detail' => 0,
                ]);
            }

            DB::table('depositproducts')
                ->where('deposit_id', $depositId)
                ->where('product_id', $productId)
                ->update([
                    'quantity' => DB::raw('quantity ' . ($type === 'entry' ? '+' : '-') . ' ' . (float) $quantity),
                    'updated_at' => now(),
                ]);

            $movementId = (int) (DB::table('stockmouvements')->max('id') ?? 0) + 1;
            $sold = $type === 'entry' ? $quantityBefore + $quantity : $quantityBefore - $quantity;

            DB::table('stockmouvements')->insert([
                'id' => $movementId,
                'deposit_id' => $depositId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $price,
                'total' => $quantity * $price,
                'done_at' => $payload['done_at'] ?? now()->toDateString(),
                'expiration_date' => $payload['expiration_date'] ?? null,
                'note' => $payload['note'] ?? null,
                'added_by' => $actor->member_id,
                'type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
                'motif' => $payload['motif'] ?? 'internal',
                'stock_before' => $quantityBefore,
                'sold' => $sold,
            ]);

            return $movementId;
        });
    }

    public function transfers(User $actor)
    {
        return DB::table('stocktransferts as st')
            ->join('products as p', 'p.id', '=', 'st.product_id')
            ->join('deposits as ds', 'ds.id', '=', 'st.deposit_sender')
            ->join('deposits as dr', 'dr.id', '=', 'st.deposit_receiver')
            ->where(function ($query) use ($actor): void {
                $query->whereIn('st.deposit_sender', function ($sub) use ($actor): void {
                    $sub->select('deposit_id')->from('depositusers')->where('user_id', $actor->member_id);
                })->orWhereIn('st.deposit_receiver', function ($sub) use ($actor): void {
                    $sub->select('deposit_id')->from('depositusers')->where('user_id', $actor->member_id);
                });
            })
            ->select(
                'st.*',
                'p.name as product_name',
                'ds.name as sender_name',
                'dr.name as receiver_name'
            )
            ->orderByDesc('st.created_at')
            ->get();
    }

    public function storeTransfer(array $payload, User $actor): int
    {
        $affectationId = (int) $payload['affectation_id'];
        $quantity = (float) $payload['quantity'];
        $destDepositId = (int) $payload['destination_deposit_id'];

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'La quantite doit etre superieure a zero.',
            ]);
        }

        return DB::transaction(function () use ($affectationId, $quantity, $destDepositId, $actor): int {
            $source = DB::table('depositproducts')->where('id', $affectationId)->lockForUpdate()->first();

            if (! $source) {
                throw ValidationException::withMessages([
                    'affectation_id' => 'Produit introuvable dans le depot source.',
                ]);
            }

            if ((float) $source->quantity < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stock insuffisant dans le depot source.',
                ]);
            }

            DB::table('depositproducts')
                ->where('id', $source->id)
                ->update([
                    'quantity' => DB::raw('quantity - ' . (float) $quantity),
                    'updated_at' => now(),
                ]);

            $this->writeMovement(
                (int) $source->deposit_id,
                (int) $source->product_id,
                'withdraw',
                $quantity,
                0,
                'Transfert stock envoye...',
                'transfert',
                (float) $source->quantity,
                (float) $source->quantity - $quantity,
                $actor->member_id
            );

            $destination = DB::table('depositproducts')
                ->where('deposit_id', $destDepositId)
                ->where('product_id', $source->product_id)
                ->lockForUpdate()
                ->first();

            $destinationBefore = (float) ($destination->quantity ?? 0);

            if (! $destination) {
                $nextId = (int) (DB::table('depositproducts')->max('id') ?? 0) + 1;
                DB::table('depositproducts')->insert([
                    'id' => $nextId,
                    'deposit_id' => $destDepositId,
                    'product_id' => $source->product_id,
                    'quantity' => $quantity,
                    'quantity_sold' => 0,
                    'price_sold' => 0,
                    'point' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'added_by' => $actor->member_id,
                    'stock_alert' => 0,
                    'price_gros' => 0,
                    'price_detail' => 0,
                ]);
            } else {
                DB::table('depositproducts')
                    ->where('id', $destination->id)
                    ->update([
                        'quantity' => DB::raw('quantity + ' . (float) $quantity),
                        'updated_at' => now(),
                    ]);
            }

            $this->writeMovement(
                $destDepositId,
                (int) $source->product_id,
                'entry',
                $quantity,
                0,
                'Transfert stock recu...',
                'transfert',
                $destinationBefore,
                $destinationBefore + $quantity,
                $actor->member_id
            );

            $transferId = (int) (DB::table('stocktransferts')->max('id') ?? 0) + 1;

            DB::table('stocktransferts')->insert([
                'id' => $transferId,
                'deposit_sender' => $source->deposit_id,
                'deposit_receiver' => $destDepositId,
                'product_id' => $source->product_id,
                'quantity' => $quantity,
                'quantity_received' => 0,
                'received_at' => now(),
                'note' => 'Transfert product...',
                'added_by' => $actor->member_id,
                'received_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'status' => 'pending',
            ]);

            return $transferId;
        });
    }

    public function validateTransfer(int $transferId, float $quantityReceived, User $actor): void
    {
        if ($quantityReceived <= 0) {
            throw ValidationException::withMessages([
                'quantity_received' => 'La quantite recue doit etre superieure a zero.',
            ]);
        }

        DB::table('stocktransferts')
            ->where('id', $transferId)
            ->where('status', 'pending')
            ->update([
                'quantity_received' => $quantityReceived,
                'status' => 'received',
                'received_at' => now(),
                'updated_at' => now(),
                'received_by' => $actor->member_id,
            ]);
    }

    public function denyTransfer(int $transferId, User $actor): void
    {
        DB::transaction(function () use ($transferId, $actor): void {
            $transfer = DB::table('stocktransferts')->where('id', $transferId)->lockForUpdate()->first();

            if (! $transfer || $transfer->status !== 'pending') {
                throw ValidationException::withMessages([
                    'transfer_id' => 'Transfert introuvable ou deja traite.',
                ]);
            }

            $destination = DB::table('depositproducts')
                ->where('deposit_id', $transfer->deposit_receiver)
                ->where('product_id', $transfer->product_id)
                ->lockForUpdate()
                ->first();

            if ($destination && (float) $destination->quantity >= (float) $transfer->quantity) {
                DB::table('depositproducts')
                    ->where('id', $destination->id)
                    ->update([
                        'quantity' => DB::raw('quantity - ' . (float) $transfer->quantity),
                        'updated_at' => now(),
                    ]);
            }

            $source = DB::table('depositproducts')
                ->where('deposit_id', $transfer->deposit_sender)
                ->where('product_id', $transfer->product_id)
                ->lockForUpdate()
                ->first();

            if ($source) {
                DB::table('depositproducts')
                    ->where('id', $source->id)
                    ->update([
                        'quantity' => DB::raw('quantity + ' . (float) $transfer->quantity),
                        'updated_at' => now(),
                    ]);
            }

            DB::table('stocktransferts')
                ->where('id', $transferId)
                ->update([
                    'quantity_received' => 0,
                    'status' => 'denied',
                    'received_at' => now(),
                    'updated_at' => now(),
                    'received_by' => $actor->member_id,
                ]);
        });
    }

    private function writeMovement(
        int $depositId,
        int $productId,
        string $type,
        float $quantity,
        float $price,
        string $note,
        string $motif,
        float $stockBefore,
        float $sold,
        string $addedBy
    ): void {
        $movementId = (int) (DB::table('stockmouvements')->max('id') ?? 0) + 1;

        DB::table('stockmouvements')->insert([
            'id' => $movementId,
            'deposit_id' => $depositId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'price' => $price,
            'total' => $quantity * $price,
            'done_at' => now()->toDateString(),
            'expiration_date' => null,
            'note' => $note,
            'added_by' => $addedBy,
            'type' => $type,
            'created_at' => now(),
            'updated_at' => now(),
            'motif' => $motif,
            'stock_before' => $stockBefore,
            'sold' => $sold,
        ]);
    }

    private function resolveMember(string|int $identifier): User
    {
        $member = User::query()
            ->where('member_code', $identifier)
            ->orWhere('member_id', (string) $identifier)
            ->orWhere('username', (string) $identifier)
            ->first();

        if (! $member) {
            throw (new ModelNotFoundException())->setModel(User::class, [$identifier]);
        }

        return $member;
    }

    private function canManageAll(User $actor): bool
    {
        return (int) $actor->member_code === 1 || in_array((int) $actor->categorie_id, [6, 9], true);
    }
}
