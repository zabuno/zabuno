<?php

declare(strict_types=1);

namespace Tests\Feature\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Blind RED test candidate for GET /api/workspaces/{workspace}/team/invitations
 * (read-only pending Team invitations list, S1-WP01A smallest server-authoritative
 * Team invitations slice). No production code for this endpoint exists yet: grep
 * of routes/api.php confirms zero "team/invitations" routes are registered, so
 * every request below is expected to fail RED with a 404 from the framework's
 * router (unmatched route), not from malformed fixtures or guessed production
 * response shapes.
 *
 * Frozen contract: access requires Permission::WorkspaceManage (owner-only, same
 * as Team members list — Editor cannot access Team); ordinary member/editor and
 * strangers get an enumeration-safe 404 identical to a nonexistent workspace;
 * auth+verified is mandatory; response is a bare JSON array ordered
 * deterministically by invitation id, PENDING invitations only, each item
 * containing only id/email/role/status — never invited_by, timestamps, or any
 * other column.
 *
 * Requirement IDs: TEAM-INVITATIONS-LIST-01, TEAM-INVITATIONS-LIST-EMPTY-01,
 * TEAM-INVITATIONS-LIST-PENDING-ONLY-01, TEAM-INVITATIONS-OWNER-ONLY-01,
 * TEAM-INVITATIONS-TENANT-ESCAPE-01, TEAM-INVITATIONS-AUTH-01,
 * TEAM-INVITATIONS-SHAPE-01.
 */
final class ListTeamInvitationsTest extends TestCase
{
    use RefreshDatabase;

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function verifiedUser(string $name = 'Ayşe Yılmaz', ?string $email = null): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email ?? sprintf('%s@example.test', bin2hex(random_bytes(6))),
            'email_verified_at' => now(),
        ]);
    }

    private function workspaceOwnedBy(User $owner, string $name, string $slug): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $workspaceId;
    }

    private function addMember(int $workspaceId, User $user, string $role = 'member'): void
    {
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Fixtures that must seed a row directly into team_invitations first check
     * the table exists. If it does not, we fail with an explicit schema
     * assertion naming the missing table — the RED signal must point at the
     * missing migration, never surface as an unhandled PDO/query exception.
     */
    private function ensureTeamInvitationsSchemaForFixture(): void
    {
        if (! Schema::hasTable('team_invitations')) {
            self::fail('TEAM-INVITATIONS-SCHEMA-01: team_invitations tablosu henüz yok — bu RED, eksik migration/şemadan kaynaklanmalı, fixture kurulumunda gizli bir sorgu hatasından değil.');
        }
    }

    private function insertPendingInvitation(int $workspaceId, string $email, string $role, int $invitedBy): int
    {
        $this->ensureTeamInvitationsSchemaForFixture();

        return (int) DB::table('team_invitations')->insertGetId([
            'workspace_id' => $workspaceId,
            'email' => $email,
            'role' => $role,
            'status' => 'pending',
            'invited_by' => $invitedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertInvitationWithStatus(int $workspaceId, string $email, string $role, string $status, int $invitedBy): int
    {
        $this->ensureTeamInvitationsSchemaForFixture();

        return (int) DB::table('team_invitations')->insertGetId([
            'workspace_id' => $workspaceId,
            'email' => $email,
            'role' => $role,
            'status' => $status,
            'invited_by' => $invitedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function invitationsUri(int $workspaceId): string
    {
        return "/api/workspaces/{$workspaceId}/team/invitations";
    }

    // --- TEAM-INVITATIONS-LIST-01 -------------------------------------------

    public function test_owner_lists_pending_invitations_ordered_by_invitation_id_with_strict_frozen_fields(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-list-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-list-01');

        $this->insertPendingInvitation($workspaceId, 'mehmet-inv-list-01@example.test', 'editor', $owner->id);
        $this->insertPendingInvitation($workspaceId, 'elif-inv-list-01@example.test', 'editor', $owner->id);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->invitationsUri($workspaceId));

        $response->assertStatus(200, 'TEAM-INVITATIONS-LIST-01: sahibi kendi workspace bekleyen davet listesini 200 ile almalı.');
        $body = $response->json();

        self::assertIsArray($body, 'TEAM-INVITATIONS-LIST-01: yanıt gövdesi çıplak bir dizi olmalı.');
        self::assertCount(2, $body, 'TEAM-INVITATIONS-LIST-01: 2 bekleyen davet dönmeli.');

        self::assertSame('mehmet-inv-list-01@example.test', $body[0]['email'] ?? null, 'TEAM-INVITATIONS-LIST-01: sıralama davet id sırasına göre — önce eklenen ilk sırada.');
        self::assertSame('elif-inv-list-01@example.test', $body[1]['email'] ?? null, 'TEAM-INVITATIONS-LIST-01: sonra eklenen ikinci sırada.');

        foreach ($body as $item) {
            /*
                `delivery` SÜTUN DEĞİL, TÜRETİLMİŞ HÂLDİR (FF-160, `docs/110` P0-06).

                Bu iddianın aslı "yalnız dört alan" değil, "ham sütun
                sızmasın"dı: `invited_by`, zaman damgaları, `workspace_id`.
                `delivery` bunların hiçbiri değil — iki sütundan türetilen tek
                kelimelik bir hâl ("sent"/"failed"/"unknown"). Alan eklendi
                çünkü onsuz sahip, e-postası hiç çıkmamış bir daveti
                ekranında ulaşmış bir davetten ayırt edemiyordu.
            */
            self::assertSame(
                ['id', 'email', 'role', 'status', 'delivery'],
                array_keys($item),
                'TEAM-INVITATIONS-SHAPE-01: kayıt yalnız bu alanları taşımalı — ham teslimat sütunları değil.'
            );
            self::assertSame('editor', $item['role']);
            self::assertSame('pending', $item['status']);
        }
    }

    // --- TEAM-INVITATIONS-LIST-EMPTY-01 -------------------------------------

    public function test_owner_with_no_invitations_receives_empty_array(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-list-empty-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-list-empty-01');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->invitationsUri($workspaceId));

        $response->assertStatus(200, 'TEAM-INVITATIONS-LIST-EMPTY-01: davet olmayan workspace için 200 dönmeli.');
        $body = $response->json();

        self::assertIsArray($body);
        self::assertCount(0, $body, 'TEAM-INVITATIONS-LIST-EMPTY-01: davet yoksa sahte veri değil boş dizi dönmeli.');
    }

    // --- TEAM-INVITATIONS-LIST-PENDING-ONLY-01 ------------------------------

    public function test_only_pending_invitations_are_returned_not_accepted_or_cancelled(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-pending-only-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-pending-only-01');

        $this->insertPendingInvitation($workspaceId, 'pending-inv-pending-only-01@example.test', 'editor', $owner->id);
        $this->insertInvitationWithStatus($workspaceId, 'accepted-inv-pending-only-01@example.test', 'editor', 'accepted', $owner->id);
        $this->insertInvitationWithStatus($workspaceId, 'cancelled-inv-pending-only-01@example.test', 'editor', 'cancelled', $owner->id);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->invitationsUri($workspaceId));

        $response->assertStatus(200);
        $body = $response->json();

        self::assertCount(1, $body, 'TEAM-INVITATIONS-LIST-PENDING-ONLY-01: yalnız pending durumundaki davet dönmeli.');
        self::assertSame('pending-inv-pending-only-01@example.test', $body[0]['email'] ?? null);
        self::assertSame('pending', $body[0]['status'] ?? null);
    }

    // --- TEAM-INVITATIONS-LIST-TENANT-SCOPE-01 ------------------------------

    public function test_pending_invitation_from_a_second_workspace_owned_by_the_same_owner_is_excluded(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-list-tenant-01@example.test');
        $workspaceOneId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-list-tenant-01');
        $workspaceTwoId = $this->workspaceOwnedBy($owner, 'Nar Restoranları', 'nar-inv-list-tenant-01');

        $this->insertPendingInvitation($workspaceOneId, 'own-workspace-inv-list-tenant-01@example.test', 'editor', $owner->id);
        $this->insertPendingInvitation($workspaceTwoId, 'other-workspace-inv-list-tenant-01@example.test', 'editor', $owner->id);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->invitationsUri($workspaceOneId));

        $response->assertStatus(200);
        $body = $response->json();

        self::assertCount(1, $body, 'TEAM-INVITATIONS-LIST-TENANT-SCOPE-01: aynı sahibin ikinci workspace\'indeki davet sızmamalı — yalnız istenen workspace\'in daveti dönmeli.');
        self::assertSame('own-workspace-inv-list-tenant-01@example.test', $body[0]['email'] ?? null);
    }

    // --- TEAM-INVITATIONS-OWNER-ONLY-01 -------------------------------------

    public function test_member_and_editor_are_denied_with_enumeration_safe_404_not_403(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-owner-only-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-owner-only-01');

        $member = $this->verifiedUser('Mehmet Demir', 'mehmet-inv-owner-only-01@example.test');
        $this->addMember($workspaceId, $member, 'member');

        $editor = $this->verifiedUser('Elif Kaya', 'elif-inv-owner-only-01@example.test');
        $this->addMember($workspaceId, $editor, 'editor');

        $memberResponse = $this->actingAs($member)->withHeaders($this->jsonHeaders())
            ->getJson($this->invitationsUri($workspaceId));
        $memberResponse->assertStatus(404, 'TEAM-INVITATIONS-OWNER-ONLY-01: member workspace.manage taşımaz — davet listesi 404 ile reddedilmeli.');

        $editorResponse = $this->actingAs($editor)->withHeaders($this->jsonHeaders())
            ->getJson($this->invitationsUri($workspaceId));
        $editorResponse->assertStatus(404, 'TEAM-INVITATIONS-OWNER-ONLY-01: editor davet listesine erişemez — 404 ile reddedilmeli, 403 değil.');
    }

    // --- TEAM-INVITATIONS-TENANT-ESCAPE-01 ----------------------------------

    public function test_foreign_or_no_membership_user_gets_enumeration_safe_404_matching_nonexistent_workspace(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-escape-01@example.test');
        $stranger = $this->verifiedUser('Can Öztürk', 'can-inv-escape-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-escape-01');

        $strangerResponse = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->getJson($this->invitationsUri($workspaceId));
        $strangerResponse->assertStatus(404, 'TEAM-INVITATIONS-TENANT-ESCAPE-01: üye olunmayan workspace davet listesi okunamaz (404).');

        $nonexistentResponse = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->getJson($this->invitationsUri(999999999));

        self::assertSame(
            $strangerResponse->getStatusCode(),
            $nonexistentResponse->getStatusCode(),
            'TEAM-INVITATIONS-TENANT-ESCAPE-01: yabancı ve var-olmayan workspace aynı imzayı taşımalı (enumeration-safe).'
        );
    }

    // --- TEAM-INVITATIONS-AUTH-01 -------------------------------------------

    public function test_endpoint_requires_authenticated_and_verified_user(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-auth-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-auth-01');
        $unverified = User::factory()->unverified()->create();

        $guestResponse = $this->withHeaders($this->jsonHeaders())->getJson($this->invitationsUri($workspaceId));
        self::assertContains(
            $guestResponse->getStatusCode(),
            [401, 403],
            'TEAM-INVITATIONS-AUTH-01: kimliksiz istek 401/403 ile reddedilmeli (404 kabul edilmez).'
        );

        $unverifiedResponse = $this->actingAs($unverified)->withHeaders($this->jsonHeaders())
            ->getJson($this->invitationsUri($workspaceId));
        $unverifiedResponse->assertStatus(403, 'TEAM-INVITATIONS-AUTH-01: doğrulanmamış (unverified) kullanıcı 403 ile reddedilmeli.');
    }

    // --- TEAM-INVITATIONS-SHAPE-01 ------------------------------------------

    public function test_response_never_leaks_invited_by_timestamps_or_workspace_id(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-shape-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-shape-01');

        $this->insertPendingInvitation($workspaceId, 'mehmet-inv-shape-01@example.test', 'editor', $owner->id);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->invitationsUri($workspaceId));

        $response->assertStatus(200);
        $body = $response->json();

        self::assertIsArray($body);
        self::assertNotEmpty($body);

        foreach ($body as $item) {
            self::assertIsArray($item);
            self::assertSame(['id', 'email', 'role', 'status', 'delivery'], array_keys($item));
            self::assertArrayNotHasKey('invited_by', $item);
            // Teslimat sütunlarının HAM hâli asla çıkmaz: kaydedilen sebep
            // sağlayıcı adresini ve yanıt gövdesini taşıyabilir (FF-160).
            self::assertArrayNotHasKey('delivered_at', $item);
            self::assertArrayNotHasKey('delivery_failure', $item);
            self::assertArrayNotHasKey('workspace_id', $item);
            self::assertArrayNotHasKey('created_at', $item);
            self::assertArrayNotHasKey('updated_at', $item);
        }
    }
}
