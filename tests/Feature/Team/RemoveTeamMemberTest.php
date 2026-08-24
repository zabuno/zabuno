<?php

declare(strict_types=1);

namespace Tests\Feature\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Blind RED test candidate for DELETE
 * /api/workspaces/{workspaceId}/team/members/{memberId} (remove an editor
 * membership row, S1-WP01A Team members write slice). No production code
 * for this endpoint exists yet: grep of routes/api.php confirms zero
 * "team/members/{member}" DELETE routes are registered, so every request
 * below is expected to fail RED with a 404 from the framework's router
 * (unmatched route), not from malformed fixtures or guessed production
 * response shapes.
 *
 * Frozen contract: access requires Permission::WorkspaceManage (owner-only,
 * enumeration-safe 404 for non-owner before any business-state validation
 * runs); the memberId path segment is a workspace_memberships.id, and only
 * a row whose role is exactly "editor" *and* whose workspace_id matches the
 * exact requested workspace can be removed; success is 204 with no body
 * and the row is deleted; the owner row and any "member" row can never be
 * removed through this endpoint (404, row survives) even when the
 * requester is the same owner acting on their own row; an editor row that
 * belongs to a different workspace is rejected with 404 and the row
 * survives untouched; auth+verified is mandatory; the write is throttled
 * to 5 requests per minute like other Team mutations (throttle:5,1).
 *
 * Requirement IDs: TEAM-MEMBERS-REMOVE-01,
 * TEAM-MEMBERS-REMOVE-OWNER-ONLY-01,
 * TEAM-MEMBERS-REMOVE-NON-EDITOR-ROLE-01,
 * TEAM-MEMBERS-REMOVE-CROSS-WORKSPACE-01,
 * TEAM-MEMBERS-REMOVE-AUTH-01, TEAM-MEMBERS-REMOVE-THROTTLE-01.
 */
final class RemoveTeamMemberTest extends TestCase
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

    private function addMember(int $workspaceId, User $user, string $role = 'member'): int
    {
        return (int) DB::table('workspace_memberships')->insertGetId([
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ownerMembershipId(int $workspaceId, int $ownerUserId): int
    {
        return (int) DB::table('workspace_memberships')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $ownerUserId)
            ->value('id');
    }

    private function membershipRow(int $membershipId): ?object
    {
        return DB::table('workspace_memberships')->where('id', $membershipId)->first();
    }

    private function removeUri(int $workspaceId, int $memberId): string
    {
        return "/api/workspaces/{$workspaceId}/team/members/{$memberId}";
    }

    // --- TEAM-MEMBERS-REMOVE-01 ----------------------------------------------

    public function test_owner_removes_an_editor_membership_in_that_exact_workspace_with_204_and_row_is_deleted(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mem-remove-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mem-remove-01');

        $editor = $this->verifiedUser('Elif Kaya', 'elif-mem-remove-01@example.test');
        $editorMembershipId = $this->addMember($workspaceId, $editor, 'editor');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->removeUri($workspaceId, $editorMembershipId));

        $response->assertStatus(204, 'TEAM-MEMBERS-REMOVE-01: sahibi editor üyeliğini kaldırdığında 204 dönmeli.');
        self::assertSame('', $response->getContent(), 'TEAM-MEMBERS-REMOVE-01: 204 yanıt gövdesi boş olmalı.');

        self::assertNull($this->membershipRow($editorMembershipId), 'TEAM-MEMBERS-REMOVE-01: kaldırılan üyelik satırı silinmeli.');
    }

    // --- TEAM-MEMBERS-REMOVE-NON-EDITOR-ROLE-01 ------------------------------

    public function test_owner_and_member_rows_cannot_be_removed_through_this_endpoint(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mem-remove-role-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mem-remove-role-01');

        $ownerMembershipId = $this->ownerMembershipId($workspaceId, $owner->id);

        $member = $this->verifiedUser('Mehmet Demir', 'mehmet-mem-remove-role-01@example.test');
        $memberMembershipId = $this->addMember($workspaceId, $member, 'member');

        $ownerSelfResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->removeUri($workspaceId, $ownerMembershipId));
        $ownerSelfResponse->assertStatus(404, 'TEAM-MEMBERS-REMOVE-NON-EDITOR-ROLE-01: owner satırı bu uç noktayla asla kaldırılamaz — 404 dönmeli.');

        $memberResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->removeUri($workspaceId, $memberMembershipId));
        $memberResponse->assertStatus(404, 'TEAM-MEMBERS-REMOVE-NON-EDITOR-ROLE-01: role="member" satırı bu uç noktayla kaldırılamaz — 404 dönmeli.');

        self::assertNotNull($this->membershipRow($ownerMembershipId), 'TEAM-MEMBERS-REMOVE-NON-EDITOR-ROLE-01: owner satırı hayatta kalmalı.');
        self::assertNotNull($this->membershipRow($memberMembershipId), 'TEAM-MEMBERS-REMOVE-NON-EDITOR-ROLE-01: member satırı hayatta kalmalı.');
    }

    // --- TEAM-MEMBERS-REMOVE-OWNER-ONLY-01 -----------------------------------

    public function test_non_owner_gets_enumeration_safe_404_and_editor_row_survives(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mem-remove-owner-only-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mem-remove-owner-only-01');

        $member = $this->verifiedUser('Mehmet Demir', 'mehmet-mem-remove-owner-only-01@example.test');
        $this->addMember($workspaceId, $member, 'member');

        $editor = $this->verifiedUser('Elif Kaya', 'elif-mem-remove-owner-only-01@example.test');
        $editorMembershipId = $this->addMember($workspaceId, $editor, 'editor');

        $stranger = $this->verifiedUser('Can Öztürk', 'can-mem-remove-owner-only-01@example.test');

        $memberResponse = $this->actingAs($member)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->removeUri($workspaceId, $editorMembershipId));
        $memberResponse->assertStatus(404, 'TEAM-MEMBERS-REMOVE-OWNER-ONLY-01: member başka bir üyeyi kaldıramaz — 404 dönmeli.');

        $editorSelfResponse = $this->actingAs($editor)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->removeUri($workspaceId, $editorMembershipId));
        $editorSelfResponse->assertStatus(404, 'TEAM-MEMBERS-REMOVE-OWNER-ONLY-01: editor kendini bile kaldıramaz — 404 dönmeli, 403 değil.');

        $strangerResponse = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->removeUri($workspaceId, $editorMembershipId));
        $strangerResponse->assertStatus(404, 'TEAM-MEMBERS-REMOVE-OWNER-ONLY-01: workspace\'e üye olmayan yabancı 404 almalı.');

        self::assertNotNull($this->membershipRow($editorMembershipId), 'TEAM-MEMBERS-REMOVE-OWNER-ONLY-01: reddedilen isteklerden sonra editor satırı hayatta kalmalı.');
    }

    // --- TEAM-MEMBERS-REMOVE-CROSS-WORKSPACE-01 ------------------------------

    public function test_editor_membership_id_belonging_to_a_different_workspace_is_rejected_with_404_and_survives(): void
    {
        $ownerOne = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mem-remove-cross-01@example.test');
        $workspaceOneId = $this->workspaceOwnedBy($ownerOne, 'Zeytin Restoranları', 'zeytin-mem-remove-cross-01');

        $ownerTwo = $this->verifiedUser('Elif Kaya', 'elif-mem-remove-cross-01@example.test');
        $workspaceTwoId = $this->workspaceOwnedBy($ownerTwo, 'Nar Restoranları', 'nar-mem-remove-cross-01');

        $editorInWorkspaceTwo = $this->verifiedUser('Can Öztürk', 'can-mem-remove-cross-01@example.test');
        $editorMembershipId = $this->addMember($workspaceTwoId, $editorInWorkspaceTwo, 'editor');

        $response = $this->actingAs($ownerOne)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->removeUri($workspaceOneId, $editorMembershipId));
        $response->assertStatus(404, 'TEAM-MEMBERS-REMOVE-CROSS-WORKSPACE-01: başka workspace\'e ait membership id, sahibi olunan workspace altında 404 vermeli.');

        self::assertNotNull($this->membershipRow($editorMembershipId), 'TEAM-MEMBERS-REMOVE-CROSS-WORKSPACE-01: yanlış workspace altındaki istek gerçek üyeliği etkilememeli.');
    }

    // --- TEAM-MEMBERS-REMOVE-AUTH-01 ------------------------------------------

    public function test_endpoint_requires_authenticated_and_verified_user(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mem-remove-auth-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mem-remove-auth-01');
        $unverified = User::factory()->unverified()->create();

        $editor = $this->verifiedUser('Elif Kaya', 'elif-mem-remove-auth-01@example.test');
        $editorMembershipId = $this->addMember($workspaceId, $editor, 'editor');

        $guestResponse = $this->withHeaders($this->jsonHeaders())
            ->deleteJson($this->removeUri($workspaceId, $editorMembershipId));
        self::assertContains(
            $guestResponse->getStatusCode(),
            [401, 403],
            'TEAM-MEMBERS-REMOVE-AUTH-01: kimliksiz istek 401/403 ile reddedilmeli (404 kabul edilmez).'
        );

        $unverifiedResponse = $this->actingAs($unverified)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->removeUri($workspaceId, $editorMembershipId));
        $unverifiedResponse->assertStatus(403, 'TEAM-MEMBERS-REMOVE-AUTH-01: doğrulanmamış (unverified) kullanıcı 403 ile reddedilmeli.');

        self::assertNotNull($this->membershipRow($editorMembershipId), 'TEAM-MEMBERS-REMOVE-AUTH-01: reddedilen isteklerden sonra editor satırı hayatta kalmalı.');
    }

    // --- TEAM-MEMBERS-REMOVE-THROTTLE-01 --------------------------------------

    public function test_sixth_remove_request_within_one_minute_is_throttled_with_429(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mem-remove-throttle-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mem-remove-throttle-01');

        RateLimiter::clear(sha1((string) $owner->getAuthIdentifier()));

        $editorMembershipIds = [];
        for ($i = 1; $i <= 6; $i++) {
            $editor = $this->verifiedUser(sprintf('Editor %d', $i), sprintf('editor-%d-mem-remove-throttle-01@example.test', $i));
            $editorMembershipIds[] = $this->addMember($workspaceId, $editor, 'editor');
        }

        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
                ->deleteJson($this->removeUri($workspaceId, $editorMembershipIds[$i]));

            $response->assertStatus(204, sprintf('TEAM-MEMBERS-REMOVE-THROTTLE-01: dakikadaki %d. istek tam olarak 204 dönmeli.', $i + 1));
        }

        $sixthResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->removeUri($workspaceId, $editorMembershipIds[5]));

        $sixthResponse->assertStatus(429, 'TEAM-MEMBERS-REMOVE-THROTTLE-01: dakikada 6. istek throttle:5,1 ile 429 almalı.');
    }
}
