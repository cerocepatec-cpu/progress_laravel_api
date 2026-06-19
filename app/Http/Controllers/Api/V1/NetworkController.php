<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\MemberResource;
use App\Services\MlmService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NetworkController extends ApiController
{
    public function __construct(
        private readonly MlmService $mlm,
    ) {}


    public function directs(Request $request)
    {
        $items = $this->mlm->directs($request->user());

        return $this->ok(MemberResource::collection($items));
    }

    public function tree(Request $request, ?string $identifier = null)
    {
        $root = $identifier ? $this->mlm->resolveMember($identifier) : $request->user();

        return $this->ok([
            'root' => MemberResource::make($root->load('category')),
            'children' => $this->serializeTree($this->mlm->tree($root)),
        ]);
    }

    public function downline(Request $request, ?string $identifier = null)
    {
        $root = $identifier ? $this->mlm->resolveMember($identifier) : $request->user();
        $items = $this->mlm->flattenDownline($root);

        return $this->ok(collect($items)->map(fn(array $item) => [
            'depth' => $item['depth'],
            'member' => MemberResource::make($item['member']),
        ])->all());
    }

    public function permute(Request $request)
    {
        $data = $request->validate([
            'parent_identifier' => ['required', 'string'],
            'child_identifier' => ['required', 'string'],
        ]);

        $result = $this->mlm->permute($data['parent_identifier'], $data['child_identifier']);

        return $this->ok($result, 'Permutation effectuee avec succes.');
    }

    public function qualified(Request $request, string $level)
    {
        $items = $this->mlm->qualifiedMembers($request, $level);

        return $this->ok($items);
    }

    public function validateLevelPayment(Request $request, string $level)
    {
        $data = $request->validate([
            'member_identifier' => ['required', 'string'],
        ]);

        $this->mlm->validateLevelPayment($level, $data['member_identifier']);

        return $this->ok(null, 'Paiement du niveau valide.');
    }

    private function serializeTree(array $items): array
    {
        return collect($items)->map(fn(array $item) => [
            'member' => MemberResource::make($item['member']),
            'children' => $this->serializeTree($item['children']),
        ])->all();
    }

    public function downlineCount(Request $request, ?string $identifier = null)
    {
        $root = $identifier
            ? $this->mlm->resolveMember($identifier)
            : $request->user();

        $maxDepth = (int) $request->input('max_depth', 8);

        return $this->ok(
            $this->mlm->downlineCountByLevel(
                $root,
                $maxDepth
            )
        );
    }

    public function vipPacketDownlineCount(Request $request, ?string $identifier = null)
    {
        $root = $identifier
            ? $this->mlm->resolveMember($identifier)
            : $request->user();

        $count = $this->mlm->vipPacketDownlineCount($root);

        return $this->ok([
            'count' => $count,
        ]);
    }

    public function downlinePaginated(Request $request, ?string $identifier = null)
    {
        $root = $identifier
            ? $this->mlm->resolveMember($identifier)
            : $request->user();

        $filters = [
            'search' => $request->input('search'),
            'level' => $request->input('level'),
            'status' => $request->input('status'),
            'category_id' => $request->input('category_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $perPage = (int) $request->input('per_page', 20);

        $items = $this->mlm->paginatedDownline(
            $root,
            $filters,
            $perPage
        );

        return $this->ok([
            'items' => collect($items->items())->map(
                fn($item) => [
                    'depth' => $item['depth'],
                    'member' => MemberResource::make($item['member']),
                ]
            ),

            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function downlineByDate(Request $request, ?string $identifier = null)
    {
        $root = $identifier
            ? $this->mlm->resolveMember($identifier)
            : $request->user();

        $data = $this->mlm->downlineCountByDate(
            $root,
            $request->input('date_from'),
            $request->input('date_to'),
            $request->input('group_by', 'day')
        );

        return $this->ok($data);
    }

    public function latestDownlineMembers(Request $request, ?string $identifier = null)
    {
        $root = $identifier
            ? $this->mlm->resolveMember($identifier)
            : $request->user();

        $limit = (int) $request->input('limit', 10);

        $items = $this->mlm->latestDownlineMembers($root, $limit);

        return $this->ok([
            'items' => $items->map(
                fn($item) => [
                    'depth' => $item['depth'],
                    'member' => MemberResource::make($item['member']),
                ]
            ),
        ]);
    }
}
