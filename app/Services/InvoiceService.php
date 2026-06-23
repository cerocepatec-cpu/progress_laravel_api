<?php

namespace App\Services;

use App\Models\DepositProduct;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\StockMouvement;
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
            ->map(fn($invoice) => $this->details((int) $invoice->id));
    }

    public function details(int $invoiceId): array
    {
        $invoice = DB::table('invoices as i')
            ->leftJoin('users as m', 'm.member_id', '=', 'i.customer_id')
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
            ...(array) $invoice,
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
            $nature = $payload['nature'] ?? 'products';

            $total = collect($details)->sum(
                fn(array $line): float => (float) $line['quantity'] * (float) $line['price']
            );

            $type = $payload['type_facture'];
            $totalReceived = $type === 'cash' ? $total : (float) ($payload['total_received'] ?? 0);

            $newInvoice=Invoice::create([
                'edited_by_id' => $actor->member_id,
                'customer_id' => $payload['customer_id'] ?? null,
                'customer_name' => $payload['customer_name'] ?? null,
                'total' => $total,
                'type_facture' => $type,
                'note' => $payload['note'] ?? null,
                'status' => 'validated',
                'uuid' => now()->format('YmdHis') . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT),
                'total_received' => $totalReceived,
                'done_at' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
                'nature' => $nature,
            ]);

            foreach ($details as $line) {
                $inventory =DepositProduct::where('deposit_id', (int) $line['deposit_id'])
                    ->where('product_id', (int) $line['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    throw ValidationException::withMessages([
                        'details' => 'Une ligne de facture pointe vers un stock introuvable.',
                    ]);
                }

                $stockField = $nature === 'solds' ? 'quantity_sold' : 'quantity';
                $availableQuantity = (float) ($inventory->{$stockField} ?? 0);

                if ($availableQuantity < (float) $line['quantity']) {
                    throw ValidationException::withMessages([
                        'details' => $nature === 'solds'
                            ? 'Stock soldé insuffisant pour au moins un produit facturé.'
                            : 'Stock insuffisant pour au moins un produit facturé.',
                    ]);
                }

                $linePrice = round((float) $line['price'], 2);

                if ($nature === 'maj') {
                    if (empty($payload['customer_id'])) {
                        throw ValidationException::withMessages([
                            'customer_id' => 'La vente MAJ exige un membre existant.',
                        ]);
                    }

                    $memberExists =User::where('member_code', $payload['customer_id'])
                        ->orWhere('member_id', $payload['customer_id'])
                        ->exists();

                    if (! $memberExists) {
                        throw ValidationException::withMessages([
                            'customer_id' => 'Le membre sélectionné est introuvable.',
                        ]);
                    }

                    $point = (float) ($line['point'] ?? $inventory->point ?? 0);

                    if ($point <= 0) {
                        throw ValidationException::withMessages([
                            'details' => 'La vente MAJ exige des points supérieurs à zéro.',
                        ]);
                    }
                }

                if ($nature === 'solds') {
                    if ($linePrice !== round((float) $inventory->price_sold, 2)) {
                        throw ValidationException::withMessages([
                            'details' => 'Pour les soldes, le prix doit être le prix soldé.',
                        ]);
                    }
                }

                if ($nature === 'products') {
                    $allowedPrices = [
                        round((float) $inventory->price_detail, 2),
                        round((float) $inventory->price_gros, 2),
                    ];

                    if (! in_array($linePrice, $allowedPrices, true)) {
                        throw ValidationException::withMessages([
                            'details' => 'Pour les produits, le prix doit être le prix détail ou gros.',
                        ]);
                    }
                }

                $detailTotal = (float) $line['quantity'] * (float) $line['price'];
                $points = $nature === 'maj'
                    ? (float) ($line['point'] ?? $inventory->point ?? 0) * (float) $line['quantity']
                    : 0;

               InvoiceDetail::create([
                    'product_id' => (int) $line['product_id'],
                    'invoice_id' => $newInvoice->id,
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

                DepositProduct::where('id', $inventory->id)
                    ->update([
                        $stockField => DB::raw($stockField . ' - ' . (float) $line['quantity']),
                        'updated_at' => now(),
                    ]);

                StockMouvement::create([
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
                    'motif' => $nature === 'solds' ? 'sold' : $nature,
                    'stock_before' => $availableQuantity,
                    'sold' => $availableQuantity - (float) $line['quantity'],
                ]);
            }

            return $newInvoice->id;
        });
    }
}
