<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\SecurityService;

class CheckSecurity
{
    protected $security;

    public function __construct(SecurityService $security)
    {
        $this->security = $security;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $pin = $request->input('pin'); // facultatif selon l'opération
        $check = $this->security->validateUserForOperation($pin);

        if (!$check['success']) {
            return response()->json([
                'status' => $check['code'],
                'message' => 'error',
                'error' => $check['message'],
                'data' => null
            ], $check['code']);
        }

        return $next($request);
    }
}
