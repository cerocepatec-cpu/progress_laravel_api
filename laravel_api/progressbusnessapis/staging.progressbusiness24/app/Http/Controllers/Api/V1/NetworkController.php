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
    ) {
    }

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

        return $this->ok(collect($items)->map(fn (array $item) => [
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

    public function qualified(string $level)
    {
        $items = $this->mlm->qualifiedMembers($level);

        return $this->ok(MemberResource::collection($items));
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
        return collect($items)->map(fn (array $item) => [
            'member' => MemberResource::make($item['member']),
            'children' => $this->serializeTree($item['children']),
        ])->all();
    }
}
