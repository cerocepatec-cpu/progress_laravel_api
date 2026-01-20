<?php
// app/Http/Middleware/CheckUserIsActive.php

namespace App\Http\Middleware;

use App\Events\UserDeactivated;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->status==="disabled") { // adapte le champ : is_active / status / etc.
            // 🔥 push event vers Redis → Node.js
            event(new UserDeactivated($user->id));

            Auth::logout();

            return response()->json([
                'message' => 'Votre compte a été désactivé par un administrateur.'
            ], 403);
        }

        return $next($request);
    }
}
