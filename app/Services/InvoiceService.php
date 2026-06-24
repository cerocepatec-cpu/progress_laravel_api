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
            ->leftJoin('users as c', 'c.member_id', '=', 'i.customer_id')
            ->leftJoin('users as s', 's.member_id', '=', 'i.edited_by_id')
            ->where('i.id', $invoiceId)
            ->select(
                'i.*',

                'c.name as customer_name_from_user',
                'c.lastname as customer_lastname',
                'c.pseudo as customer_pseudo',
                'c.member_id as customer_member_id',
                'c.member_code as customer_member_code',

                's.name as seller_name',
                's.lastname as seller_lastname',
                's.pseudo as seller_pseudo',
                's.member_id as seller_member_id',
                's.member_code as seller_member_code'
            )
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

        $walkinName = trim((string) ($invoice->customer_name ?? ''));

        $customerDisplayName = trim(
            (string) (($invoice->customer_name_from_user ?? '') . ' ' . ($invoice->customer_lastname ?? ''))
        );

        $sellerDisplayName = trim(
            (string) (($invoice->seller_name ?? '') . ' ' . ($invoice->seller_lastname ?? ''))
        );

        return [
            ...(array) $invoice,

            'customer' => $invoice->customer_member_id
                ? [
                    'type' => 'member',
                    'member_id' => $invoice->customer_member_id,
                    'member_code' => $invoice->customer_member_code,
                    'name' => $invoice->customer_name_from_user,
                    'lastname' => $invoice->customer_lastname,
                    'pseudo' => $invoice->customer_pseudo,
                    'display_name' => $customerDisplayName !== ''
                        ? $customerDisplayName
                        : ($invoice->customer_pseudo ?: 'Client membre'),
                ]
                : [
                    'type' => 'walkin',
                    'member_id' => null,
                    'member_code' => null,
                    'name' => $walkinName !== '' ? $walkinName : 'Client comptoir',
                    'lastname' => null,
                    'pseudo' => null,
                    'display_name' => $walkinName !== '' ? $walkinName : 'Client comptoir',
                ],

            'seller' => [
                'member_id' => $invoice->seller_member_id,
                'member_code' => $invoice->seller_member_code,
                'name' => $invoice->seller_name,
                'lastname' => $invoice->seller_lastname,
                'pseudo' => $invoice->seller_pseudo,
                'display_name' => $sellerDisplayName !== ''
                    ? $sellerDisplayName
                    : ($invoice->seller_pseudo ?: $invoice->edited_by_id),
            ],

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
            logger()->info('FACTURE PAYLOAD', $payload);
            $newInvoice = Invoice::create([
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
                $inventory = DepositProduct::where('deposit_id', (int) $line['deposit_id'])
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

                    $memberExists = User::where('member_code', $payload['customer_id'])
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

    public function listByUserPaginated(User $actor, array $filters = []): array
    {
        $from = $filters['from'] ?? now()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $perPage = (int) ($filters['per_page'] ?? 15);

        $query = DB::table('invoices as i')
            ->leftJoin('users as m', 'm.member_id', '=', 'i.customer_id')
            ->where('i.edited_by_id', $actor->member_id)
            ->whereBetween(DB::raw('DATE(i.created_at)'), [$from, $to])
            ->select(
                'i.*',
                'm.name',
                'm.lastname',
                'm.pseudo',
                'm.member_id'
            );

        if (! empty($filters['q'])) {
            $q = $filters['q'];

            $query->where(function ($builder) use ($q) {
                $builder
                    ->where('i.uuid', 'like', "%{$q}%")
                    ->orWhere('i.customer_name', 'like', "%{$q}%")
                    ->orWhere('i.customer_id', 'like', "%{$q}%")
                    ->orWhere('m.name', 'like', "%{$q}%")
                    ->orWhere('m.lastname', 'like', "%{$q}%")
                    ->orWhere('m.pseudo', 'like', "%{$q}%");
            });
        }

        if (! empty($filters['type_facture'])) {
            $query->where('i.type_facture', $filters['type_facture']);
        }

        if (! empty($filters['nature'])) {
            $query->where('i.nature', $filters['nature']);
        }

        if (! empty($filters['status'])) {
            $query->where('i.status', $filters['status']);
        }

        $summaryBase = clone $query;

        $summary = $summaryBase
            ->select(
                'i.type_facture',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(i.total) as total'),
                DB::raw('SUM(i.total_received) as total_received')
            )
            ->groupBy('i.type_facture')
            ->get();

        $paginator = $query
            ->orderByDesc('i.id')
            ->paginate($perPage);

        $items = collect($paginator->items())
            ->map(fn($invoice) => $this->details((int) $invoice->id))
            ->values();

        return [
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'summary' => [
                'count' => $summary->sum('count'),
                'total' => round((float) $summary->sum('total'), 2),
                'total_received' => round((float) $summary->sum('total_received'), 2),
                'by_type' => $summary,
            ],
        ];
    }
}
