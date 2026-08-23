<?php

declare(strict_types=1);

namespace Tests\Feature\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Blind RED test candidate for GET /api/workspaces/{workspace}/team/members
 * (read-only real Team member list, S1-WP01A smallest server-authoritative
 * Team slice). No production code for this endpoint exists yet: grep of
 * routes/api.php confirms zero "team" routes are registered, so every
 * request below is expected to fail RED with a 404 from the framework's
 * router (unmatched route), not from malformed fixtures or guessed
 * production response shapes.
 *
 * Frozen contract: access requires Permission::WorkspaceManage (owner-only,
 * per docs/02 — Editor cannot access Team); ordinary member/editor and
 * strangers get an enumeration-safe 404 identical to a nonexistent
 * workspace; auth+verified is mandatory; response is a JSON array ordered
 * deterministically by membership id, each item containing only
 * id/name/email/role — never a raw User or membership model (no password,
 * remember_token, timestamps, or workspace id).
 *
 * Requirement IDs: TEAM-MEMBERS-LIST-01, TEAM-MEMBERS-LIST-EMPTY-01,
 * TEAM-MEMBERS-OWNER-ONLY-01, TEAM-MEMBERS-TENANT-ESCAPE-01,
 * TEAM-MEMBERS-AUTH-01, TEAM-MEMBERS-SHAPE-01.
 */
final class ListTeamMembersTest extends TestCase
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

    private function membersUri(int $workspaceId): string
    {
        return "/api/workspaces/{$workspaceId}/team/members";
    }

    // --- TEAM-MEMBERS-LIST-01 -----------------------------------------------

    public function test_owner_lists_workspace_members_ordered_by_membership_id_with_role_and_only_the_frozen_fields(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-team-list-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-team-list-01');

        $memberOne = $this->verifiedUser('Mehmet Demir', 'mehmet-team-list-01@example.test');
        $this->addMember($workspaceId, $memberOne, 'member');

        $memberTwo = $this->verifiedUser('Elif Kaya', 'elif-team-list-01@example.test');
        $this->addMember($workspaceId, $memberTwo, 'editor');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->membersUri($workspaceId));

        $response->assertStatus(200, 'TEAM-MEMBERS-LIST-01: sahibi kendi workspace üye listesini 200 ile almalı.');
        $body = $response->json();

        self::assertIsArray($body, 'TEAM-MEMBERS-LIST-01: yanıt gövdesi bir dizi olmalı.');
        self::assertCount(3, $body, 'TEAM-MEMBERS-LIST-01: owner + 2 üye = 3 kayıt dönmeli.');

        self::assertSame('Ayşe Yılmaz', $body[0]['name'] ?? null, 'TEAM-MEMBERS-LIST-01: sıralama membership id sırasına göre — owner ilk eklenen üyeliktir.');
        self::assertSame('owner', $body[0]['role'] ?? null);
        self::assertSame('Mehmet Demir', $body[1]['name'] ?? null, 'TEAM-MEMBERS-LIST-01: ikinci eklenen üyelik ikinci sırada olmalı.');
        self::assertSame('member', $body[1]['role'] ?? null);
        self::assertSame('Elif Kaya', $body[2]['name'] ?? null, 'TEAM-MEMBERS-LIST-01: üçüncü eklenen üyelik üçüncü sırada olmalı.');
        self::assertSame('editor', $body[2]['role'] ?? null);

        foreach ($body as $item) {
            self::assertArrayHasKey('id', $item);
            self::assertArrayHasKey('name', $item);
            self::assertArrayHasKey('email', $item);
            self::assertArrayHasKey('role', $item);
        }
    }

    // --- TEAM-MEMBERS-SHAPE-01 -----------------------------------------------

    public function test_response_never_leaks_password_remember_token_timestamps_or_workspace_id(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-team-shape-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-team-shape-01');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->membersUri($workspaceId));

        $response->assertStatus(200);
        $body = $response->json();

        self::assertIsArray($body);
        self::assertNotEmpty($body);

        foreach ($body as $item) {
            self::assertIsArray($item);
            self::assertSame(
                ['id', 'name', 'email', 'role'],
                array_keys($item),
                'TEAM-MEMBERS-SHAPE-01: her kayıt yalnız id/name/email/role alanlarını taşımalı — model doğrudan serialize edilmemeli.'
            );
            self::assertArrayNotHasKey('password', $item);
            self::assertArrayNotHasKey('remember_token', $item);
            self::assertArrayNotHasKey('created_at', $item);
            self::assertArrayNotHasKey('updated_at', $item);
            self::assertArrayNotHasKey('workspace_id', $item);
            self::assertArrayNotHasKey('email_verified_at', $item);
        }
    }

    // --- TEAM-MEMBERS-LIST-EMPTY-01 -------------------------------------------

    public function test_owner_alone_receives_a_single_element_array_containing_only_themselves(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-team-list-empty-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-team-list-empty-01');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->membersUri($workspaceId));

        $response->assertStatus(200, 'TEAM-MEMBERS-LIST-EMPTY-01: tek üyeli (sadece owner) workspace için 200 dönmeli.');
        $body = $response->json();

        self::assertCount(1, $body, 'TEAM-MEMBERS-LIST-EMPTY-01: sahte veri yok — yalnız owner kaydı dönmeli.');
        self::assertSame('owner', $body[0]['role'] ?? null);
    }

    // --- TEAM-MEMBERS-OWNER-ONLY-01 -------------------------------------------

    public function test_member_and_editor_are_denied_with_enumeration_safe_404_not_403(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-team-owner-only-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-team-owner-only-01');

        $member = $this->verifiedUser('Mehmet Demir', 'mehmet-team-owner-only-01@example.test');
        $this->addMember($workspaceId, $member, 'member');

        $editor = $this->verifiedUser('Elif Kaya', 'elif-team-owner-only-01@example.test');
        $this->addMember($workspaceId, $editor, 'editor');

        $memberResponse = $this->actingAs($member)->withHeaders($this->jsonHeaders())
            ->getJson($this->membersUri($workspaceId));
        $memberResponse->assertStatus(404, 'TEAM-MEMBERS-OWNER-ONLY-01: member workspace.manage taşımaz — Team listesi 404 ile reddedilmeli.');

        $editorResponse = $this->actingAs($editor)->withHeaders($this->jsonHeaders())
            ->getJson($this->membersUri($workspaceId));
        $editorResponse->assertStatus(404, 'TEAM-MEMBERS-OWNER-ONLY-01: editor Team\'e erişemez (docs/02) — 404 ile reddedilmeli, 403 değil.');
    }

    // --- TEAM-MEMBERS-TENANT-ESCAPE-01 -----------------------------------------

    public function test_foreign_or_no_membership_user_gets_enumeration_safe_404_matching_nonexistent_workspace(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-team-escape-01@example.test');
        $stranger = $this->verifiedUser('Can Öztürk', 'can-team-escape-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-team-escape-01');

        $strangerResponse = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->getJson($this->membersUri($workspaceId));
        $strangerResponse->assertStatus(404, 'TEAM-MEMBERS-TENANT-ESCAPE-01: üye olunmayan workspace Team listesi okunamaz (404).');

        $nonexistentResponse = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->getJson($this->membersUri(999999999));

        self::assertSame(
            $strangerResponse->getStatusCode(),
            $nonexistentResponse->getStatusCode(),
            'TEAM-MEMBERS-TENANT-ESCAPE-01: yabancı ve var-olmayan workspace aynı imzayı taşımalı (enumeration-safe).'
        );
    }

    // --- TEAM-MEMBERS-AUTH-01 ---------------------------------------------------

    public function test_endpoint_requires_authenticated_and_verified_user(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-team-auth-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-team-auth-01');
        $unverified = User::factory()->unverified()->create();

        $guestResponse = $this->withHeaders($this->jsonHeaders())->getJson($this->membersUri($workspaceId));
        self::assertContains(
            $guestResponse->getStatusCode(),
            [401, 403],
            'TEAM-MEMBERS-AUTH-01: kimliksiz istek 401/403 ile reddedilmeli (404 kabul edilmez).'
        );

        $unverifiedResponse = $this->actingAs($unverified)->withHeaders($this->jsonHeaders())
            ->getJson($this->membersUri($workspaceId));
        $unverifiedResponse->assertStatus(403, 'TEAM-MEMBERS-AUTH-01: doğrulanmamış (unverified) kullanıcı 403 ile reddedilmeli.');
    }
}
