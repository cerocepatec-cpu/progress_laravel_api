<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Support\LegacyPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends ApiController
{
    public function __construct(
        private readonly LegacyPassword $legacyPassword,
    ) {
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['nullable', 'string', 'required_without:username'],
            'username' => ['nullable', 'string', 'required_without:login'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        $identifier = (string) ($data['login'] ?? $data['username'] ?? '');

        $member = Member::query()
            ->with('category')
            ->where(function ($query) use ($identifier): void {
                $query->where('username', $identifier)
                    ->orWhere('member_id', $identifier)
                    ->orWhere('member_code', $identifier);
            })
            ->where('member_statute', 'enabled')
            ->first();

        if (! $member || ! $this->legacyPassword->check($data['password'], $member->password)) {
            return $this->fail('Identifiants invalides.', 401);
        }

        $token = $member->createToken($data['device_name'] ?? 'progress-api')->plainTextToken;

        return $this->ok([
            'token' => $token,
            'member' => MemberResource::make($member),
        ], 'Connexion reussie.');
    }

    public function me(Request $request)
    {
        return $this->ok(MemberResource::make($request->user()->load('category')));
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'new_password' => ['required', 'string', 'min:4', 'confirmed'],
        ]);

        $request->user()->forceFill([
            'password' => $data['new_password'],
        ])->save();

        return $this->ok(null, 'Mot de passe principal mis a jour.');
    }

    public function changeEwalletPassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:4', 'confirmed'],
        ]);

        if (! $this->legacyPassword->check($data['current_password'], $request->user()->password_e_wallet)) {
            return $this->fail("L'ancien mot de passe E-wallet n'est pas correct.", 422);
        }

        $request->user()->forceFill([
            'password_e_wallet' => $data['new_password'],
        ])->save();

        return $this->ok(null, 'Mot de passe E-wallet mis a jour.');
    }

    public function logout(Request $request)
    {
        DB::table('last_sign_on')->where('member_code', $request->user()->member_code)->delete();
        DB::table('last_sign_on')->insert([
            'member_code' => $request->user()->member_code,
            'date_hour' => now(),
        ]);

        $request->user()->currentAccessToken()?->delete();

        return $this->ok(null, 'Session API fermee.');
    }
}
