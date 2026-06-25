<?php

namespace App\Http\Middleware;

use Closure;
use Carbon\Carbon;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class UpdateUserSessionLastSeen
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
   
        public function handle(Request $request, Closure $next)
{
    if ($user = $request->user()) {

        $tokenId = $user->currentAccessToken()?->id;

        if ($tokenId) {

            $now = now();

            // 🔄 DB toujours à jour
            UserSession::where('access_token_id', $tokenId)
                ->update([
                    'last_seen_at' => $now,
                    'status'       => 'active',
                ]);

            // 📡 Redis throttlé (1 fois / 60s)
            if (Cache::add("user:last_seen:broadcast:{$user->id}", true, 60)) {

                Redis::publish('user.presence', json_encode([
                    'type' => 'last_seen',
                    'data' => [
                        'userId'       => $user->id,
                        'last_seen_at' => $now->toISOString(),
                        'status'       => 'active',
                    ]
                ]));
            }
        }
    }

    return $next($request);
}


}
