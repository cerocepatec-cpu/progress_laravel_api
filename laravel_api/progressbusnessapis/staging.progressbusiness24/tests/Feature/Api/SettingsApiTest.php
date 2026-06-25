<?php

namespace Tests\Feature\Api;

use App\Models\Member;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_settings_endpoints_return_legacy_configuration_rows(): void
    {
        $this->authenticateOwner();

        $majPoints = $this->getJson('/api/v1/settings/maj-points')
            ->assertOk()
            ->assertJsonPath('success', true);

        $adhesionPoints = $this->getJson('/api/v1/settings/adhesion-points')
            ->assertOk();

        $inscriptionCost = $this->getJson('/api/v1/settings/inscription-cost')
            ->assertOk();

        $validationExpiration = $this->getJson('/api/v1/settings/validation-expiration')
            ->assertOk();

        $this->assertSame((float) DB::table('majpointsettings')->value('pointvalue'), (float) $majPoints->json('data.pointvalue'));
        $this->assertSame((float) DB::table('adhesionpointsettings')->value('pointvalue'), (float) $adhesionPoints->json('data.pointvalue'));
        $this->assertSame((float) DB::table('inscription_cost')->value('amount'), (float) $inscriptionCost->json('data.amount'));
        $this->assertSame((int) DB::table('validation_member_expiration')->value('days_fixed'), (int) $validationExpiration->json('data.days_fixed'));
    }

    public function test_settings_updates_preserve_single_row_legacy_logic(): void
    {
        $this->authenticateOwner();

        $this->putJson('/api/v1/settings/maj-points', [
            'pointvalue' => 17,
            'status' => 'available',
        ])->assertOk();

        $this->putJson('/api/v1/settings/adhesion-points', [
            'pointvalue' => 12,
            'status' => 'available',
        ])->assertOk();

        $this->putJson('/api/v1/settings/inscription-cost', [
            'amount' => 55,
        ])->assertOk();

        $this->putJson('/api/v1/settings/validation-expiration', [
            'days_fixed' => 7,
        ])->assertOk();

        $this->assertSame(17.0, (float) DB::table('majpointsettings')->value('pointvalue'));
        $this->assertSame('available', DB::table('majpointsettings')->value('status'));
        $this->assertSame(12.0, (float) DB::table('adhesionpointsettings')->value('pointvalue'));
        $this->assertSame('available', DB::table('adhesionpointsettings')->value('status'));
        $this->assertSame(55.0, (float) DB::table('inscription_cost')->value('amount'));
        $this->assertSame(7, (int) DB::table('validation_member_expiration')->value('days_fixed'));
    }

    private function authenticateOwner(): void
    {
        $owner = Member::query()->where('member_code', 1)->firstOrFail();

        Sanctum::actingAs($owner);
    }
}
