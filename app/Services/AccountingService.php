<?php

namespace App\Services;

use App\Models\User;
use App\Services\NotificationService;
use App\Support\LegacyPassword;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function summary(User $actor): array
    {
        $owner = $this->resolveMember(1);

        return [
            'member' => [
                'member_code' => $actor->member_code,
                'member_id' => $actor->member_id,
                'name' => $actor->name,
                'lastname' => $actor->lastname,
                'telephone' => $actor->telephone,
                'email' => $actor->email,
                'balance' => (float) $actor->total_amount_e_wallet,
                'actual_level' => (int) $actor->actual_level,
            ],
            'owner' => [
                'member_code' => $owner->member_code,
                'member_id' => $owner->member_id,
                'balance' => (float) $owner->total_amount_e_wallet,
            ],
            'counts' => [
                'entries' => DB::table('entries_accountancy')->count(),
                'wayouts' => DB::table('wayout_accountancy')->count(),
                'transfers' => DB::table('transactions_accounts')->count(),
                'cash_operations' => DB::table('cash_operations')->count(),
            ],
        ];
    }

    public function transfer(User $actor, array $payload): array
    {
        $destination = $this->resolveMember($payload['member_id_destination']);
        $amount = (float) $payload['amount'];
        $wording = (string) $payload['wording'];

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Le montant doit etre superieur a zero.',
            ]);
        }

        return DB::transaction(function () use ($actor, $destination, $amount, $wording): array {
            $source = $this->lockMember($actor->member_code);

            if ((float) $source->total_amount_e_wallet < $amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Solde E-wallet insuffisant.',
                ]);
            }

            $recipient = $this->lockMember($destination->member_code);

            $this->setBalance($source->member_code, (float) $source->total_amount_e_wallet - $amount);
            $this->setBalance($recipient->member_code, (float) $recipient->total_amount_e_wallet + $amount);

            DB::table('wayout_account_member')->insert([
                'amount' => $amount,
                'wording' => $wording,
                'member_id' => $source->member_id,
                'transaction_type' => 'transaction virtuelle',
                'date_wayout' => now(),
            ]);

            DB::table('entry_account_member')->insert([
                'amount' => $amount,
                'wording' => $wording,
                'member_id' => $recipient->member_id,
                'transaction_type' => 'transaction virtuelle',
                'date_entry' => now(),
            ]);

            DB::table('transactions_accounts')->insert([
                'id' => (int) now()->format('YmdHisv'),
                'amount' => $amount,
                'wording' => $wording,
                'member_id_source' => $source->member_id,
                'member_id_destination' => $recipient->member_id,
                'date_transaction' => now(),
            ]);

            $this->notifications->push(
                $recipient->member_id,
                'Votre compte a ete credite de ' . $amount . '$ par ' . $source->name . ' ' . $source->lastname
            );
            $this->notifications->push(
                $source->member_id,
                'Vous avez transfere un montant de ' . $amount . '$ pour ' . $recipient->member_id
            );

            return [
                'source_balance' => (float) $source->total_amount_e_wallet - $amount,
                'destination_balance' => (float) $recipient->total_amount_e_wallet + $amount,
            ];
        });
    }

    public function storeEntry(User $actor, array $payload): int
    {
        $amount = (float) $payload['amount'];

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Le montant doit etre superieur a zero.',
            ]);
        }

        return DB::transaction(function () use ($actor, $payload, $amount): int {
            $owner = $this->lockMember(1);

            $id = DB::table('entries_accountancy')->insertGetId([
                'amount' => $amount,
                'member_id' => $actor->member_id,
                'wording' => $payload['wording'],
                'provenance_entry' => $payload['provenance_entry'] ?? $payload['provenance'] ?? null,
                'date_entry_accountancy' => now(),
                'point' => (float) ($payload['point'] ?? 0),
                'used' => 0,
                'adhesion' => null,
            ]);

            DB::table('members')
                ->where('member_code', 1)
                ->update([
                    'total_amount_e_wallet' => DB::raw('total_amount_e_wallet + ' . (float) $amount),
                ]);

            $this->notifications->push(
                $actor->member_id,
                'Entree caisse : vous avez effectue une entree en caisse de ' . $amount . '$.'
            );
            $this->notifications->push(
                $owner->member_id,
                'Entree caisse : votre compte principal a ete augmente de ' . $amount . '$ par ' . $actor->username . '.'
            );

            return (int) $id;
        });
    }

    public function storeWayout(User $actor, array $payload): int
    {
        $amount = (float) $payload['amount'];

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Le montant doit etre superieur a zero.',
            ]);
        }

        return DB::transaction(function () use ($actor, $payload, $amount): int {
            $owner = $this->lockMember(1);

            if ((float) $owner->total_amount_e_wallet < $amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Le compte principal ne dispose pas du solde suffisant.',
                ]);
            }

            $id = DB::table('wayout_accountancy')->insertGetId([
                'amount' => $amount,
                'provenance_wayout' => $payload['destination'] ?? 'external',
                'wording' => $payload['wording'],
                'done_by' => $actor->member_id,
                'date_wayout_accountancy' => now(),
            ]);

            $this->setBalance($owner->member_code, (float) $owner->total_amount_e_wallet - $amount);

            $this->notifications->push(
                $actor->member_id,
                'Sortie caisse : vous avez effectue une operation de sortie de ' . $amount . '$.'
            );
            $this->notifications->push(
                $owner->member_id,
                'Sortie caisse : votre compte principal a ete diminue de ' . $amount . '$ par ' . $actor->username . '.'
            );

            return (int) $id;
        });
    }

    public function memberLedger(Request $request, User $member): array
    {
        $type = (string) $request->query('type', 'entries_member');
        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);

        $search = trim((string) $request->query('search', ''));
        $from = $request->query('from');
        $to = $request->query('to');
        $minAmount = $request->query('min_amount');
        $maxAmount = $request->query('max_amount');
        $transactionType = trim((string) $request->query('transaction_type', ''));

        $memberIds = array_values(array_filter([
            (string) $member->member_id,
            (string) $member->member_code,
            (string) $member->id,
            (string) $member->email,
        ]));

        $configs = [
            'entries_accountancy' => [
                'table' => 'entries_accountancy',
                'id' => 'id_entry_accountancy',
                'date' => 'date_entry_accountancy',
                'member_column' => 'member_id',
                'search_columns' => ['wording', 'provenance_entry'],
                'type_column' => 'provenance_entry',
            ],

            'entries_member' => [
                'table' => 'entry_account_member',
                'id' => 'id_entry',
                'date' => 'date_entry',
                'member_column' => 'member_id',
                'search_columns' => ['wording', 'transaction_type'],
                'type_column' => 'transaction_type',
            ],

            'wayouts_accountancy' => [
                'table' => 'wayout_accountancy',
                'id' => 'id_entry_accountancy',
                'date' => 'date_wayout_accountancy',
                'member_column' => 'done_by',
                'search_columns' => ['wording', 'provenance_wayout', 'done_by'],
                'type_column' => 'provenance_wayout',
            ],

            'wayouts_member' => [
                'table' => 'wayout_account_member',
                'id' => 'id_wayout',
                'date' => 'date_wayout',
                'member_column' => 'member_id',
                'search_columns' => ['wording', 'transaction_type'],
                'type_column' => 'transaction_type',
            ],

            'incoming_transactions' => [
                'table' => 'transactions_accounts',
                'id' => 'id',
                'date' => 'date_transaction',
                'member_column' => 'member_id_destination',
                'search_columns' => ['wording', 'member_id_source', 'member_id_destination'],
                'type_column' => 'wording',
            ],

            'outgoing_transactions' => [
                'table' => 'transactions_accounts',
                'id' => 'id',
                'date' => 'date_transaction',
                'member_column' => 'member_id_source',
                'search_columns' => ['wording', 'member_id_source', 'member_id_destination'],
                'type_column' => 'wording',
            ],
        ];

        if (! isset($configs[$type])) {
            abort(422, 'Type de relevé invalide.');
        }

        $config = $configs[$type];

        $query = DB::table($config['table'])
            ->whereIn($config['member_column'], $memberIds);

        if ($search !== '') {
            $terms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);

            $query->where(function ($mainQuery) use ($terms, $config) {
                foreach ($terms as $term) {
                    $mainQuery->orWhere(function ($termQuery) use ($term, $config) {
                        foreach ($config['search_columns'] as $column) {
                            $termQuery->orWhere($column, 'like', "%{$term}%");
                        }
                    });
                }
            });
        }

        if ($transactionType !== '' && $config['type_column']) {
            $query->where($config['type_column'], 'like', "%{$transactionType}%");
        }

        if ($from) {
            $query->whereDate($config['date'], '>=', $from);
        }

        if ($to) {
            $query->whereDate($config['date'], '<=', $to);
        }

        if ($minAmount !== null && $minAmount !== '') {
            $query->where('amount', '>=', (float) $minAmount);
        }

        if ($maxAmount !== null && $maxAmount !== '') {
            $query->where('amount', '<=', (float) $maxAmount);
        }

        $totalAmount = (clone $query)->sum('amount');

        $items = $query
            ->orderByDesc($config['date'])
            ->paginate($perPage)
            ->appends($request->query());

        return [
            'type' => $type,
            'total_amount' => (float) $totalAmount,
            'data' => $items,
        ];
    }

    public function reports(User $actor, array $filters): array
    {
        $type = $filters['type'] ?? 'entries';
        $from = $filters['from'] ?? now()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $accounts = collect($filters['accounts'] ?? [])->filter()->values();
        $canManageAll = $this->canManageAll($actor);

        return match ($type) {
            'entries' => [
                'type' => $type,
                'items' => $this->queryEntries($actor, $from, $to, $accounts, $canManageAll)->get(),
            ],
            'wayouts' => [
                'type' => $type,
                'items' => $this->queryWayouts($actor, $from, $to, $accounts, $canManageAll)->get(),
            ],
            'transactions' => [
                'type' => $type,
                'items' => DB::table('transactions_accounts')
                    ->when(! $canManageAll, fn($query) => $query->where('member_id_source', $actor->member_id))
                    ->whereBetween(DB::raw('DATE(date_transaction)'), [$from, $to])
                    ->orderByDesc('date_transaction')
                    ->get(),
            ],
            'cash' => [
                'type' => $type,
                'items' => DB::table('cash_operations')
                    ->when(! $canManageAll, fn($query) => $query->where('member_code', $actor->member_code))
                    ->whereBetween('cash_date', [$from, $to])
                    ->orderByDesc('cash_date')
                    ->get(),
            ],
            default => throw ValidationException::withMessages([
                'type' => 'Type de rapport inconnu.',
            ]),
        };
    }

    public function cashOperations(User $actor): Collection
    {
        return DB::table('cash_operations')
            ->when(! $this->canManageAll($actor), fn($query) => $query->where('member_code', $actor->member_code))
            ->orderByDesc('cash_date')
            ->get();
    }

    public function cashOperation(User $actor, int $cashId): object
    {
        $cash = DB::table('cash_operations')
            ->when(! $this->canManageAll($actor), fn($query) => $query->where('member_code', $actor->member_code))
            ->where('cash_id', $cashId)
            ->first();

        if (! $cash) {
            throw ValidationException::withMessages([
                'cash_id' => 'Operation CASH introuvable.',
            ]);
        }

        return $cash;
    }

    public function storeCashOperation(User $actor, array $payload): int
    {
        $amount = (float) $payload['cash_mount'];

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'cash_mount' => 'Le montant doit etre superieur a zero.',
            ]);
        }

        $cashId = (int) now()->format('YmdHisv');

        DB::table('cash_operations')->insert([
            'cash_id' => $cashId,
            'cash_mount' => $amount,
            'member_code' => $actor->member_code,
            'cash_mode' => $payload['cash_mode'],
            'bank_name' => $payload['bank_name'] ?? null,
            'own_bank_account' => $payload['own_bank_account'] ?? null,
            'bank_account_number' => $payload['bank_account_number'] ?? null,
            'mobile_number' => $payload['mobile_number'] ?? null,
            'cash_statute' => 'waiting',
            'cash_date' => now()->toDateString(),
        ]);

        return $cashId;
    }

    public function validateCashOperation(int $cashId): void
    {
        DB::transaction(function () use ($cashId): void {
            $cash = DB::table('cash_operations')->where('cash_id', $cashId)->lockForUpdate()->first();

            if (! $cash) {
                throw ValidationException::withMessages([
                    'cash_id' => 'Operation CASH introuvable.',
                ]);
            }

            $member = $this->lockMember((int) $cash->member_code);

            if ((float) $member->total_amount_e_wallet < (float) $cash->cash_mount) {
                throw ValidationException::withMessages([
                    'cash_id' => 'Le membre ne dispose pas du solde suffisant.',
                ]);
            }

            DB::table('cash_operations')
                ->where('cash_id', $cashId)
                ->update(['cash_statute' => 'enabled']);

            $this->setBalance($member->member_code, (float) $member->total_amount_e_wallet - (float) $cash->cash_mount);
            DB::table('members')
                ->where('member_code', 1)
                ->update([
                    'total_amount_e_wallet' => DB::raw('total_amount_e_wallet + ' . (float) $cash->cash_mount),
                ]);

            DB::table('entries_accountancy')->insert([
                'amount' => $cash->cash_mount,
                'member_id' => $member->member_id,
                'wording' => 'CASH : retrait du membre ' . $member->username . ' [' . $member->member_id . ']',
                'provenance_entry' => 'cash',
                'date_entry_accountancy' => now(),
                'point' => 0,
                'used' => 0,
                'adhesion' => null,
            ]);

            $this->notifications->push($member->member_id, 'Operation CASH : votre compte a ete debite de ' . $cash->cash_mount . '$.');
            $this->notifications->push('1', 'Operation CASH : votre compte principal a ete credite de ' . $cash->cash_mount . '$.');
        });
    }

    public function deleteCashOperation(int $cashId): void
    {
        DB::table('cash_operations')->where('cash_id', $cashId)->delete();
    }

    public function vipPackets(User $actor): array
    {
        return [
            'balance' => (float) $actor->total_amount_e_wallet,
            'pdfpaquet' => (float) ($actor->pdfpaquet ?? 0),
            'inscription_cost_reference' => (float) (DB::table('inscription_cost')->value('amount') ?? 40),
            'history' => DB::table('coffrets_vip')
                ->where('member_id', $actor->member_id)
                ->orderByDesc('id')
                ->get(),
        ];
    }

    public function buyVipPackets(User $actor, array $payload): array
    {
        $legacyPsw = new LegacyPassword();
        $notification = new NotificationService();
        $MlmService = new MlmService($notification, $legacyPsw);
        $inscriptionAmount = $MlmService->inscriptionAmount();

        $number = (int) $payload['number'];
        $total = (float) $number * $inscriptionAmount;

        if ($number <= 0) {
            throw ValidationException::withMessages([
                'number' => 'Le nombre de coffrets doit etre superieur a zero.',
            ]);
        }

        if ($total <= 0) {
            throw ValidationException::withMessages([
                'total' => 'Le total a payer doit etre superieur a zero.',
            ]);
        }
        if (!$legacyPsw->check($payload['password_ewallet'], $actor->password_e_wallet)) {
            throw ValidationException::withMessages([
                'password_ewallet' => 'Le mot de pass de votre ewalet est incorect'
            ]);
        }
        return DB::transaction(function () use ($actor, $number, $total): array {

            $member = $this->lockMember($actor->member_code);

            if ((float) $member->total_amount_e_wallet < $total) {
                throw ValidationException::withMessages([
                    'total' => 'Solde E-wallet insuffisant pour acheter ces coffrets VIP.',
                ]);
            }

            DB::table('users')
                ->where('member_code', $member->member_code)
                ->update([
                    'pdfpaquet' => DB::raw('COALESCE(pdfpaquet, 0) + ' . $number),
                    'total_amount_e_wallet' => DB::raw('total_amount_e_wallet - ' . (float) $total),
                ]);

            $id = (int) ((DB::table('coffrets_vip')->max('id') ?? 0) + 1);
            DB::table('coffrets_vip')->insert([
                'id' => $id,
                'member_id' => $member->member_id,
                'pu' => $total / $number,
                'total' => $total,
                'created_at' => now(),
                'number' => $number,
            ]);

            $member = $this->resolveMember($member->member_code);

            return [
                'id' => $id,
                'balance' => (float) $member->total_amount_e_wallet,
                'pdfpaquet' => (float) ($member->pdfpaquet ?? 0),
                'number' => $number,
                'total' => $total,
                'pu' => $total / $number,
            ];
        });
    }

    private function queryEntries(User $actor, string $from, string $to, Collection $accounts, bool $canManageAll)
    {
        return DB::table('entries_accountancy')
            ->join('members', 'entries_accountancy.member_id', '=', 'members.member_id')
            ->when(! $canManageAll, fn($query) => $query->where('entries_accountancy.member_id', $actor->member_id))
            ->when($canManageAll && $accounts->isNotEmpty(), fn($query) => $query->whereIn('entries_accountancy.member_id', $accounts->all()))
            ->whereBetween(DB::raw('DATE(date_entry_accountancy)'), [$from, $to])
            ->orderByDesc('date_entry_accountancy');
    }

    private function queryWayouts(User $actor, string $from, string $to, Collection $accounts, bool $canManageAll)
    {
        return DB::table('wayout_accountancy')
            ->join('members', 'wayout_accountancy.done_by', '=', 'members.member_id')
            ->when(! $canManageAll, fn($query) => $query->where('wayout_accountancy.done_by', $actor->member_id))
            ->when($canManageAll && $accounts->isNotEmpty(), fn($query) => $query->whereIn('wayout_accountancy.done_by', $accounts->all()))
            ->whereBetween(DB::raw('DATE(date_wayout_accountancy)'), [$from, $to])
            ->orderByDesc('date_wayout_accountancy');
    }

    private function canManageAll(User $actor): bool
    {
        return (int) $actor->member_code === 1 || in_array((int) $actor->categorie_id, [6, 9], true);
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

    private function lockMember(int|string $identifier): User
    {
        $member = User::query()
            ->where('member_code', $identifier)
            ->orWhere('member_id', (string) $identifier)
            ->lockForUpdate()
            ->first();

        if (! $member) {
            throw (new ModelNotFoundException())->setModel(User::class, [$identifier]);
        }

        return $member;
    }

    private function setBalance(int $memberCode, float $newBalance): void
    {
        DB::table('members')
            ->where('member_code', $memberCode)
            ->update(['total_amount_e_wallet' => $newBalance]);
    }
}
