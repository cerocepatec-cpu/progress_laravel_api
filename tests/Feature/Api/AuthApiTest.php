<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    public function test_login_returns_a_token_for_existing_legacy_member(): void
    {
        $owner = User::query()->where('member_code', 1)->firstOrFail();

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => $owner->username,
            'password' => 'davida',
            'device_name' => 'phpunit',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.member.member_code', 1);

        $this->assertIsString($response->json('data.token'));
        $this->assertNotSame('', $response->json('data.token'));
    }

    public function test_me_endpoint_returns_authenticated_member(): void
    {
        $owner = User::query()->where('member_code', 1)->firstOrFail();

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.member_code', 1)
            ->assertJsonPath('data.member_id', $owner->member_id);
    }

    public function test_sign_in_alias_accepts_username_payload(): void
    {
        $owner = User::query()->where('member_code', 1)->firstOrFail();

        $response = $this->postJson('/api/auth/sign-in', [
            'username' => $owner->username,
            'password' => 'davida',
            'device_name' => 'phpunit-mobile',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.member.member_code', 1);

        $this->assertIsString($response->json('data.token'));
        $this->assertNotSame('', $response->json('data.token'));
    }
}

