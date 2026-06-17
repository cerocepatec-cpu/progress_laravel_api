<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\MemberResource;
use App\Models\User;
use App\Support\LegacyPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends ApiController
{
    public function __construct(
        private readonly LegacyPassword $legacyPassword,
    ) {}

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['nullable', 'string', 'required_without:username'],
            'username' => ['nullable', 'string', 'required_without:login'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        $identifier = (string) ($data['login'] ?? $data['username'] ?? '');

        $member = User::query()
            ->with('category')
            ->where(function ($query) use ($identifier): void {
                $query->where('username', $identifier)
                    ->orWhere('member_id', $identifier)
                    ->orWhere('member_code', $identifier);
            })
            ->where('member_statute', 'enabled')
            ->first();

        if (! $member) {
            return $this->fail('Identifiants invalides.', 401);
        }

        // Nouveau hash Laravel
        if (Hash::check($data['password'], $member->password)) {

            // OK
        }

        // Ancien hash Progress Business
        elseif ($this->legacyPassword->check($data['password'], $member->password)) {

            // Migration automatique vers bcrypt
            $member->update([
                'password' => Hash::make($data['password']),
            ]);
        } else {
            return $this->fail('Identifiants invalides.', 401);
        }

        if ($request->hasSession()) {
            Auth::guard('web')->login($member);
            $request->session()->regenerate();
        }

        $tokenName = $data['device_name'] ?? 'progress-api';

        $member->tokens()->where('name', $tokenName)->delete();

        $member->forceFill([
            'last_connection' => now(),
        ])->save();
        $token = $member->createToken($tokenName)->plainTextToken;

        return $this->authenticatedResponse(
            $member,
            $token,
            'Connexion reussie.'
        );
    }

    public function me(Request $request)
    {
        $member = $this->authMember();

        return $this->ok(
            MemberResource::make($this->loadMemberRelations($member))
        );
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'new_password' => ['required', 'string', 'min:4', 'confirmed'],
        ]);

        $member = $this->authMember();

        $member->forceFill([
            'password' => $this->legacyPassword->hash($data['new_password']),
        ])->save();

        return $this->ok(null, 'Mot de passe principal mis a jour.');
    }

    public function changeEwalletPassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:4', 'confirmed'],
        ]);

        $member = $this->authMember();

        if (! $this->legacyPassword->check($data['current_password'], $member->password_e_wallet)) {
            return $this->fail("L'ancien mot de passe E-wallet n'est pas correct.", 422);
        }

        $member->forceFill([
            'password_e_wallet' => $this->legacyPassword->hash($data['new_password']),
        ])->save();

        return $this->ok(null, 'Mot de passe E-wallet mis a jour.');
    }

    public function logout(Request $request)
    {
        $member = $this->authMember();

        DB::table('last_sign_on')->where('member_code', $member->member_code)->delete();
        DB::table('last_sign_on')->insert([
            'member_code' => $member->member_code,
            'date_hour' => now(),
        ]);

        $this->revokeCurrentToken($request, $member);
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->clearAuthCookie(
            $this->ok(null, 'Session API fermee.')
        );
    }

    private function authenticatedResponse(User $member, string $token, string $message): JsonResponse
    {
        $secure = config('session.secure');

        if ($secure === null) {
            $secure = request()->isSecure();
        }

        $response = $this->ok([
            'token' => $token,
            'member' => MemberResource::make($this->loadMemberRelations($member)),
        ], $message);

        return $response->withCookie(
            cookie(
                (string) config('auth.token_cookie', 'progress_access_token'),
                $token,
                (int) config('session.lifetime', 120),
                config('session.path', '/'),
                config('session.domain'),
                $secure,
                true,
                false,
                config('session.same_site', 'lax'),
            )
        );
    }

    private function clearAuthCookie(JsonResponse $response): JsonResponse
    {
        return $response->withoutCookie(
            (string) config('auth.token_cookie', 'progress_access_token'),
            config('session.path', '/'),
            config('session.domain'),
        );
    }

    private function revokeCurrentToken(Request $request, User $member): void
    {
        $currentToken = $member->currentAccessToken();

        if ($currentToken instanceof PersonalAccessToken) {
            $currentToken->delete();

            return;
        }

        $plainTextToken = $request->bearerToken()
            ?: $request->cookie((string) config('auth.token_cookie', 'progress_access_token'));

        if (! $plainTextToken) {
            return;
        }

        $accessToken = PersonalAccessToken::findToken($plainTextToken);

        if (! $accessToken) {
            return;
        }

        if ($accessToken->tokenable_type !== $member::class || (string) $accessToken->tokenable_id !== (string) $member->getKey()) {
            return;
        }

        $accessToken->delete();
    }

    private function loadMemberRelations(User $member): User
    {
        $relations = ['category'];

        return $member->loadMissing($relations);
    }
}
