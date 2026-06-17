<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RouteScopeApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_member_routes_are_not_open_to_backoffice_routes(): void
    {
        $member = User::query()->where('categorie_id', 7)->firstOrFail();

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/mlm/dashboard')->assertOk();
        $this->getJson('/api/v1/mlm/members')->assertOk();
        $this->getJson('/api/v1/wallet/overview')->assertOk();
        $this->getJson('/api/v1/wallet/commissions')->assertOk();
        $this->getJson('/api/v1/accounting/summary')->assertOk();
        $this->getJson('/api/v1/members')->assertForbidden();
        $this->getJson('/api/v1/settings/maj-points')->assertForbidden();
    }

    public function test_backoffice_user_can_read_but_not_manage_backoffice(): void
    {
        $backofficeUser = User::query()
            ->where('categorie_id', 9)
            ->where('member_code', '<>', 1)
            ->firstOrFail();

        Sanctum::actingAs($backofficeUser);

        $this->getJson('/api/v1/mlm/dashboard')->assertOk();
        $this->getJson('/api/v1/mlm/members')->assertOk();
        $this->getJson('/api/v1/wallet/overview')->assertOk();
        $this->getJson('/api/v1/wallet/commissions')->assertOk();
        $this->getJson('/api/v1/members')->assertOk();
        $this->postJson('/api/v1/members', [])->assertForbidden();
        $this->getJson('/api/v1/settings/maj-points')->assertForbidden();
    }

    public function test_backoffice_admin_can_manage_but_not_use_admin_it_settings(): void
    {
        $admin = User::query()->where('categorie_id', 6)->firstOrFail();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/members')->assertOk();
        $this->postJson('/api/v1/members', [])->assertStatus(422);
        $this->getJson('/api/v1/settings/maj-points')->assertForbidden();
    }

    public function test_admin_it_can_access_admin_it_routes(): void
    {
        $adminIt = User::query()->where('member_code', 1)->firstOrFail();

        Sanctum::actingAs($adminIt);

        $this->getJson('/api/v1/settings/maj-points')->assertOk();
    }
}

