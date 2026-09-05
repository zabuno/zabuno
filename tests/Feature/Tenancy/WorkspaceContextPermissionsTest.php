<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `docs/98` FF-74: bağlam ucu izinleri ve bayrakları taşır; ön uç yetkisiz
 * eylemi hiç çizmez.
 *
 * Kullanıcı yolculuğu: Editor Ayşe kabuğu açar → sunucu "billing.view yok,
 * workspace.manage yok" der → kenar çubuğunda Team ve Billing yok; 403
 * görmeden çalışır.
 */
final class WorkspaceContextPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(User $owner): int
    {
        $id = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'ctx-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->member($id, $owner, 'owner');

        return $id;
    }

    private function member(int $workspaceId, User $user, string $role): void
    {
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $user->id, 'role' => $role,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function api(User $user)
    {
        return $this->actingAs($user)->withHeaders(['Accept' => 'application/json']);
    }

    #[Test]
    public function switching_context_returns_the_owners_full_permission_set_role_and_feature_flags(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $workspaceId = $this->workspace($owner);

        $body = $this->api($owner)->putJson('/api/workspace-context', ['workspace_id' => $workspaceId])
            ->assertOk()->json();

        self::assertSame('owner', $body['role']);
        // 15 → 17: Mutfak rolüyle birlikte `menu.manage`'in içinden iki dar
        // eksen çıkarıldı (`docs/109` §6.4). Sahibin listesi daralmadı,
        // yalnız aynı yetkiyi daha ince anlatır oldu.
        self::assertCount(17, $body['permissions']);
        self::assertContains('billing.manage', $body['permissions']);
        self::assertContains('media.manage', $body['permissions']);
        self::assertContains('menu.allergens.manage', $body['permissions']);
        self::assertContains('menu.stock.manage', $body['permissions']);
        self::assertTrue($body['features']['novice-home'], 'Bayrak varsayılan açık.');

        $again = $this->api($owner)->getJson('/api/workspace-context')->assertOk()->json();
        self::assertSame($body['permissions'], $again['permissions']);
    }

    #[Test]
    public function an_editor_sees_only_what_they_can_do(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $workspaceId = $this->workspace($owner);
        $editor = User::factory()->create(['email_verified_at' => now()]);
        $this->member($workspaceId, $editor, 'editor');

        $body = $this->api($editor)->putJson('/api/workspace-context', ['workspace_id' => $workspaceId])
            ->assertOk()->json();

        self::assertSame('editor', $body['role']);
        self::assertContains('menu.manage', $body['permissions']);
        self::assertNotContains('workspace.manage', $body['permissions']);
        self::assertNotContains('billing.view', $body['permissions']);
        self::assertNotContains('menu.publish', $body['permissions']);
    }

    #[Test]
    public function a_flag_can_be_switched_off_for_one_workspace_without_touching_another(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $first = $this->workspace($owner);
        $second = $this->workspace($owner);

        Feature::for("workspace:{$first}")->deactivate('novice-home');

        $a = $this->api($owner)->putJson('/api/workspace-context', ['workspace_id' => $first])->assertOk()->json();
        $b = $this->api($owner)->putJson('/api/workspace-context', ['workspace_id' => $second])->assertOk()->json();

        self::assertFalse($a['features']['novice-home']);
        self::assertTrue($b['features']['novice-home']);
    }
}
