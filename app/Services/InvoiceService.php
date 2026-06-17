<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function listByUser(User $actor, ?string $from = null, ?string $to = null)
    {
        $from ??= now()->toDateString();
        $to ??= now()->toDateString();

        return DB::table('invoices')
            ->where('edited_by_id', $actor->member_id)
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->orderByDesc('id')
            ->get()
            ->map(fn ($invoice) => $this->details((int) $invoice->id));
    }

    public function details(int $invoiceId): array
    {
        $invoice = DB::table('invoices as i')
            ->leftJoin('members as m', 'm.member_id', '=', 'i.customer_id')
            ->where('i.id', $invoiceId)
            ->select('i.*', 'm.name', 'm.lastname', 'm.pseudo', 'm.member_id')
            ->first();

        if (! $invoice) {
            throw (new ModelNotFoundException())->setModel(Invoice::class, [$invoiceId]);
        }

        $details = DB::table('invoice_details as iv')
            ->join('products as p', 'p.id', '=', 'iv.product_id')
            ->leftJoin('categoriesproducts as c', 'p.category_id', '=', 'c.id')
            ->leftJoin('uoms as u', 'p.uom_id', '=', 'u.id')
            ->where('iv.invoice_id', $invoiceId)
            ->select(
                'iv.*',
                'p.name',
                'p.description',
                'p.category_id',
                'p.uom_id',
                'p.id as productId',
                'c.name as categoryName',
                'u.name as uomName'
            )
            ->get();

        return [
            ... (array) $invoice,
            'customer' => $invoice->member_id ? [
                'member_id' => $invoice->member_id,
                'name' => $invoice->name,
                'lastname' => $invoice->lastname,
                'pseudo' => $invoice->pseudo,
            ] : null,
            'details' => $details,
        ];
    }

    public function create(User $actor, array $payload): int
    {
        $details = $payload['details'] ?? [];

        if ($details === []) {
            throw ValidationException::withMessages([
                'details' => 'La facture doit contenir au moins une ligne.',
            ]);
        }

        return DB::transaction(function () use ($actor, $payload, $details): int {
            $total = collect($details)->sum(fn (array $line): float => (float) $line['quantity'] * (float) $line['price']);
            $type = $payload['type_facture'];
            $totalReceived = $type === 'cash' ? $total : (float) ($payload['total_received'] ?? 0);

            $invoiceId = (int) ((DB::table('invoices')->max('id') ?? 0) + 1);

            DB::table('invoices')->insert([
                'id' => $invoiceId,
                'edited_by_id' => $actor->member_id,
                'customer_id' => $payload['customer_id'] ?? null,
                'total' => $total,
                'type_facture' => $type,
                'note' => $payload['note'] ?? null,
                'status' => 'validated',
                'uuid' => now()->format('YmdHis').str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT),
                'total_received' => $totalReceived,
                'done_at' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
                'nature' => $payload['nature'] ?? 'products',
            ]);

            foreach ($details as $line) {
                $inventory = DB::table('depositproducts')
                    ->where('deposit_id', (int) $line['deposit_id'])
                    ->where('product_id', (int) $line['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    throw ValidationException::withMessages([
                        'details' => 'Une ligne de facture pointe vers un stock introuvable.',
                    ]);
                }

                if ((float) $inventory->quantity < (float) $line['quantity']) {
                    throw ValidationException::withMessages([
                        'details' => 'Stock insuffisant pour au moins un produit facture.',
                    ]);
                }

                $nextDetailId = (int) ((DB::table('invoice_details')->max('id') ?? 0) + 1);
                $detailTotal = (float) $line['quantity'] * (float) $line['price'];
                $points = (float) ($line['point'] ?? $inventory->point ?? 0) * (float) $line['quantity'];

                DB::table('invoice_details')->insert([
                    'id' => $nextDetailId,
                    'product_id' => (int) $line['product_id'],
                    'invoice_id' => $invoiceId,
                    'deposit_id' => (int) $line['deposit_id'],
                    'quantity' => (float) $line['quantity'],
                    'price' => (float) $line['price'],
                    'total' => $detailTotal,
                    'benefit' => null,
                    'stock_history' => null,
                    'description' => $line['description'] ?? null,
                    'date_at' => now()->toDateString(),
                    'points' => $points,
                ]);

                DB::table('depositproducts')
                    ->where('id', $inventory->id)
                    ->update([
                        'quantity' => DB::raw('quantity - '.(float) $line['quantity']),
                        'updated_at' => now(),
                    ]);

                $movementId = (int) ((DB::table('stockmouvements')->max('id') ?? 0) + 1);
                DB::table('stockmouvements')->insert([
                    'id' => $movementId,
                    'deposit_id' => (int) $line['deposit_id'],
                    'product_id' => (int) $line['product_id'],
                    'quantity' => (float) $line['quantity'],
                    'price' => (float) $line['price'],
                    'total' => $detailTotal,
                    'done_at' => now()->toDateString(),
                    'expiration_date' => null,
                    'note' => 'vente',
                    'added_by' => $actor->member_id,
                    'type' => 'withdraw',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'motif' => 'sold',
                    'stock_before' => (float) $inventory->quantity,
                    'sold' => (float) $inventory->quantity - (float) $line['quantity'],
                ]);
            }

            return $invoiceId;
        });
    }
}

