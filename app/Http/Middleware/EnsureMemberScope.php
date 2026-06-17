<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\MemberAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberScope
{
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        /** @var User|null $member */
        $member = $request->user();

        foreach ($scopes as $scope) {
            if (MemberAccess::allows($member, $scope)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Acces refuse pour ce profil utilisateur.',
            'errors' => [],
        ], 403);
    }
}

