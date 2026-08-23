<?php

declare(strict_types=1);

namespace Tests\Feature\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Blind RED test candidate for DELETE
 * /api/workspaces/{workspaceId}/team/invitations/{invitationId} (cancel a
 * pending Team invitation, S1-WP01A Team invitations write slice). No
 * production code for this endpoint exists yet: grep of routes/api.php
 * confirms zero "team/invitations/{invitation}" DELETE routes are
 * registered, so every request below is expected to fail RED with a 404
 * from the framework's router (unmatched route), not from malformed
 * fixtures or guessed production response shapes.
 *
 * Frozen contract: access requires Permission::WorkspaceManage (owner-only,
 * enumeration-safe 404 for non-owner before any business-state
 * validation runs); only a *pending* invitation belonging to the exact
 * requested workspace can be cancelled; success is 204 with no body, the
 * persisted row's status flips to "cancelled" (row is kept, not deleted),
 * and the row disappears from a pending-only listing; editor/member/
 * outsider cannot cancel (404); an invitation id that belongs to a
 * different workspace, or is already accepted/cancelled, is rejected with
 * a 404 and leaves the row byte-for-byte unchanged; auth+verified is
 * mandatory; the write is throttled to 5 requests per minute like other
 * Team mutations (throttle:5,1).
 *
 * Requirement IDs: TEAM-INVITATIONS-CANCEL-01,
 * TEAM-INVITATIONS-CANCEL-OWNER-ONLY-01,
 * TEAM-INVITATIONS-CANCEL-NOT-PENDING-01,
 * TEAM-INVITATIONS-CANCEL-CROSS-WORKSPACE-01,
 * TEAM-INVITATIONS-CANCEL-AUTH-01, TEAM-INVITATIONS-CANCEL-THROTTLE-01.
 */
final class CancelTeamInvitationTest extends TestCase
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

    private function ensureTeamInvitationsSchemaForFixture(): void
    {
        if (! Schema::hasTable('team_invitations')) {
            self::fail('TEAM-INVITATIONS-SCHEMA-01: team_invitations tablosu henüz yok — bu RED, eksik migration/şemadan kaynaklanmalı, fixture kurulumunda gizli bir sorgu hatasından değil.');
        }
    }

    private function insertInvitation(int $workspaceId, string $email, string $role, string $status, int $invitedBy): int
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

    private function invitationRow(int $invitationId): ?object
    {
        $this->ensureTeamInvitationsSchemaForFixture();

        return DB::table('team_invitations')->where('id', $invitationId)->first();
    }

    private function cancelUri(int $workspaceId, int $invitationId): string
    {
        return "/api/workspaces/{$workspaceId}/team/invitations/{$invitationId}";
    }

    // --- TEAM-INVITATIONS-CANCEL-01 -----------------------------------------

    public function test_owner_cancels_a_pending_invitation_with_204_and_row_becomes_cancelled_and_excluded_from_pending_list(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-cancel-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-cancel-01');

        $invitationId = $this->insertInvitation($workspaceId, 'pending-inv-cancel-01@example.test', 'editor', 'pending', $owner->id);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->cancelUri($workspaceId, $invitationId));

        $response->assertStatus(204, 'TEAM-INVITATIONS-CANCEL-01: sahibi pending daveti iptal ettiğinde 204 dönmeli.');
        self::assertSame('', $response->getContent(), 'TEAM-INVITATIONS-CANCEL-01: 204 yanıt gövdesi boş olmalı.');

        $row = $this->invitationRow($invitationId);
        self::assertNotNull($row, 'TEAM-INVITATIONS-CANCEL-01: satır silinmemeli, yalnız durumu değişmeli.');
        self::assertSame('cancelled', $row->status, 'TEAM-INVITATIONS-CANCEL-01: iptal edilen davetin status alanı "cancelled" olmalı.');

        $listResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/team/invitations");
        $listResponse->assertStatus(200);
        $listedIds = array_column((array) $listResponse->json(), 'id');
        self::assertNotContains($invitationId, $listedIds, 'TEAM-INVITATIONS-CANCEL-01: iptal edilen davet pending listesinde görünmemeli.');
    }

    // --- TEAM-INVITATIONS-CANCEL-OWNER-ONLY-01 ------------------------------

    public function test_non_owner_gets_enumeration_safe_404_and_leaves_pending_invitation_unchanged(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-cancel-owner-only-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-cancel-owner-only-01');

        $member = $this->verifiedUser('Mehmet Demir', 'mehmet-inv-cancel-owner-only-01@example.test');
        $this->addMember($workspaceId, $member, 'member');

        $editor = $this->verifiedUser('Elif Kaya', 'elif-inv-cancel-owner-only-01@example.test');
        $this->addMember($workspaceId, $editor, 'editor');

        $stranger = $this->verifiedUser('Can Öztürk', 'can-inv-cancel-owner-only-01@example.test');

        $invitationId = $this->insertInvitation($workspaceId, 'pending-inv-cancel-owner-only-01@example.test', 'editor', 'pending', $owner->id);

        $memberResponse = $this->actingAs($member)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->cancelUri($workspaceId, $invitationId));
        $memberResponse->assertStatus(404, 'TEAM-INVITATIONS-CANCEL-OWNER-ONLY-01: member davet iptal edemez — 404 dönmeli.');

        $editorResponse = $this->actingAs($editor)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->cancelUri($workspaceId, $invitationId));
        $editorResponse->assertStatus(404, 'TEAM-INVITATIONS-CANCEL-OWNER-ONLY-01: editor davet iptal edemez — 404 dönmeli, 403 değil.');

        $strangerResponse = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->cancelUri($workspaceId, $invitationId));
        $strangerResponse->assertStatus(404, 'TEAM-INVITATIONS-CANCEL-OWNER-ONLY-01: workspace\'e üye olmayan yabancı 404 almalı.');

        $row = $this->invitationRow($invitationId);
        self::assertSame('pending', $row->status, 'TEAM-INVITATIONS-CANCEL-OWNER-ONLY-01: reddedilen isteklerden sonra davet hâlâ pending olmalı.');
    }

    // --- TEAM-INVITATIONS-CANCEL-NOT-PENDING-01 -----------------------------

    public function test_already_non_pending_invitation_is_rejected_with_404_and_leaves_status_unchanged(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-cancel-not-pending-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-cancel-not-pending-01');

        $acceptedId = $this->insertInvitation($workspaceId, 'accepted-inv-cancel-not-pending-01@example.test', 'editor', 'accepted', $owner->id);
        $cancelledId = $this->insertInvitation($workspaceId, 'cancelled-inv-cancel-not-pending-01@example.test', 'editor', 'cancelled', $owner->id);

        $acceptedResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->cancelUri($workspaceId, $acceptedId));
        $acceptedResponse->assertStatus(404, 'TEAM-INVITATIONS-CANCEL-NOT-PENDING-01: accepted davet iptal edilemez — 404 dönmeli.');

        $cancelledResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->cancelUri($workspaceId, $cancelledId));
        $cancelledResponse->assertStatus(404, 'TEAM-INVITATIONS-CANCEL-NOT-PENDING-01: zaten cancelled davet tekrar iptal edilemez — 404 dönmeli.');

        self::assertSame('accepted', $this->invitationRow($acceptedId)->status);
        self::assertSame('cancelled', $this->invitationRow($cancelledId)->status);
    }

    // --- TEAM-INVITATIONS-CANCEL-CROSS-WORKSPACE-01 -------------------------

    public function test_invitation_id_belonging_to_a_different_workspace_is_rejected_with_404_and_unchanged(): void
    {
        $ownerOne = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-cancel-cross-01@example.test');
        $workspaceOneId = $this->workspaceOwnedBy($ownerOne, 'Zeytin Restoranları', 'zeytin-inv-cancel-cross-01');

        $ownerTwo = $this->verifiedUser('Elif Kaya', 'elif-inv-cancel-cross-01@example.test');
        $workspaceTwoId = $this->workspaceOwnedBy($ownerTwo, 'Nar Restoranları', 'nar-inv-cancel-cross-01');

        $invitationId = $this->insertInvitation($workspaceTwoId, 'pending-inv-cancel-cross-01@example.test', 'editor', 'pending', $ownerTwo->id);

        $response = $this->actingAs($ownerOne)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->cancelUri($workspaceOneId, $invitationId));
        $response->assertStatus(404, 'TEAM-INVITATIONS-CANCEL-CROSS-WORKSPACE-01: başka workspace\'e ait invitation id, sahibi olunan workspace altında 404 vermeli.');

        self::assertSame('pending', $this->invitationRow($invitationId)->status, 'TEAM-INVITATIONS-CANCEL-CROSS-WORKSPACE-01: yanlış workspace altındaki istek gerçek daveti etkilememeli.');
    }

    // --- TEAM-INVITATIONS-CANCEL-AUTH-01 ------------------------------------

    public function test_endpoint_requires_authenticated_and_verified_user(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-cancel-auth-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-cancel-auth-01');
        $unverified = User::factory()->unverified()->create();

        $invitationId = $this->insertInvitation($workspaceId, 'pending-inv-cancel-auth-01@example.test', 'editor', 'pending', $owner->id);

        $guestResponse = $this->withHeaders($this->jsonHeaders())
            ->deleteJson($this->cancelUri($workspaceId, $invitationId));
        self::assertContains(
            $guestResponse->getStatusCode(),
            [401, 403],
            'TEAM-INVITATIONS-CANCEL-AUTH-01: kimliksiz istek 401/403 ile reddedilmeli (404 kabul edilmez).'
        );

        $unverifiedResponse = $this->actingAs($unverified)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->cancelUri($workspaceId, $invitationId));
        $unverifiedResponse->assertStatus(403, 'TEAM-INVITATIONS-CANCEL-AUTH-01: doğrulanmamış (unverified) kullanıcı 403 ile reddedilmeli.');

        self::assertSame('pending', $this->invitationRow($invitationId)->status, 'TEAM-INVITATIONS-CANCEL-AUTH-01: reddedilen isteklerden sonra davet hâlâ pending olmalı.');
    }

    // --- TEAM-INVITATIONS-CANCEL-THROTTLE-01 --------------------------------

    public function test_sixth_cancel_request_within_one_minute_is_throttled_with_429(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-cancel-throttle-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-cancel-throttle-01');

        RateLimiter::clear(sha1((string) $owner->getAuthIdentifier()));

        $invitationIds = [];
        for ($i = 1; $i <= 6; $i++) {
            $invitationIds[] = $this->insertInvitation($workspaceId, sprintf('throttle-%d-inv-cancel-throttle-01@example.test', $i), 'editor', 'pending', $owner->id);
        }

        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
                ->deleteJson($this->cancelUri($workspaceId, $invitationIds[$i]));

            $response->assertStatus(204, sprintf('TEAM-INVITATIONS-CANCEL-THROTTLE-01: dakikadaki %d. istek tam olarak 204 dönmeli.', $i + 1));
        }

        $sixthResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->cancelUri($workspaceId, $invitationIds[5]));

        $sixthResponse->assertStatus(429, 'TEAM-INVITATIONS-CANCEL-THROTTLE-01: dakikada 6. istek throttle:5,1 ile 429 almalı.');
    }
}
