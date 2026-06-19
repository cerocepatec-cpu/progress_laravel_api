<?php

namespace App\Services;

use App\Http\Resources\MemberResource;
use App\Models\User;
use App\Support\LegacyPassword;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MlmService
{
    private const MEMBERS_TABLE = 'users';

    private const QUALIFIED_TABLES = [
        'builder' => 'member_id',
        'sapphire' => 'member_code',
        'ruby' => 'member_code',
        'emerald' => 'member_code',
        'diamond' => 'member_code',
        'diamond_crowned' => 'member_code',
        'ambassador' => 'member_code',
        'ambassador_crowned' => 'member_code',
    ];

    private const QUALIFIED_TABLES_LEVELS = [
        'builder' => [
            'table' => 'builder',
            'join_field' => 'member_id',
            'user_field' => 'member_id',
        ],
        'sapphire' => [
            'table' => 'sapphire',
            'join_field' => 'member_code',
            'user_field' => 'member_code',
        ],
        'ruby' => [
            'table' => 'ruby',
            'join_field' => 'member_code',
            'user_field' => 'member_code',
        ],
        'emerald' => [
            'table' => 'emerald',
            'join_field' => 'member_code',
            'user_field' => 'member_code',
        ],
        'diamond' => [
            'table' => 'diamond',
            'join_field' => 'member_code',
            'user_field' => 'member_code',
        ],
        'diamond_crowned' => [
            'table' => 'diamond_crowned',
            'join_field' => 'member_code',
            'user_field' => 'member_code',
        ],
        'ambassador' => [
            'table' => 'ambassador',
            'join_field' => 'member_code',
            'user_field' => 'member_code',
        ],
        'ambassador_crowned' => [
            'table' => 'ambassador_crowned',
            'join_field' => 'member_code',
            'user_field' => 'member_code',
        ],
    ];

    private const MATRIX_LEVELS = [
        2 => [
            'table' => 'sapphire',
            'reward' => 12.5,
            'owner_debit' => 2.5,
            'promotion_message' => 'Felicitations!!! Progress Business vous accueille dans RUBY',
        ],
        3 => [
            'table' => 'ruby',
            'reward' => 20.0,
            'owner_debit' => 5.0,
            'promotion_message' => 'Felicitations!!! Progress Business vous accueille dans EMERALD',
        ],
        4 => [
            'table' => 'emerald',
            'reward' => 50.0,
            'owner_debit' => 25.0,
            'promotion_message' => 'Felicitations!!! Progress Business vous accueille dans DIAMOND',
        ],
        5 => [
            'table' => 'diamond',
            'reward' => 250.0,
            'owner_debit' => 50.0,
            'promotion_message' => 'Felicitations!!! Progress Business vous accueille dans DIAMOND CROWNED',
        ],
        6 => [
            'table' => 'diamond_crowned',
            'reward' => 500.0,
            'owner_debit' => 75.0,
            'promotion_message' => 'Felicitations!!! Progress Business vous accueille dans AMBASSADOR',
        ],
        7 => [
            'table' => 'ambassador',
            'reward' => 5000.0,
            'owner_debit' => 250.0,
            'promotion_message' => 'Felicitations!!! Progress Business vous accueille dans AMBASSADEUR COURONNE',
        ],
        8 => [
            'table' => 'ambassador_crowned',
            'reward' => 12500.0,
            'owner_debit' => 2500.0,
            'promotion_message' => 'Felicitations!!! Vous avez acheve votre huitieme niveau. Vous avez atteint le nombre maximum des membres.',
        ],
    ];

    private const ROOT_LEVEL_MESSAGES = [
        2 => 'Felicitations!!! Progress Business vous accueille dans SAPPHIRE',
        4 => 'Felicitations, vous avez gagne votre premier bonus de la societe Progress Business. Votre premier KIT alimentaire!!',
        5 => 'Felicitations, vous avez gagne votre deuxieme bonus de la societe Progress Business. Votre deuxieme KIT alimentaire!!',
        6 => 'Felicitations, vous avez gagne votre troisieme bonus de la societe Progress Business. Votre appareil electro-menager!!',
        7 => 'Felicitations, vous avez gagne votre quatrieme bonus de la societe Progress Business. Votre moto de luxe qui vous fera plaisir!!',
        8 => "Felicitations, vous avez gagne votre cinquieme bonus de la societe Progress Business. Votre deuxieme moto de luxe qui vous fera plaisir d'avantage!!",
        9 => 'Felicitations, vous avez gagne votre sixieme bonus de la societe Progress Business. Voiture de luxe et une maison moderne!!',
    ];

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly LegacyPassword $legacyPassword,
    ) {}

    public function resolveMember(string|int|null $identifier): User
    {
        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            throw ValidationException::withMessages([
                'member' => 'Veuillez renseigner un identifiant membre valide.',
            ]);
        }

        $member = User::query()
            ->with('category')
            ->where(function ($query) use ($identifier): void {
                $query->where('member_code', $identifier)
                    ->orWhere('member_id', $identifier)
                    ->orWhere('username', $identifier);
            })
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'member' => 'Aucun membre ne correspond à cet identifiant.',
            ]);
        }

        return $member;
    }

    public function updateMember(User $member, array $payload): User
    {
        $member->fill([
            'name' => $payload['name'] ?? $member->name,
            'lastname' => $payload['lastname'] ?? $member->lastname,
            'pseudo' => $payload['pseudo'] ?? $member->pseudo,
            'telephone' => $payload['telephone'] ?? $member->telephone,
            'email' => $payload['email'] ?? $member->email,
            'gender' => $payload['gender'] ?? $member->gender,
            'password' => array_key_exists('password', $payload)
                ? $this->legacyPassword->hashForStorage($payload['password'])
                : $member->password,
            'username' => $payload['username'] ?? $member->username,
            'categorie_id' => $payload['categorie_id'] ?? $member->categorie_id,
            'e_mobile_number' => $payload['e_mobile_number'] ?? $member->e_mobile_number,
            'bank_name' => $payload['bank_name'] ?? $member->bank_name,
            'bank_account' => $payload['bank_account'] ?? $member->bank_account,
            'password_e_wallet' => array_key_exists('password_e_wallet', $payload)
                ? $this->legacyPassword->hashForStorage($payload['password_e_wallet'])
                : $member->password_e_wallet,
            'adress' => $payload['adress'] ?? $member->adress,
            'city' => $payload['city'] ?? $member->city,
        ]);

        $member->save();

        return $member->fresh(['category']);
    }

    public function updateMemberStatus(User $member, string $status): User
    {
        $member->member_statute = $status;
        $member->save();

        return $member->fresh(['category']);
    }

    public function updateMemberCity(User $member, int $cityId): User
    {
        $member->city = $cityId;
        $member->save();

        return $member->fresh(['category']);
    }

    public function directs(User $member): Collection
    {
        return User::query()
            ->with('category')
            ->where('parent_code', $member->member_code)
            ->orderBy('member_code')
            ->get();
    }

    public function flattenDownline(User $member, int $maxDepth = 8): array
    {
        $depthMap = $this->buildDownlineDepthMap((int) $member->member_code, $maxDepth);

        if ($depthMap === []) {
            return [];
        }

        $members = User::query()
            ->with('category')
            ->whereIn('member_code', array_keys($depthMap))
            ->orderBy('member_code')
            ->get();

        return $members->map(function (User $child) use ($depthMap): array {
            return [
                'depth' => $depthMap[(int) $child->member_code] ?? null,
                'member' => $child,
            ];
        })->all();
    }

    public function downlineCount(User $member, int $maxDepth = 8): int
    {
        return count($this->buildDownlineDepthMap((int) $member->member_code, $maxDepth));
    }

    public function downlineCountByLevel(User $member, int $maxDepth = 8): array
    {
        $depthMap = $this->buildDownlineDepthMap((int) $member->member_code, $maxDepth);

        $levels = [
            1 => 'Builder',
            2 => 'Sapphire',
            3 => 'Ruby',
            4 => 'Emerald',
            5 => 'Diamond',
            6 => 'Diamond Crowned',
            7 => 'Ambassador',
            8 => 'Ambassador Crowned',
        ];

        $result = [];

        for ($depth = 1; $depth <= $maxDepth; $depth++) {
            $count = count(array_filter(
                $depthMap,
                static fn($value): bool => (int) $value === $depth
            ));

            $expected = 4 ** $depth;

            $result[] = [
                'level' => $depth,
                'rank' => $levels[$depth] ?? 'Level ' . $depth,
                'count' => $count,
                'expected' => $expected,
                'missing' => max($expected - $count, 0),
                'percent' => $expected > 0 ? round(($count / $expected) * 100, 2) : 0,
            ];
        }

        return [
            'total' => count($depthMap),
            'levels' => $result,
        ];
    }

    public function vipPacketDownlineCount(User $member): int
    {
        return User::query()
            ->where('sponsor_code', $member->member_code)
            ->where('inscription_mode', 'vip_packet_mode')
            ->count();
    }

    public function latestDownlineMembers(
        User $member,
        int $limit = 10,
        int $maxDepth = 8
    ): SupportCollection {
        $limit = max(1, min($limit, 100));

        $depthMap = $this->buildDownlineDepthMap((int) $member->member_code, $maxDepth);

        if ($depthMap === []) {
            return collect();
        }

        return User::query()
            ->with('category')
            ->whereIn('member_code', array_keys($depthMap))
            ->orderByDesc('date')
            ->orderByDesc('member_code')
            ->limit($limit)
            ->get()
            ->map(function (User $user) use ($depthMap): array {
                return [
                    'depth' => $depthMap[(int) $user->member_code] ?? null,
                    'member' => $user,
                ];
            });
    }

    public function paginatedDownline(
        User $member,
        array $filters = [],
        int $perPage = 20,
        int $maxDepth = 8
    ): LengthAwarePaginator {
        $depthMap = $this->buildDownlineDepthMap((int) $member->member_code, $maxDepth);

        if ($depthMap === []) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        $query = User::query()
            ->with('category')
            ->whereIn('member_code', array_keys($depthMap));

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%")
                    ->orWhere('pseudo', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('telephone', 'like', "%{$search}%")
                    ->orWhere('member_id', 'like', "%{$search}%")
                    ->orWhere('member_code', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['level'])) {
            $level = (int) $filters['level'];

            $codesAtLevel = array_keys(array_filter(
                $depthMap,
                static fn($depth): bool => (int) $depth === $level
            ));

            $query->whereIn('member_code', $codesAtLevel);
        }

        if (! empty($filters['status'])) {
            $query->where('member_statute', $filters['status']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('categorie_id', (int) $filters['category_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('date', '>=', Carbon::parse($filters['date_from'])->toDateString());
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('date', '<=', Carbon::parse($filters['date_to'])->toDateString());
        }

        $paginator = $query
            ->orderBy('member_code')
            ->paginate($perPage);

        $paginator->getCollection()->transform(function (User $user) use ($depthMap): array {
            return [
                'depth' => $depthMap[(int) $user->member_code] ?? null,
                'member' => $user,
            ];
        });

        return $paginator;
    }

    public function downlineCountByDate(
        User $member,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        string $groupBy = 'day',
        int $maxDepth = 8
    ): array {
        $depthMap = $this->buildDownlineDepthMap((int) $member->member_code, $maxDepth);

        if ($depthMap === []) {
            return [];
        }

        $format = match ($groupBy) {
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m-%d',
        };

        $query = User::query()
            ->selectRaw("DATE_FORMAT(date, '{$format}') as period")
            ->selectRaw('COUNT(*) as total')
            ->whereIn('member_code', array_keys($depthMap));

        if ($dateFrom) {
            $query->whereDate('date', '>=', Carbon::parse($dateFrom)->toDateString());
        }

        if ($dateTo) {
            $query->whereDate('date', '<=', Carbon::parse($dateTo)->toDateString());
        }

        return $query
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn($row): array => [
                'period' => $row->period,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    public function tree(User $member, int $maxDepth = 8, int $depth = 0): array
    {
        if ($depth >= $maxDepth) {
            return [];
        }

        $children = User::query()
            ->with('category')
            ->where('parent_code', $member->member_code)
            ->orderBy('member_code')
            ->get();

        return $children->map(function (User $child) use ($maxDepth, $depth): array {
            return [
                'member' => $child,
                'children' => $this->tree($child, $maxDepth, $depth + 1),
            ];
        })->all();
    }

    public function qualifiedMembers(Request $request, string $level): array
    {
        $config = self::QUALIFIED_TABLES_LEVELS[$level] ?? null;

        if (! $config) {
            throw ValidationException::withMessages([
                'level' => 'Niveau inconnu.',
            ]);
        }

        $table = $config['table'];
        $joinField = $config['join_field'];
        $userField = $config['user_field'];

        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);
        $search = trim((string) $request->input('q', ''));

        $query = DB::table($table)
            ->join('users', "users.{$userField}", '=', "{$table}.{$joinField}")
            ->select([
                'users.member_code',
                'users.member_id',
                'users.name',
                'users.lastname',
                'users.pseudo',
                'users.telephone',
                'users.email',
                'users.username',
                'users.categorie_id',
                'users.actual_level',
                'users.member_statute',
                'users.date',
                "{$table}.status as qualification_status",
            ]);

        if ($search !== '') {
            $terms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);

            $query->where(function ($main) use ($terms): void {
                foreach ($terms as $term) {
                    $main->orWhere(function ($sub) use ($term): void {
                        $sub->where('users.member_id', 'like', "%{$term}%")
                            ->orWhere('users.member_code', 'like', "%{$term}%")
                            ->orWhere('users.username', 'like', "%{$term}%")
                            ->orWhere('users.name', 'like', "%{$term}%")
                            ->orWhere('users.lastname', 'like', "%{$term}%")
                            ->orWhere('users.pseudo', 'like', "%{$term}%")
                            ->orWhere('users.telephone', 'like', "%{$term}%")
                            ->orWhere('users.email', 'like', "%{$term}%");
                    });
                }
            });
        }

        $members = $query
            ->orderByRaw("{$table}.status = 'unpaid' DESC")
            ->orderBy('users.member_code')
            ->paginate($perPage)
            ->appends($request->query());

        return [
            'items' => collect($members->items())->map(fn($member) => [
                'member_code' => $member->member_code,
                'member_id' => $member->member_id,
                'name' => $member->name,
                'lastname' => $member->lastname,
                'pseudo' => $member->pseudo,
                'telephone' => $member->telephone,
                'email' => $member->email,
                'username' => $member->username,
                'categorie_id' => $member->categorie_id,
                'actual_level' => (int) $member->actual_level,
                'member_statute' => $member->member_statute,
                'qualification_status' => $member->qualification_status,
                'registered_at' => $member->date,
            ])->values(),
            'meta' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
            ],
        ];
    }

    public function validateLevelPayment(string $level, string|int $memberIdentifier): void
    {
        $config = self::QUALIFIED_TABLES_LEVELS[$level] ?? null;

        if (! $config) {
            throw ValidationException::withMessages([
                'level' => 'Niveau inconnu.',
            ]);
        }

        $member = $this->resolveMember($memberIdentifier);

        $table = $config['table'];
        $joinField = $config['join_field'];
        $userField = $config['user_field'];

        $value = $userField === 'member_id'
            ? $member->member_id
            : $member->member_code;

        DB::table($table)
            ->where($joinField, $value)
            ->update([
                'status' => 'paid',
                'updated_at' => now(),
            ]);
    }

    public function permute(string|int $parentIdentifier, string|int $childIdentifier): array
    {
        $targetRoot = $this->resolveMember($parentIdentifier);
        $child = $this->resolveMember($childIdentifier);

        if ((int) $targetRoot->member_code === (int) $child->member_code) {
            throw ValidationException::withMessages([
                'parent' => 'Le parent cible ne peut pas etre le meme membre.',
            ]);
        }

        $downlineCodes = array_map(
            static fn(array $row): int => (int) $row['member']->member_code,
            $this->flattenDownline($child, 8),
        );

        if (in_array((int) $targetRoot->member_code, $downlineCodes, true)) {
            throw ValidationException::withMessages([
                'parent' => 'Permutation impossible: le parent cible est deja dans la descendance du membre.',
            ]);
        }

        $placementParent = (int) $targetRoot->member_code === 1
            ? $targetRoot
            : $this->findPlacementParent($targetRoot);

        return DB::transaction(function () use ($placementParent, $child): array {
            $oldParentCode = (int) $child->parent_code;

            DB::table('permutation_mouvements')->insert([
                'old_parent_code' => $oldParentCode,
                'new_parent_code' => $placementParent->member_code,
                'member_code' => (string) $child->member_code,
                'date_permutation' => now(),
            ]);

            DB::table(self::MEMBERS_TABLE)
                ->where('member_code', $child->member_code)
                ->update([
                    'parent_code' => $placementParent->member_code,
                    'updated_at' => now(),
                ]);

            DB::table('builder')
                ->where('member_id', $child->member_id)
                ->update([
                    'parent_code' => $placementParent->member_code,
                ]);

            $this->notifications->push(
                $child->member_id,
                'Vous avez ete deplace vers un autre parent grace au processus de mutation!!'
            );

            $this->notifications->push(
                $placementParent->member_id,
                'Vous avez eu un nouvel enfant par le processus de mutation!!'
            );

            if ((int) $placementParent->member_code !== 1) {
                $this->afterMemberInserted($placementParent, $placementParent);
            }

            return [
                'old_parent_code' => $oldParentCode,
                'new_parent_code' => $placementParent->member_code,
                'member_code' => $child->member_code,
            ];
        });
    }

    public function register(array $payload, ?User $actor = null): User
    {
        return DB::transaction(function () use ($payload, $actor): User {
            $mode = $payload['payment_mode_list'] ?? null;

            if (! in_array($mode, ['ewallet_check_mode', 'vip_packet_mode'], true)) {
                throw ValidationException::withMessages([
                    'payment_mode_list' => 'Mode d inscription invalide. Utilisez ewallet_check_mode ou vip_packet_mode.',
                ]);
            }

            // 1. Vérification obligatoire de payment_evidence AVANT toute autre opération métier
            $paymentEvidence = $this->verifyPaymentEvidence($payload);

            // 2. Vérifier si le sponsor existe
            $root = $this->resolveMember($payload['member_id_checking']);

            // 3-4. Vérifier le compte payeur e-wallet / VIP + mot de passe
            $settlement = $this->prepareRegistrationSettlement($payload);

            $placementParent = (int) $root->member_code === 1
                ? $root
                : $this->findPlacementParent($root);

            $sponsorCode = $actor?->member_code ?? $root->member_code;

            $member = $this->createMemberRecord(
                $payload,
                (int) $placementParent->member_code,
                (int) $sponsorCode,
                1,
                'enabled'
            );

            DB::table('entries_accountancy')
                ->where('id_entry_accountancy', $paymentEvidence->id_entry_accountancy)
                ->update([
                    'used' => 1,
                    'adhesion' => $member->member_id,
                ]);

            if ((int) $root->member_code !== 1) {
                DB::table('builder')->insert([
                    'parent_code' => $placementParent->member_code,
                    'member_id' => $member->member_id,
                    'status' => 'unpaid',
                ]);
            }

            $this->applyRegistrationSettlement($member, $root, $actor, $payload, $settlement);

            if ((int) $root->member_code !== 1) {
                $this->afterMemberInserted($root, $placementParent);
            }

            return $member->fresh(['category']);
        });
    }

    private function verifyPaymentEvidence(array $payload): object
    {
        $paymentEvidence = (int) ($payload['payment_evidence'] ?? 0);

        if ($paymentEvidence <= 0) {
            throw ValidationException::withMessages([
                'payment_evidence' => 'La preuve de paiement est obligatoire pour cette adhesion.',
            ]);
        }

        $settings = DB::table('adhesionpointsettings')->first();

        if (! $settings || $settings->status !== 'available') {
            throw ValidationException::withMessages([
                'payment_evidence' => 'La configuration des points d adhesion est indisponible.',
            ]);
        }

        $provenance = preg_replace('/\s+/', ' ', trim(sprintf(
            '%s %s %s',
            $payload['name'] ?? $payload['member_name'] ?? '',
            $payload['lastname'] ?? $payload['member_lastname'] ?? '',
            $payload['pseudo'] ?? $payload['member_pseudo'] ?? '',
        )));

        $entry = DB::table('entries_accountancy')
            ->where('id_entry_accountancy', $paymentEvidence)
            ->where('provenance_entry', $provenance)
            ->where('used', 0)
            ->whereNull('adhesion')
            ->where('point', '>=', $settings->pointvalue)
            ->first();

        if (! $entry) {
            throw ValidationException::withMessages([
                'payment_evidence' => 'La reference comptable ne valide pas cette adhesion.',
            ]);
        }

        return $entry;
    }



    private function prepareRegistrationSettlement(array $payload): array
    {
        $mode = $payload['payment_mode_list'] ?? null;
        $amount = $this->inscriptionAmount();

        if (! in_array($mode, ['ewallet_check_mode', 'vip_packet_mode'], true)) {
            throw ValidationException::withMessages([
                'payment_mode_list' => 'Mode d inscription invalide.',
            ]);
        }

        $payerIdentifier = $payload['e_wallet_account'] ?? null;

        if (empty($payerIdentifier)) {
            throw ValidationException::withMessages([
                'e_wallet_account' => 'Le compte E-wallet payeur est obligatoire.',
            ]);
        }

        $payerPassword = (string) ($payload['password_e_wallet_account'] ?? '');
        $payer = $this->resolveMember($payerIdentifier);

        if (! $this->legacyPassword->check($payerPassword, $payer->password_e_wallet)) {
            throw ValidationException::withMessages([
                'password_e_wallet_account' => 'Mot de passe E-wallet invalide.',
            ]);
        }

        if ($mode === 'ewallet_check_mode' && (float) $payer->total_amount_e_wallet < $amount) {
            throw ValidationException::withMessages([
                'e_wallet_account' => 'Solde E-wallet insuffisant pour cette adhesion.',
            ]);
        }

        if ($mode === 'vip_packet_mode' && (float) ($payer->pdfpaquet ?? 0) < 1) {
            throw ValidationException::withMessages([
                'e_wallet_account' => 'Le membre payeur ne possede plus de coffret VIP.',
            ]);
        }

        return [
            'mode' => $mode,
            'amount' => $amount,
            'entry_id' => null,
            'payer' => $payer,
        ];
    }

    private function applyRegistrationSettlement(
        User $newMember,
        User $root,
        ?User $actor,
        array $payload,
        array $settlement
    ): void {
        $amount = (float) $settlement['amount'];
        $mode = $settlement['mode'];
        $payer = $settlement['payer'];
        $sponsor = $actor ?? $root;
        $gift = 6.0;
        $wording = 'Frais Adhesion ' . $newMember->name . ' ' . $newMember->lastname;

        if ($mode === 'ewallet_check_mode' && $payer instanceof User) {
            $this->updateMemberBalance((int) $payer->member_code, -$amount);

            DB::table('wayout_account_member')->insert([
                'amount' => $amount,
                'wording' => $wording,
                'member_id' => $payer->member_id,
                'transaction_type' => 'adhesion',
                'date_wayout' => now(),
            ]);

            $this->notifications->push(
                $payer->member_id,
                'Votre compte E-wallet a ete deduit de ' . $amount . '$ pour l adhesion du membre ' . $newMember->name . ' ' . $newMember->lastname
            );
        }

        if ($mode === 'vip_packet_mode' && $payer instanceof User) {
            DB::table(self::MEMBERS_TABLE)
                ->where('member_code', $payer->member_code)
                ->update([
                    'pdfpaquet' => DB::raw('COALESCE(pdfpaquet, 0) - 1'),
                    'updated_at' => now(),
                ]);

            $this->notifications->push(
                $payer->member_id,
                'Votre coffret VIP a ete deduit d un coffret pour l adhesion du membre ' . $newMember->name . ' ' . $newMember->lastname
            );
        }

        if (in_array($mode, ['ewallet_check_mode', 'vip_packet_mode'], true)) {
            $this->updateMemberBalance(1, $amount);
        }



        $this->updateMemberBalance(1, -$gift);
        $this->updateMemberBalance((int) $sponsor->member_code, $gift);

        DB::table('entry_account_member')->insert([
            'amount' => $gift,
            'wording' => $wording,
            'member_id' => $sponsor->member_id,
            'transaction_type' => 'gift_adhesion',
            'date_entry' => now(),
        ]);

        DB::table('wayout_accountancy')->insert([
            'amount' => $gift,
            'provenance_wayout' => $newMember->member_id,
            'wording' => 'Gift adhesion ' . $newMember->member_id,
            'done_by' => $actor?->member_id ?? $root->member_id,
            'date_wayout_accountancy' => now(),
        ]);

        $this->notifications->push(
            $sponsor->member_id,
            'Vous avez ajoute un nouveau membre en la personne de ' . $newMember->name . ' ' . $newMember->lastname
        );

        $gender = ($newMember->gender ?? 'M') === 'F' ? 'Madame' : 'Monsieur';
        $welcome = ($newMember->gender ?? 'M') === 'F' ? 'la bienvenue' : 'le bienvenu';

        $this->notifications->push(
            $newMember->member_id,
            $gender . ' ' . $newMember->name . ' ' . $newMember->lastname . ' l equipe Progress Business vous souhaite ' . $welcome . '. Profitez de votre temps et devenez riche...!'
        );
    }

    private function createAdminMember(array $payload, ?User $actor): User
    {
        return $this->createMemberRecord(
            $payload,
            0,
            (int) ($actor?->member_code ?? 1),
            0,
            'enabled'
        )->fresh(['category']);
    }

    private function createMemberRecord(
        array $payload,
        int $parentCode,
        int $sponsorCode,
        int $level,
        string $status
    ): User {
        $memberId = now()->format('YmdHis') . random_int(10, 99);

        if (User::query()->where('member_id', $memberId)->exists()) {
            throw ValidationException::withMessages([
                'member_id' => 'Cet identifiant membre existe deja.',
            ]);
        }

        $username = (string) ($payload['username'] ?? '');
        $baseUsername = str_contains($username, '@') ? $username : $username . '@pro.com';

        if (User::query()->where('username', $baseUsername)->exists()) {
            $baseUsername = preg_replace('/@/', now()->format('is') . '@', $baseUsername, 1);
        }

        $password = (string) ($payload['password'] ?? '');

        if ($password === '') {
            $password = (string) random_int(100000, 999999);
        }

        $passwordHash = $this->legacyPassword->hashForStorage($password);
        $ewalletPasswordHash = $this->legacyPassword->hashForStorage(
            $payload['password_e_wallet'] ?? null,
            $password
        );

        $memberCode = $this->nextMemberCode();

        $record = [
            'member_code' => $memberCode,
            'member_id' => $memberId,
            'name' => $payload['name'],
            'lastname' => $payload['lastname'],
            'pseudo' => $payload['pseudo'] ?? '',
            'telephone' => $payload['telephone'] ?? '',
            'email' => $payload['email'] ?? null,
            'gender' => $payload['gender'] ?? null,
            'password' => $passwordHash,
            'username' => $baseUsername,
            'date' => now(),
            'categorie_id' => (int) $payload['categorie_id'],
            'parent_code' => $parentCode,
            'sponsor_code' => $sponsorCode,
            'e_mobile_number' => $payload['e_mobile_number'] ?? '',
            'bank_name' => $payload['bank_name'] ?? '',
            'bank_account' => $payload['bank_account'] ?? '',
            'total_amount_e_wallet' => (float) ($payload['total_amount_e_wallet'] ?? 0),
            'password_e_wallet' => $ewalletPasswordHash,
            'inscription_mode' => $payload['payment_mode_list'] ?? 'ewallet_check_mode',
            'member_statute' => $status,
            'actual_level' => $level,
            'pdfpaquet' => (float) ($payload['pdfpaquet'] ?? 0),
            'adress' => $payload['adress'] ?? null,
            'city' => $payload['city'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table(self::MEMBERS_TABLE)->insert($record);

        return User::query()->where('member_id', $memberId)->firstOrFail();
    }

    private function afterMemberInserted(User $root, User $placementParent): void
    {
        if ($this->childrenCount((int) $placementParent->member_code) >= 4) {
            $this->promoteRootMember((int) $placementParent->member_code, 2);
        }

        $this->syncRootProgression((int) $root->member_code);
    }

    private function syncRootProgression(int $memberCode): void
    {
        $member = $this->resolveMember($memberCode);
        $currentLevel = (int) $member->actual_level;
        $achievedLevel = max(1, $currentLevel);

        for ($depth = 1; $depth <= 8; $depth++) {
            $countAtDepth = $this->countMembersAtDepth($memberCode, $depth);

            if ($countAtDepth >= (4 ** $depth)) {
                $achievedLevel = min(8, max($achievedLevel, $depth + 1));
            }
        }

        for ($level = $currentLevel + 1; $level <= $achievedLevel; $level++) {
            $this->promoteRootMember($memberCode, $level);
        }
    }

    private function promoteRootMember(int $memberCode, int $newLevel): void
    {
        $member = $this->resolveMember($memberCode);

        if ((int) $member->actual_level >= $newLevel) {
            return;
        }

        DB::table(self::MEMBERS_TABLE)
            ->where('member_code', $memberCode)
            ->update([
                'actual_level' => $newLevel,
                'updated_at' => now(),
            ]);

        if (isset(self::ROOT_LEVEL_MESSAGES[$newLevel])) {
            $this->notifications->push($member->member_id, self::ROOT_LEVEL_MESSAGES[$newLevel]);
        }

        if (isset(self::MATRIX_LEVELS[$newLevel])) {
            $this->alignMemberToMatrix($memberCode, $newLevel);
        }
    }

    private function promoteMatrixParent(int $memberCode, int $newLevel, string $message): void
    {
        $member = $this->resolveMember($memberCode);

        if ((int) $member->actual_level >= $newLevel) {
            return;
        }

        DB::table(self::MEMBERS_TABLE)
            ->where('member_code', $memberCode)
            ->update([
                'actual_level' => $newLevel,
                'updated_at' => now(),
            ]);

        $this->notifications->push($member->member_id, $message);

        if (isset(self::MATRIX_LEVELS[$newLevel])) {
            $this->alignMemberToMatrix($memberCode, $newLevel);
        }
    }

    private function alignMemberToMatrix(int $memberCode, int $level): void
    {
        $config = self::MATRIX_LEVELS[$level] ?? null;

        if (! $config) {
            return;
        }

        $exists = DB::table($config['table'])
            ->where('member_code', $memberCode)
            ->exists();

        if ($exists) {
            return;
        }

        $alignmentParent = $this->findAlignmentParent($memberCode, $level);

        DB::table($config['table'])->insert([
            'parent_code' => $alignmentParent,
            'member_code' => $memberCode,
            'status' => 'unpaid',
        ]);

        $this->updateMemberBalance($alignmentParent, $config['reward']);
        $this->updateMemberBalance(1, -$config['owner_debit']);

        $childrenCount = DB::table($config['table'])
            ->where('parent_code', $alignmentParent)
            ->count();

        if ($childrenCount >= 4) {
            $this->promoteMatrixParent($alignmentParent, $level + 1, $config['promotion_message']);
        }
    }

    private function findAlignmentParent(int $memberCode, int $level): int
    {
        $childMatch = User::query()
            ->where('parent_code', $memberCode)
            ->where('actual_level', $level)
            ->orderBy('member_code')
            ->value('member_code');

        if ($childMatch) {
            return (int) $childMatch;
        }

        $ancestorCode = (int) User::query()
            ->where('member_code', $memberCode)
            ->value('parent_code');

        $iterations = 0;

        while ($ancestorCode > 0 && $iterations < 8) {
            $ancestorLevel = (int) User::query()
                ->where('member_code', $ancestorCode)
                ->value('actual_level');

            if ($ancestorLevel === $level) {
                return $ancestorCode;
            }

            $ancestorCode = (int) User::query()
                ->where('member_code', $ancestorCode)
                ->value('parent_code');

            $iterations++;
        }

        return 1;
    }

    private function findPlacementParent(User $root): User
    {
        $queue = [[$root, 0]];

        while ($queue !== []) {
            [$current, $depth] = array_shift($queue);

            if ($this->childrenCount((int) $current->member_code) < 4) {
                return $current;
            }

            if ($depth >= 7) {
                continue;
            }

            $children = User::query()
                ->where('parent_code', $current->member_code)
                ->orderBy('member_code')
                ->get();

            foreach ($children as $child) {
                $queue[] = [$child, $depth + 1];
            }
        }

        throw ValidationException::withMessages([
            'member_id_checking' => 'Aucun emplacement disponible dans cette matrice.',
        ]);
    }

    private function childrenCount(int $memberCode): int
    {
        return User::query()
            ->where('parent_code', $memberCode)
            ->count();
    }

    private function countMembersAtDepth(int $rootCode, int $targetDepth): int
    {
        $currentLevel = [$rootCode];
        $depth = 0;

        while ($currentLevel !== [] && $depth < $targetDepth) {
            $nextLevel = User::query()
                ->whereIn('parent_code', $currentLevel)
                ->pluck('member_code')
                ->map(static fn($value): int => (int) $value)
                ->all();

            $depth++;

            if ($depth === $targetDepth) {
                return count($nextLevel);
            }

            $currentLevel = $nextLevel;
        }

        return 0;
    }

    private function buildDownlineDepthMap(int $rootCode, int $maxDepth = 8): array
    {
        $depthMap = [];
        $currentParents = [$rootCode];

        for ($depth = 1; $depth <= $maxDepth; $depth++) {
            if ($currentParents === []) {
                break;
            }

            $children = User::query()
                ->whereIn('parent_code', $currentParents)
                ->orderBy('member_code')
                ->pluck('member_code')
                ->map(static fn($value): int => (int) $value)
                ->all();

            if ($children === []) {
                break;
            }

            foreach ($children as $childCode) {
                $depthMap[$childCode] = $depth;
            }

            $currentParents = $children;
        }

        return $depthMap;
    }

    private function updateMemberBalance(int $memberCode, float $delta): void
    {
        DB::table(self::MEMBERS_TABLE)
            ->where('member_code', $memberCode)
            ->update([
                'total_amount_e_wallet' => DB::raw('total_amount_e_wallet + (' . (float) $delta . ')'),
                'updated_at' => now(),
            ]);
    }

    public function inscriptionAmount(): float
    {
        return (float) (DB::table('inscription_cost')->value('amount') ?? 40);
    }

    private function nextMemberCode(): int
    {
        return ((int) DB::table(self::MEMBERS_TABLE)
            ->lockForUpdate()
            ->max('member_code')) + 1;
    }
}
