<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LegacyLogicApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_notifications_follow_legacy_identifier_matching_and_read_flow(): void
    {
        $owner = $this->authenticateOwner();

        $baseId = (int) ((DB::table('notifications')->max('id_notification') ?? 0) + 1);

        DB::table('notifications')->insert([
            [
                'id_notification' => $baseId,
                'message_notified' => 'notification username test',
                'member_id' => $owner->username,
                'date_notification' => now(),
                'status_notification' => 'unread',
            ],
            [
                'id_notification' => $baseId + 1,
                'message_notified' => 'notification member id test',
                'member_id' => (string) $owner->member_id,
                'date_notification' => now(),
                'status_notification' => 'unread',
            ],
            [
                'id_notification' => $baseId + 2,
                'message_notified' => 'notification read test',
                'member_id' => $owner->username,
                'date_notification' => now(),
                'status_notification' => 'read',
            ],
            [
                'id_notification' => $baseId + 3,
                'message_notified' => 'notification other member test',
                'member_id' => '__other__',
                'date_notification' => now(),
                'status_notification' => 'unread',
            ],
        ]);

        $response = $this->getJson('/api/v1/notifications?limit=1000')
            ->assertOk()
            ->assertJsonPath('success', true);

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($baseId, $ids);
        $this->assertContains($baseId + 1, $ids);
        $this->assertNotContains($baseId + 2, $ids);
        $this->assertNotContains($baseId + 3, $ids);

        $this->postJson("/api/v1/notifications/{$baseId}/read")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(
            'read',
            DB::table('notifications')->where('id_notification', $baseId)->value('status_notification')
        );
    }

    public function test_authenticated_member_can_change_main_and_ewallet_passwords(): void
    {
        $owner = User::query()->where('member_code', 1)->firstOrFail();

        DB::table('members')->where('member_code', $owner->member_code)->update([
            'password' => Hash::make('secret-main-old'),
            'password_e_wallet' => Hash::make('secret-old'),
        ]);

        Sanctum::actingAs($owner->fresh());

        $this->postJson('/api/v1/auth/password', [
            'new_password' => 'new-main-pass',
            'new_password_confirmation' => 'new-main-pass',
        ])->assertOk();

        $this->postJson('/api/v1/auth/e-wallet-password', [
            'current_password' => 'secret-old',
            'new_password' => 'secret-new',
            'new_password_confirmation' => 'secret-new',
        ])->assertOk();

        $updated = $owner->fresh();

        $this->assertTrue(Hash::check('new-main-pass', $updated->password));
        $this->assertTrue(Hash::check('secret-new', $updated->password_e_wallet));
    }

    public function test_member_categories_crud_uses_legacy_categories_table(): void
    {
        $this->authenticateOwner();

        $create = $this->postJson('/api/v1/catalog/categories', [
            'categorie_name' => 'Categorie test API',
        ])->assertCreated();

        $categoryId = (int) $create->json('data.categorie_id');

        $this->assertSame(
            'Categorie test API',
            DB::table('categories')->where('categorie_id', $categoryId)->value('categorie_name')
        );

        $this->patchJson("/api/v1/catalog/categories/{$categoryId}", [
            'categorie_name' => 'Categorie test API maj',
        ])->assertOk();

        $this->assertSame(
            'Categorie test API maj',
            DB::table('categories')->where('categorie_id', $categoryId)->value('categorie_name')
        );

        $this->deleteJson("/api/v1/catalog/categories/{$categoryId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('categories', [
            'categorie_id' => $categoryId,
        ]);
    }

    public function test_buying_vip_packets_updates_legacy_balances_and_history(): void
    {
        $owner = $this->authenticateOwner();

        DB::table('members')->where('member_code', $owner->member_code)->update([
            'total_amount_e_wallet' => 500,
            'pdfpaquet' => 1,
        ]);

        Sanctum::actingAs($owner->fresh());

        $response = $this->postJson('/api/v1/accounting/vip-packets', [
            'number' => 2,
            'total' => 80,
        ])->assertCreated();

        $response->assertJsonPath('success', true)
            ->assertJsonPath('data.pdfpaquet', 3)
            ->assertJsonPath('data.balance', 420);

        $latestHistory = DB::table('coffrets_vip')
            ->where('member_id', $owner->member_id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($latestHistory);
        $this->assertSame(2, (int) $latestHistory->number);
        $this->assertSame(80.0, (float) $latestHistory->total);
        $this->assertSame(40.0, (float) $latestHistory->pu);

        $historyResponse = $this->getJson('/api/v1/accounting/vip-packets')
            ->assertOk()
            ->assertJsonPath('success', true);

        $historyIds = collect($historyResponse->json('data.history'))->pluck('id')->all();

        $this->assertContains((int) $latestHistory->id, $historyIds);
    }

    private function authenticateOwner(): Member
    {
        $owner = User::query()->where('member_code', 1)->firstOrFail();

        Sanctum::actingAs($owner);

        return $owner;
    }
}

