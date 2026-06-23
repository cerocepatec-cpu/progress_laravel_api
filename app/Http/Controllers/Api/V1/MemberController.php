<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\MemberResource;
use App\Models\User;
use App\Services\MlmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MemberController extends ApiController
{
    public function __construct(
        private readonly MlmService $mlm,
    ) {}

    private const QUALIFIED_LEVELS = [
        'builder' => '1',
        'sapphire' => '2',
        'ruby' => '3',
        'emerald' => '4',
        'diamond' => '5',
        'diamond_crowned' => '6',
        'ambassador' => '7',
        'ambassador_crowned' => '8',
    ];

    public function index(Request $request)
    {
        $baseQuery = $this->membersIndexQuery($request);

        $members = (clone $baseQuery)
            ->orderByDesc('member_code')
            ->paginate((int) $request->input('per_page', 25));

        $levelStatsQuery = $this->membersIndexQuery($request, ['actual_level']);

        $levelsRows = (clone $levelStatsQuery)
            ->selectRaw('COALESCE(actual_level, 0) as actual_level, COUNT(*) as total')
            ->groupBy('actual_level')
            ->get()
            ->keyBy(fn($row) => (int) $row->actual_level);

        $levels = collect(self::QUALIFIED_LEVELS)
            ->map(function ($levelId, $key) use ($levelsRows) {
                $level = (int) $levelId;
                $row = $levelsRows->get($level);

                return [
                    'id' => $level,
                    'key' => $key,
                    'name' => ucwords(str_replace('_', ' ', $key)),
                    'count' => (int) ($row->total ?? 0),
                ];
            })
            ->values();

        $categoryStatsQuery = $this->membersIndexQuery($request, ['categorie_id']);

        $categoryRows = (clone $categoryStatsQuery)
            ->selectRaw('COALESCE(categorie_id, 0) as categorie_id, COUNT(*) as total')
            ->groupBy('categorie_id')
            ->get()
            ->keyBy(fn($row) => (int) $row->categorie_id);

        $categories = DB::table('categories')
            ->get()
            ->map(function ($category) use ($categoryRows) {
                $id = (int) ($category->categorie_id ?? $category->id ?? 0);
                $row = $categoryRows->get($id);

                return [
                    'id' => $id,
                    'name' => $category->name
                        ?? $category->category_name
                        ?? $category->categorie_name
                        ?? $category->description
                        ?? 'Catégorie ' . $id,
                    'count' => (int) ($row->total ?? 0),
                ];
            })
            ->values();

        return $this->ok([
            'items' => MemberResource::collection($members->items()),
            'meta' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
            ],
            'stats' => [
                'total' => (clone $baseQuery)->count(),
                'levels' => $levels,
                'categories' => $categories,
            ],
        ]);
    }

    private function membersIndexQuery(Request $request, array $except = [])
    {
        return User::query()
            ->with('category')
            ->when($request->filled('q') && ! in_array('q', $except, true), function ($query) use ($request): void {
                $q = trim((string) $request->string('q'));
                $terms = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);

                $query->where(function ($main) use ($terms): void {
                    foreach ($terms as $term) {
                        $main->orWhere(function ($sub) use ($term): void {
                            $sub->where('member_id', 'like', "%{$term}%")
                                ->orWhere('member_code', 'like', "%{$term}%")
                                ->orWhere('username', 'like', "%{$term}%")
                                ->orWhere('name', 'like', "%{$term}%")
                                ->orWhere('lastname', 'like', "%{$term}%")
                                ->orWhere('pseudo', 'like', "%{$term}%")
                                ->orWhere('telephone', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%");
                        });
                    }
                });
            })
            ->when($request->filled('categorie_id') && ! in_array('categorie_id', $except, true), fn($query) => $query->where('categorie_id', (int) $request->categorie_id))
            ->when($request->filled('member_statute') && ! in_array('member_statute', $except, true), fn($query) => $query->where('member_statute', $request->member_statute))
            ->when($request->filled('sponsor_code') && ! in_array('sponsor_code', $except, true), fn($query) => $query->where('sponsor_code', (int) $request->sponsor_code))
            ->when($request->filled('parent_code') && ! in_array('parent_code', $except, true), fn($query) => $query->where('parent_code', (int) $request->parent_code))
            ->when($request->filled('actual_level') && ! in_array('actual_level', $except, true), fn($query) => $query->where('actual_level', (int) $request->actual_level));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'member_id' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'lastname' => ['nullable', 'string', 'max:100'],
            'pseudo' => ['nullable', 'string', 'max:100'],
            'telephone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email'],
            'gender' => ['required', 'in:M,F'],
            'password' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100'],
            'categorie_id' => ['nullable', 'integer'],
            'member_id_checking' => ['required_unless:payment_mode_list,admin', 'string'],
            'e_mobile_number' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:255'],
            'total_amount_e_wallet' => ['nullable', 'numeric'],
            'password_e_wallet' => ['nullable', 'string', 'max:255'],
            'payment_mode_list' => ['required', Rule::in(['ewallet_check_mode', 'vip_packet_mode', 'accountancy_mode', 'admin'])],
            'e_wallet_account' => ['nullable', 'string'],
            'password_e_wallet_account' => ['nullable', 'string'],
            'adress' => ['nullable', 'string'],
            'payment_evidence' => ['nullable', 'integer'],
            'city' => ['nullable', 'integer'],
        ]);
        $member = $this->mlm->register($data, $request->user());
        return $this->ok(MemberResource::make($member), 'Membre enregistre avec succes.', 201);
    }

    public function storeNormal(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'lastname' => ['nullable', 'string', 'max:100'],
            'pseudo' => ['nullable', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'gender' => ['nullable', 'in:M,F'],
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
            'categorie_id' => ['nullable', 'integer'],
            'adress' => ['nullable', 'string'],
            'city' => ['nullable', 'integer'],
        ]);

        $user = $this->mlm->registerNormalUser($data);

        return $this->ok(
            MemberResource::make($user),
            'Utilisateur enregistré avec succès.',
            201
        );
    }
    
    public function show(string $identifier)
    {
        $member = $this->mlm->resolveMember($identifier)->load('category');

        return $this->ok(MemberResource::make($member));
    }

    public function update(Request $request, string $identifier)
    {
        $member = $this->mlm->resolveMember($identifier);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'lastname' => ['sometimes', 'string', 'max:100'],
            'pseudo' => ['sometimes', 'string', 'max:100'],
            'telephone' => ['sometimes', 'string', 'max:50'],
            'email' => ['sometimes', 'email'],
            'gender' => ['sometimes', 'in:M,F'],
            'password' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:100'],
            'categorie_id' => ['sometimes', 'integer'],
            'e_mobile_number' => ['sometimes', 'string', 'max:100'],
            'bank_name' => ['sometimes', 'string', 'max:255'],
            'bank_account' => ['sometimes', 'string', 'max:255'],
            'password_e_wallet' => ['sometimes', 'string', 'max:255'],
            'adress' => ['sometimes', 'nullable', 'string'],
        ]);

        $updated = $this->mlm->updateMember($member, $data);

        return $this->ok(MemberResource::make($updated), 'Membre mis a jour.');
    }

    public function updateStatus(Request $request, string $identifier)
    {
        $member = $this->mlm->resolveMember($identifier);
        $data = $request->validate([
            'member_statute' => ['required', Rule::in(['enabled', 'disabled', 'waiting'])],
        ]);

        $updated = $this->mlm->updateMemberStatus($member, $data['member_statute']);

        return $this->ok(MemberResource::make($updated), 'Statut du membre mis a jour.');
    }

    public function updateCity(Request $request, string $identifier)
    {
        $member = $this->mlm->resolveMember($identifier);
        $data = $request->validate([
            'city' => ['required', 'integer'],
        ]);

        $updated = $this->mlm->updateMemberCity($member, (int) $data['city']);

        return $this->ok(MemberResource::make($updated), 'Ville du membre mise a jour.');
    }

    public function destroy(string $identifier)
    {
        $member = $this->mlm->resolveMember($identifier);

        $member->delete();

        return $this->ok(null, 'Membre supprime.');
    }
}
