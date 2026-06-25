<?php

namespace App\Services;

use App\Models\Member;
use App\Support\LegacyPassword;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MlmService
{
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
    ) {
    }

    public function resolveMember(string|int $identifier): Member
    {
        $member = Member::query()
            ->with('category')
            ->where('member_code', $identifier)
            ->orWhere('member_id', (string) $identifier)
            ->orWhere('username', (string) $identifier)
            ->first();

        if (! $member) {
            throw (new ModelNotFoundException())->setModel(Member::class, [$identifier]);
        }

        return $member;
    }

    public function updateMember(Member $member, array $payload): Member
    {
        $member->fill([
            'name' => $payload['name'] ?? $member->name,
            'lastname' => $payload['lastname'] ?? $member->lastname,
            'pseudo' => $payload['pseudo'] ?? $member->pseudo,
            'telephone' => $payload['telephone'] ?? $member->telephone,
            'email' => $payload['email'] ?? $member->email,
            'gender' => $payload['gender'] ?? $member->gender,
            'password' => $payload['password'] ?? $member->password,
            'username' => $payload['username'] ?? $member->username,
            'categorie_id' => $payload['categorie_id'] ?? $member->categorie_id,
            'e_mobile_number' => $payload['e_mobile_number'] ?? $member->e_mobile_number,
            'bank_name' => $payload['bank_name'] ?? $member->bank_name,
            'bank_account' => $payload['bank_account'] ?? $member->bank_account,
            'password_e_wallet' => $payload['password_e_wallet'] ?? $member->password_e_wallet,
            'adress' => $payload['adress'] ?? $member->adress,
        ]);

        $member->save();

        return $member->fresh(['category']);
    }

    public function updateMemberStatus(Member $member, string $status): Member
    {
        $member->member_statute = $status;
        $member->save();

        return $member->fresh(['category']);
    }

    public function updateMemberCity(Member $member, int $cityId): Member
    {
        $member->city = $cityId;
        $member->save();

        return $member->fresh(['category']);
    }

    public function directs(Member $member): Collection
    {
        return Member::query()
            ->with('category')
            ->where('parent_code', $member->member_code)
            ->orderBy('member_code')
            ->get();
    }

    public function flattenDownline(Member $member, int $maxDepth = 8): array
    {
        $items = [];
        $queue = [[$member, 0]];

        while ($queue !== []) {
            [$current, $depth] = array_shift($queue);

            if ($depth >= $maxDepth) {
                continue;
            }

            $children = Member::query()
                ->with('category')
                ->where('parent_code', $current->member_code)
                ->orderBy('member_code')
                ->get();

            foreach ($children as $child) {
                $items[] = [
                    'depth' => $depth + 1,
                    'member' => $child,
                ];
                $queue[] = [$child, $depth + 1];
            }
        }

        return $items;
    }

    public function tree(Member $member, int $maxDepth = 8, int $depth = 0): array
    {
        if ($depth >= $maxDepth) {
            return [];
        }

        $children = Member::query()
            ->with('category')
            ->where('parent_code', $member->member_code)
            ->orderBy('member_code')
            ->get();

        return $children->map(function (Member $child) use ($maxDepth, $depth): array {
            return [
                'member' => $child,
                'children' => $this->tree($child, $maxDepth, $depth + 1),
            ];
        })->all();
    }

    public function qualifiedMembers(string $level): Collection
    {
        $joinField = self::QUALIFIED_TABLES[$level] ?? null;

        if (! $joinField) {
            throw ValidationException::withMessages([
                'level' => 'Niveau MLM inconnu.',
            ]);
        }

        return Member::query()
            ->select('members.*', "{$level}.status as qualification_status")
            ->join($level, "{$level}.{$joinField}", '=', "members.{$joinField}")
            ->orderByRaw("({$level}.status = 'unpaid') DESC")
            ->orderBy('members.member_code')
            ->get();
    }

    public function validateLevelPayment(string $level, string|int $memberIdentifier): void
    {
        $joinField = self::QUALIFIED_TABLES[$level] ?? null;
        $member = $this->resolveMember($memberIdentifier);

        if (! $joinField) {
            throw ValidationException::withMessages([
                'level' => 'Niveau MLM inconnu.',
            ]);
        }

        $value = $joinField === 'member_id' ? $member->member_id : $member->member_code;

        DB::table($level)
            ->where($joinField, $value)
            ->update(['status' => 'paid']);
    }

    public function permute(string|int $parentIdentifier, string|int $childIdentifier): array
    {
        $targetRoot = $this->resolveMember($parentIdentifier);
        $child = $this->resolveMember($childIdentifier);

        if ($targetRoot->member_code === $child->member_code) {
            throw ValidationException::withMessages([
                'parent' => 'Le parent cible ne peut pas etre le meme membre.',
            ]);
        }

        $downlineCodes = array_map(
            static fn (array $row): int => (int) $row['member']->member_code,
            $this->flattenDownline($child, 8),
        );

        if (in_array((int) $targetRoot->member_code, $downlineCodes, true)) {
            throw ValidationException::withMessages([
                'parent' => 'Permutation impossible: le parent cible est deja dans la descendance du membre.',
            ]);
        }

        $placementParent = $targetRoot->member_code === 1
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

            DB::table('members')
                ->where('member_code', $child->member_code)
                ->update([
                    'parent_code' => $placementParent->member_code,
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

            if ($placementParent->member_code !== 1) {
                $this->afterMemberInserted($placementParent, $placementParent);
            }

            return [
                'old_parent_code' => $oldParentCode,
                'new_parent_code' => $placementParent->member_code,
                'member_code' => $child->member_code,
            ];
        });
    }

    public function register(array $payload, ?Member $actor = null): Member
    {
        return DB::transaction(function () use ($payload, $actor): Member {
            $mode = $payload['payment_mode_list'] ?? 'ewallet_check_mode';

            if ($mode === 'admin') {
                return $this->createAdminMember($payload, $actor);
            }

            $root = $this->resolveMember($payload['member_id_checking']);
            $settlement = $this->prepareRegistrationSettlement($payload);
            $placementParent = $root->member_code === 1 ? $root : $this->findPlacementParent($root);
            $sponsorCode = $actor?->member_code ?? $root->member_code;

            $member = $this->createMemberRecord(
                $payload,
                $placementParent->member_code,
                $sponsorCode,
                1,
                'enabled'
            );

            if ($root->member_code !== 1) {
                DB::table('builder')->insert([
                    'parent_code' => $placementParent->member_code,
                    'member_id' => $member->member_id,
                    'status' => 'unpaid',
                ]);
            }

            $this->applyRegistrationSettlement($member, $root, $actor, $payload, $settlement);

            if ($root->member_code !== 1) {
                $this->afterMemberInserted($root, $placementParent);
            }

            return $member->fresh(['category']);
        });
    }

    private function prepareRegistrationSettlement(array $payload): array
    {
        $mode = $payload['payment_mode_list'] ?? 'ewallet_check_mode';
        $amount = $this->inscriptionAmount();
        $provenance = trim(sprintf(
            '%s %s %s',
            $payload['name'] ?? $payload['member_name'] ?? '',
            $payload['lastname'] ?? $payload['member_lastname'] ?? '',
            $payload['pseudo'] ?? $payload['member_pseudo'] ?? '',
        ));

        if ($mode === 'accountancy_mode') {
            $paymentEvidence = (int) ($payload['payment_evidence'] ?? 0);
            $settings = DB::table('adhesionpointsettings')->first();

            if (! $settings || $settings->status !== 'available') {
                throw ValidationException::withMessages([
                    'payment_evidence' => 'La configuration des points d adhesion est indisponible.',
                ]);
            }

            $entry = DB::table('entries_accountancy')
                ->where('id_entry_accountancy', $paymentEvidence)
                ->where('provenance_entry', preg_replace('/\s+/', ' ', $provenance))
                ->where('used', 0)
                ->whereNull('adhesion')
                ->where('point', '>=', $settings->pointvalue)
                ->first();

            if (! $entry) {
                throw ValidationException::withMessages([
                    'payment_evidence' => 'La reference comptable ne valide pas cette adhesion.',
                ]);
            }

            return [
                'mode' => $mode,
                'amount' => $amount,
                'entry_id' => $paymentEvidence,
                'payer' => null,
            ];
        }

        $payerIdentifier = $payload['e_wallet_account'] ?? null;
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
        Member $newMember,
        Member $root,
        ?Member $actor,
        array $payload,
        array $settlement
    ): void {
        $amount = (float) $settlement['amount'];
        $mode = $settlement['mode'];
        $payer = $settlement['payer'];
        $sponsor = $actor ?? $root;
        $gift = 6.0;
        $wording = 'Frais Adhesion '.$newMember->name.' '.$newMember->lastname;

        if ($mode === 'ewallet_check_mode' && $payer instanceof Member) {
            $this->updateMemberBalance($payer->member_code, -$amount);

            DB::table('wayout_account_member')->insert([
                'amount' => $amount,
                'wording' => $wording,
                'member_id' => $payer->member_id,
                'transaction_type' => 'adhesion',
                'date_wayout' => now(),
            ]);

            $this->notifications->push(
                $payer->member_id,
                'Votre compte E-wallet a ete deduit de '.$amount.'$ pour l adhesion du membre '.$newMember->name.' '.$newMember->lastname
            );
        }

        if ($mode === 'vip_packet_mode' && $payer instanceof Member) {
            DB::table('members')
                ->where('member_code', $payer->member_code)
                ->update([
                    'pdfpaquet' => DB::raw('COALESCE(pdfpaquet, 0) - 1'),
                ]);

            $this->notifications->push(
                $payer->member_id,
                'Votre coffret VIP a ete deduit d un coffret pour l adhesion du membre '.$newMember->name.' '.$newMember->lastname
            );
        }

        if (in_array($mode, ['ewallet_check_mode', 'vip_packet_mode'], true)) {
            $this->updateMemberBalance(1, $amount);
        }

        if ($mode === 'accountancy_mode') {
            DB::table('entries_accountancy')
                ->where('id_entry_accountancy', $settlement['entry_id'])
                ->update([
                    'used' => 1,
                    'adhesion' => $newMember->member_code,
                ]);
        }

        $this->updateMemberBalance(1, -$gift);
        $this->updateMemberBalance($sponsor->member_code, $gift);

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
            'wording' => 'Gift adhesion '.$newMember->member_id,
            'done_by' => $actor?->member_id ?? $root->member_id,
            'date_wayout_accountancy' => now(),
        ]);

        $this->notifications->push(
            $sponsor->member_id,
            'Vous avez ajoute un nouveau membre en la personne de '.$newMember->name.' '.$newMember->lastname
        );

        $gender = ($newMember->gender ?? 'M') === 'F' ? 'Madame' : 'Monsieur';
        $welcome = ($newMember->gender ?? 'M') === 'F' ? 'la bienvenue' : 'le bienvenu';

        $this->notifications->push(
            $newMember->member_id,
            $gender.' '.$newMember->name.' '.$newMember->lastname.' l equipe Progress Business vous souhaite '.$welcome.'. Profitez de votre temps et devenez riche...!'
        );
    }

    private function createAdminMember(array $payload, ?Member $actor): Member
    {
        return $this->createMemberRecord(
            $payload,
            0,
            $actor?->member_code ?? 1,
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
    ): Member {
        $memberId = (string) ($payload['member_id'] ?? '');

        if (Member::query()->where('member_id', $memberId)->exists()) {
            throw ValidationException::withMessages([
                'member_id' => 'Cet identifiant membre existe deja.',
            ]);
        }

        $username = (string) ($payload['username'] ?? '');
        $baseUsername = str_contains($username, '@') ? $username : $username.'@pro.com';

        if (Member::query()->where('username', $baseUsername)->exists()) {
            $baseUsername = preg_replace('/@/', now()->format('is').'@', $baseUsername, 1);
        }

        $password = (string) ($payload['password'] ?? '');
        if ($password === '') {
            $password = (string) random_int(100000, 999999);
        }

        $record = [
            'member_id' => $memberId,
            'name' => $payload['name'],
            'lastname' => $payload['lastname'],
            'pseudo' => $payload['pseudo'],
            'telephone' => $payload['telephone'],
            'email' => $payload['email'],
            'gender' => $payload['gender'],
            'password' => $password,
            'username' => $baseUsername,
            'date' => now(),
            'categorie_id' => (int) $payload['categorie_id'],
            'parent_code' => $parentCode,
            'sponsor_code' => $sponsorCode,
            'e_mobile_number' => $payload['e_mobile_number'] ?? '',
            'bank_name' => $payload['bank_name'] ?? '',
            'bank_account' => $payload['bank_account'] ?? '',
            'total_amount_e_wallet' => (float) ($payload['total_amount_e_wallet'] ?? 0),
            'password_e_wallet' => $payload['password_e_wallet'] ?? $password,
            'inscription_mode' => $payload['payment_mode_list'] ?? 'ewallet_check_mode',
            'member_statute' => $status,
            'actual_level' => $level,
            'pdfpaquet' => (float) ($payload['pdfpaquet'] ?? 0),
            'adress' => $payload['adress'] ?? null,
            'city' => $payload['city'] ?? null,
        ];

        DB::table('members')->insert($record);

        return Member::query()->where('member_id', $memberId)->firstOrFail();
    }

    private function afterMemberInserted(Member $root, Member $placementParent): void
    {
        if ($this->childrenCount($placementParent->member_code) >= 4) {
            $this->promoteRootMember($placementParent->member_code, 2);
        }

        $this->syncRootProgression($root->member_code);
    }

    private function syncRootProgression(int $memberCode): void
    {
        $member = $this->resolveMember($memberCode);
        $currentLevel = (int) $member->actual_level;
        $achievedLevel = max(1, $currentLevel);

        for ($depth = 1; $depth <= 8; $depth++) {
            $countAtDepth = $this->countMembersAtDepth($memberCode, $depth);

            if ($countAtDepth >= (4 ** $depth)) {
                $achievedLevel = max($achievedLevel, $depth + 1);
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

        DB::table('members')
            ->where('member_code', $memberCode)
            ->update(['actual_level' => $newLevel]);

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

        DB::table('members')
            ->where('member_code', $memberCode)
            ->update(['actual_level' => $newLevel]);

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
        $childMatch = Member::query()
            ->where('parent_code', $memberCode)
            ->where('actual_level', $level)
            ->orderBy('member_code')
            ->value('member_code');

        if ($childMatch) {
            return (int) $childMatch;
        }

        $ancestorCode = (int) Member::query()
            ->where('member_code', $memberCode)
            ->value('parent_code');

        $iterations = 0;

        while ($ancestorCode > 0 && $iterations < 8) {
            $ancestorLevel = (int) Member::query()
                ->where('member_code', $ancestorCode)
                ->value('actual_level');

            if ($ancestorLevel === $level) {
                return $ancestorCode;
            }

            $ancestorCode = (int) Member::query()
                ->where('member_code', $ancestorCode)
                ->value('parent_code');
            $iterations++;
        }

        return 1;
    }

    private function findPlacementParent(Member $root): Member
    {
        $queue = [[$root, 0]];

        while ($queue !== []) {
            [$current, $depth] = array_shift($queue);

            if ($this->childrenCount($current->member_code) < 4) {
                return $current;
            }

            if ($depth >= 7) {
                continue;
            }

            $children = Member::query()
                ->where('parent_code', $current->member_code)
                ->orderBy('member_code')
                ->get();

            foreach ($children as $child) {
                $queue[] = [$child, $depth + 1];
            }
        }

        throw ValidationException::withMessages([
            'member_id_checking' => 'Aucun emplacement disponible dans cette matrice MLM.',
        ]);
    }

    private function childrenCount(int $memberCode): int
    {
        return Member::query()
            ->where('parent_code', $memberCode)
            ->count();
    }

    private function countMembersAtDepth(int $rootCode, int $targetDepth): int
    {
        $currentLevel = [$rootCode];
        $depth = 0;

        while ($currentLevel !== [] && $depth < $targetDepth) {
            $nextLevel = Member::query()
                ->whereIn('parent_code', $currentLevel)
                ->pluck('member_code')
                ->map(static fn ($value): int => (int) $value)
                ->all();

            $depth++;

            if ($depth === $targetDepth) {
                return count($nextLevel);
            }

            $currentLevel = $nextLevel;
        }

        return 0;
    }

    private function updateMemberBalance(int $memberCode, float $delta): void
    {
        DB::table('members')
            ->where('member_code', $memberCode)
            ->update([
                'total_amount_e_wallet' => DB::raw('total_amount_e_wallet + ('.(float) $delta.')'),
            ]);
    }

    private function inscriptionAmount(): float
    {
        return (float) (DB::table('inscription_cost')->value('amount') ?? 40);
    }
}
