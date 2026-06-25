<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Services\MlmService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends ApiController
{
    public function __construct(
        private readonly MlmService $mlm,
    ) {
    }

    public function index(Request $request)
    {
        $members = Member::query()
            ->with('category')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $query->where(function ($sub) use ($request): void {
                    $sub->where('member_id', 'like', '%'.$request->string('q').'%')
                        ->orWhere('username', 'like', '%'.$request->string('q').'%')
                        ->orWhere('name', 'like', '%'.$request->string('q').'%')
                        ->orWhere('lastname', 'like', '%'.$request->string('q').'%')
                        ->orWhere('pseudo', 'like', '%'.$request->string('q').'%');
                });
            })
            ->when($request->filled('categorie_id'), fn ($query) => $query->where('categorie_id', (int) $request->categorie_id))
            ->when($request->filled('member_statute'), fn ($query) => $query->where('member_statute', $request->member_statute))
            ->when($request->filled('sponsor_code'), fn ($query) => $query->where('sponsor_code', (int) $request->sponsor_code))
            ->when($request->filled('parent_code'), fn ($query) => $query->where('parent_code', (int) $request->parent_code))
            ->when($request->filled('actual_level'), fn ($query) => $query->where('actual_level', (int) $request->actual_level))
            ->orderByDesc('member_code')
            ->paginate((int) $request->input('per_page', 25));

        return $this->ok([
            'items' => MemberResource::collection($members->items()),
            'meta' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'member_id' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'lastname' => ['required', 'string', 'max:100'],
            'pseudo' => ['required', 'string', 'max:100'],
            'telephone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email'],
            'gender' => ['required', 'in:M,F'],
            'password' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100'],
            'categorie_id' => ['required', 'integer'],
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
