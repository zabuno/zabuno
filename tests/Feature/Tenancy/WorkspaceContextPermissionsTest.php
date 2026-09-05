<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domain\Authorization\Permission;
use App\Domain\Authorization\RolePermissions;
use App\Domain\Tenancy\MembershipRole;
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
        // 17 → 21: sipariş ekseni (FF-179, `docs/115` §4). Bu sayı ekranın
        // gördüğü listedir; panel yetkisiz eylemi hiç çizmediği için, eksik
        // gönderilen bir izin sahibin ekranından bir düğmenin sessizce
        // kaybolması demektir.
        // 21 → 23: puan ekseni (`docs/116` §4). Sahip puanları görür ve
        // yanıtlar; SİLMEZ — ve listede silecek bir izin hiç yoktur.
        self::assertCount(23, $body['permissions']);
        self::assertContains('billing.manage', $body['permissions']);
        self::assertContains('media.manage', $body['permissions']);
        self::assertContains('menu.allergens.manage', $body['permissions']);
        self::assertContains('menu.stock.manage', $body['permissions']);
        self::assertContains('order.view', $body['permissions']);
        self::assertContains('order.confirm', $body['permissions']);
        self::assertContains('order.kitchen', $body['permissions']);
        self::assertContains('order.settings', $body['permissions']);
        self::assertContains('rating.view', $body['permissions']);
        self::assertContains('rating.reply', $body['permissions']);
        self::assertNotContains('rating.delete', $body['permissions'], 'Sahip puanı silemez; panelin gördüğü listede böyle bir yetki yok.');
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

    /**
     * Mutfak rolü, izinleri doğruyken bile ADSIZ kalabiliyordu.
     *
     * Kullanıcı yolculuğu: aşçı Kemal kabuğu açar → yapabildikleri doğru
     * (alerjen ve "bugün bitti" var, fiyat yok) ama kabuk ona kim olduğunu
     * söyleyemez; rol rozeti boş kalır. İzin listesi kararı verir, rol adı
     * ise kullanıcının kendini tanıdığı yerdir — biri doğruyken diğerinin
     * boş olması, ekranı "yetkisiz" gibi gösterir.
     */
    #[Test]
    public function the_kitchen_role_is_named_in_the_context_body(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $workspaceId = $this->workspace($owner);
        $cook = User::factory()->create(['email_verified_at' => now()]);
        $this->member($workspaceId, $cook, 'kitchen');

        $this->api($cook)->putJson('/api/workspace-context', ['workspace_id' => $workspaceId])->assertOk();
        $body = $this->api($cook)->getJson('/api/workspace-context')->assertOk()->json();

        self::assertSame('kitchen', $body['role']);

        // Beklenen liste burada TEKRAR YAZILMAZ: tek doğru kaynak
        // `RolePermissions`. Elle kopyalanmış bir liste, rolün sınırı orada
        // değişince sessizce yalan söylerdi.
        $expected = array_map(
            static fn (Permission $permission): string => $permission->value,
            RolePermissions::for(MembershipRole::Kitchen),
        );
        sort($expected);
        $given = $body['permissions'];
        sort($given);

        self::assertSame($expected, $given);
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
