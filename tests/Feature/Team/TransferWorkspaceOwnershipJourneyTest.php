<?php

declare(strict_types=1);

namespace Tests\Feature\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Blind RED test candidate for POST
 * /api/workspaces/{workspaceId}/team/members/{memberId}/transfer-ownership
 * (S1-WP02E-OWNERSHIP-TRANSFER). No production code for this endpoint
 * exists yet: grep of routes/api.php confirms zero
 * "team/members/{member}/transfer-ownership" routes are registered, so
 * every request below is expected to fail RED with a 404 from the
 * framework's router (unmatched route), not from malformed fixtures or
 * guessed production response shapes.
 *
 * Frozen contract: access is a two-stage gate mirroring
 * RemoveTeamMemberController — no Permission::WorkspaceManage at all means
 * an enumeration-safe 404 before any business-state validation runs, while
 * holding that permission without being the workspace owner (a Manager)
 * means 403; the memberId path
 * segment is a workspace_memberships.id and must belong to the exact
 * requested workspace with role exactly "editor" — cross-workspace target,
 * a "member"-role target, the owner's own row as target, and a missing
 * target id are all rejected 404; on success the transfer is one atomic
 * transaction: the selected editor becomes owner, the prior owner becomes
 * editor, exactly one owner row remains in the workspace, and every
 * unrelated membership row is unchanged; auth+verified is mandatory; the
 * write is throttled to 5 requests per minute like other Team mutations
 * (throttle:5,1).
 *
 * FF-142 SÖZLEŞME DÜZELTMESİ. Bu sözleşme "non-owner → 404" diyordu ve
 * kardeş uç `RemoveTeamMemberController` da bir zamanlar öyle davranıyordu.
 * Kardeş uç açık bir sahiplik kapısı kazanıp Yönetici'ye 403 demeye
 * başlayınca burası geride kaldı: aynı kişi, aynı ekranda, iki komşu düğme
 * için iki farklı cevap alıyordu. Fark kasıtlı değil kazaydı — burada 404
 * bir karar değil, deponun `false` dönmesinin YAN ETKİSİYDİ.
 *
 * Requirement IDs: TEAM-OWNERSHIP-TRANSFER-01,
 * TEAM-OWNERSHIP-TRANSFER-OWNER-ONLY-01,
 * TEAM-OWNERSHIP-TRANSFER-OWNER-ONLY-02,
 * TEAM-OWNERSHIP-TRANSFER-INVALID-TARGET-01,
 * TEAM-OWNERSHIP-TRANSFER-CROSS-WORKSPACE-01,
 * TEAM-OWNERSHIP-TRANSFER-AUTH-01, TEAM-OWNERSHIP-TRANSFER-THROTTLE-01.
 */
final class TransferWorkspaceOwnershipJourneyTest extends TestCase
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

    private function transferUri(int $workspaceId, int $memberId): string
    {
        return "/api/workspaces/{$workspaceId}/team/members/{$memberId}/transfer-ownership";
    }

    // --- TEAM-OWNERSHIP-TRANSFER-01 ------------------------------------------

    public function test_owner_transfers_ownership_to_an_editor_atomically_leaving_exactly_one_owner(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-transfer-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-transfer-01');
        $ownerMembershipId = $this->ownerMembershipId($workspaceId, $owner->id);

        $editor = $this->verifiedUser('Elif Kaya', 'elif-transfer-01@example.test');
        $editorMembershipId = $this->addMember($workspaceId, $editor, 'editor');

        $bystander = $this->verifiedUser('Mehmet Demir', 'mehmet-transfer-01@example.test');
        $bystanderMembershipId = $this->addMember($workspaceId, $bystander, 'member');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->transferUri($workspaceId, $editorMembershipId));

        self::assertContains(
            $response->getStatusCode(),
            [200, 204],
            'TEAM-OWNERSHIP-TRANSFER-01: sahiplik devri başarılı olduğunda 200/204 dönmeli.'
        );

        $newOwnerRow = $this->membershipRow($editorMembershipId);
        self::assertNotNull($newOwnerRow, 'TEAM-OWNERSHIP-TRANSFER-01: hedef üyelik satırı hayatta kalmalı.');
        self::assertSame('owner', $newOwnerRow->role, 'TEAM-OWNERSHIP-TRANSFER-01: seçilen editor owner olmalı.');

        $priorOwnerRow = $this->membershipRow($ownerMembershipId);
        self::assertNotNull($priorOwnerRow, 'TEAM-OWNERSHIP-TRANSFER-01: eski sahibin satırı hayatta kalmalı.');
        self::assertSame('editor', $priorOwnerRow->role, 'TEAM-OWNERSHIP-TRANSFER-01: eski sahip editor olmalı.');

        $ownerCount = DB::table('workspace_memberships')
            ->where('workspace_id', $workspaceId)
            ->where('role', 'owner')
            ->count();
        self::assertSame(1, $ownerCount, 'TEAM-OWNERSHIP-TRANSFER-01: workspace\'te tam olarak bir owner kalmalı.');

        $bystanderRow = $this->membershipRow($bystanderMembershipId);
        self::assertSame('member', $bystanderRow->role, 'TEAM-OWNERSHIP-TRANSFER-01: ilişkisiz üyelik satırı değişmeden kalmalı.');
    }

    // --- TEAM-OWNERSHIP-TRANSFER-INVALID-TARGET-01 ---------------------------

    public function test_invalid_transfer_targets_are_rejected_and_roles_remain_unchanged(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-transfer-target-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-transfer-target-01');
        $ownerMembershipId = $this->ownerMembershipId($workspaceId, $owner->id);

        $member = $this->verifiedUser('Mehmet Demir', 'mehmet-transfer-target-01@example.test');
        $memberMembershipId = $this->addMember($workspaceId, $member, 'member');

        $memberTargetResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->transferUri($workspaceId, $memberMembershipId));
        self::assertContains(
            $memberTargetResponse->getStatusCode(),
            [404, 422],
            'TEAM-OWNERSHIP-TRANSFER-INVALID-TARGET-01: role="member" hedefe devir reddedilmeli.'
        );

        $selfTargetResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->transferUri($workspaceId, $ownerMembershipId));
        self::assertContains(
            $selfTargetResponse->getStatusCode(),
            [404, 422],
            'TEAM-OWNERSHIP-TRANSFER-INVALID-TARGET-01: owner kendine devredemez, reddedilmeli.'
        );

        $missingTargetResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->transferUri($workspaceId, 999999));
        self::assertContains(
            $missingTargetResponse->getStatusCode(),
            [404, 422],
            'TEAM-OWNERSHIP-TRANSFER-INVALID-TARGET-01: mevcut olmayan hedef reddedilmeli.'
        );

        $ownerRow = $this->membershipRow($ownerMembershipId);
        self::assertSame('owner', $ownerRow->role, 'TEAM-OWNERSHIP-TRANSFER-INVALID-TARGET-01: reddedilen isteklerden sonra owner rolü değişmemeli.');

        $memberRow = $this->membershipRow($memberMembershipId);
        self::assertSame('member', $memberRow->role, 'TEAM-OWNERSHIP-TRANSFER-INVALID-TARGET-01: reddedilen isteklerden sonra member rolü değişmemeli.');
    }

    // --- TEAM-OWNERSHIP-TRANSFER-OWNER-ONLY-01 -------------------------------

    public function test_non_owner_gets_enumeration_safe_404_and_roles_survive(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-transfer-owner-only-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-transfer-owner-only-01');
        $ownerMembershipId = $this->ownerMembershipId($workspaceId, $owner->id);

        $member = $this->verifiedUser('Mehmet Demir', 'mehmet-transfer-owner-only-01@example.test');
        $this->addMember($workspaceId, $member, 'member');

        $editor = $this->verifiedUser('Elif Kaya', 'elif-transfer-owner-only-01@example.test');
        $editorMembershipId = $this->addMember($workspaceId, $editor, 'editor');

        $stranger = $this->verifiedUser('Can Öztürk', 'can-transfer-owner-only-01@example.test');

        $memberResponse = $this->actingAs($member)->withHeaders($this->jsonHeaders())
            ->postJson($this->transferUri($workspaceId, $editorMembershipId));
        $memberResponse->assertStatus(404, 'TEAM-OWNERSHIP-TRANSFER-OWNER-ONLY-01: member sahiplik devredemez — 404 dönmeli.');

        $editorSelfResponse = $this->actingAs($editor)->withHeaders($this->jsonHeaders())
            ->postJson($this->transferUri($workspaceId, $editorMembershipId));
        $editorSelfResponse->assertStatus(404, 'TEAM-OWNERSHIP-TRANSFER-OWNER-ONLY-01: editor kendine devredemez — 404 dönmeli, 403 değil.');

        $strangerResponse = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->postJson($this->transferUri($workspaceId, $editorMembershipId));
        $strangerResponse->assertStatus(404, 'TEAM-OWNERSHIP-TRANSFER-OWNER-ONLY-01: workspace\'e üye olmayan yabancı 404 almalı.');

        $ownerRow = $this->membershipRow($ownerMembershipId);
        self::assertSame('owner', $ownerRow->role, 'TEAM-OWNERSHIP-TRANSFER-OWNER-ONLY-01: reddedilen isteklerden sonra owner rolü değişmemeli.');

        $editorRow = $this->membershipRow($editorMembershipId);
        self::assertSame('editor', $editorRow->role, 'TEAM-OWNERSHIP-TRANSFER-OWNER-ONLY-01: reddedilen isteklerden sonra editor rolü değişmemeli.');
    }

    // --- TEAM-OWNERSHIP-TRANSFER-OWNER-ONLY-02 -------------------------------

    /**
     * YÖNETİCİ SAHİPLİĞİ DEVREDEMEZ — ve bunu 403 ile öğrenir, 404 ile değil.
     *
     * `Permission::WorkspaceManage` Yönetici'de DE var ve olmalı: şubeyi ve
     * karekodu o yürütüyor. Ama sahipliği kime vereceğine karar vermek
     * günlük operasyon değildir; kardeş uç `RemoveTeamMemberController` aynı
     * sınırı aynı cümleyle söylüyor (TEAM-MEMBERS-REMOVE-OWNER-ONLY-02).
     *
     * Neden 404 DEĞİL: buraya kadar gelen kişi çalışma alanını zaten
     * YÖNETİYOR — varlığını gizlemenin anlamı yok ve çıkış yolu farklıdır
     * (sahipten istemek). Numaralandırmaya kapalı 404, izne HİÇ sahip
     * olmayanlar için (TEAM-OWNERSHIP-TRANSFER-OWNER-ONLY-01) aynen durur.
     *
     * Bir de somut zarar: bu ayrım olmadan yönetici, kendisini işe alan
     * sahibin haberi olmadan sahipliği başkasına — ya da sırayla kendine —
     * kaydırmayı deneyebileceğini sanır ve sunucunun "yok" cevabını üyeliğin
     * silinmiş olmasıyla karıştırır.
     */
    public function test_a_manager_cannot_transfer_ownership(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-transfer-owner-only-02@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-transfer-owner-only-02');
        $ownerMembershipId = $this->ownerMembershipId($workspaceId, $owner->id);

        $manager = $this->verifiedUser('Mehmet Demir', 'mehmet-transfer-owner-only-02@example.test');
        $managerMembershipId = $this->addMember($workspaceId, $manager, 'manager');

        $editor = $this->verifiedUser('Elif Kaya', 'elif-transfer-owner-only-02@example.test');
        $editorMembershipId = $this->addMember($workspaceId, $editor, 'editor');

        $otherResponse = $this->actingAs($manager)->withHeaders($this->jsonHeaders())
            ->postJson($this->transferUri($workspaceId, $editorMembershipId));
        $otherResponse->assertStatus(403, 'TEAM-OWNERSHIP-TRANSFER-OWNER-ONLY-02: manager sahipliği bir editöre devredemez — 403 dönmeli.');

        $selfResponse = $this->actingAs($manager)->withHeaders($this->jsonHeaders())
            ->postJson($this->transferUri($workspaceId, $managerMembershipId));
        $selfResponse->assertStatus(403, 'TEAM-OWNERSHIP-TRANSFER-OWNER-ONLY-02: manager sahipliği kendine de alamaz — 403 dönmeli.');

        self::assertSame('owner', $this->membershipRow($ownerMembershipId)->role, 'TEAM-OWNERSHIP-TRANSFER-OWNER-ONLY-02: reddedilen isteklerden sonra owner rolü değişmemeli.');
        self::assertSame('manager', $this->membershipRow($managerMembershipId)->role, 'TEAM-OWNERSHIP-TRANSFER-OWNER-ONLY-02: manager rolü değişmemeli.');
        self::assertSame('editor', $this->membershipRow($editorMembershipId)->role, 'TEAM-OWNERSHIP-TRANSFER-OWNER-ONLY-02: editor rolü değişmemeli.');
    }

    // --- TEAM-OWNERSHIP-TRANSFER-CROSS-WORKSPACE-01 --------------------------

    public function test_editor_membership_id_belonging_to_a_different_workspace_is_rejected_with_404_and_survives(): void
    {
        $ownerOne = $this->verifiedUser('Ayşe Yılmaz', 'ayse-transfer-cross-01@example.test');
        $workspaceOneId = $this->workspaceOwnedBy($ownerOne, 'Zeytin Restoranları', 'zeytin-transfer-cross-01');
        $ownerOneMembershipId = $this->ownerMembershipId($workspaceOneId, $ownerOne->id);

        $ownerTwo = $this->verifiedUser('Elif Kaya', 'elif-transfer-cross-01@example.test');
        $workspaceTwoId = $this->workspaceOwnedBy($ownerTwo, 'Nar Restoranları', 'nar-transfer-cross-01');

        $editorInWorkspaceTwo = $this->verifiedUser('Can Öztürk', 'can-transfer-cross-01@example.test');
        $editorMembershipId = $this->addMember($workspaceTwoId, $editorInWorkspaceTwo, 'editor');

        $response = $this->actingAs($ownerOne)->withHeaders($this->jsonHeaders())
            ->postJson($this->transferUri($workspaceOneId, $editorMembershipId));
        $response->assertStatus(404, 'TEAM-OWNERSHIP-TRANSFER-CROSS-WORKSPACE-01: başka workspace\'e ait membership id, sahibi olunan workspace altında 404 vermeli.');

        $editorRow = $this->membershipRow($editorMembershipId);
        self::assertSame('editor', $editorRow->role, 'TEAM-OWNERSHIP-TRANSFER-CROSS-WORKSPACE-01: yanlış workspace altındaki istek gerçek üyeliği etkilememeli.');

        $ownerOneRow = $this->membershipRow($ownerOneMembershipId);
        self::assertSame('owner', $ownerOneRow->role, 'TEAM-OWNERSHIP-TRANSFER-CROSS-WORKSPACE-01: reddedilen istekten sonra owner rolü değişmemeli.');
    }

    // --- TEAM-OWNERSHIP-TRANSFER-AUTH-01 ---------------------------------------

    public function test_endpoint_requires_authenticated_and_verified_user(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-transfer-auth-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-transfer-auth-01');
        $ownerMembershipId = $this->ownerMembershipId($workspaceId, $owner->id);
        $unverified = User::factory()->unverified()->create();

        $editor = $this->verifiedUser('Elif Kaya', 'elif-transfer-auth-01@example.test');
        $editorMembershipId = $this->addMember($workspaceId, $editor, 'editor');

        $guestResponse = $this->withHeaders($this->jsonHeaders())
            ->postJson($this->transferUri($workspaceId, $editorMembershipId));
        self::assertContains(
            $guestResponse->getStatusCode(),
            [401, 403],
            'TEAM-OWNERSHIP-TRANSFER-AUTH-01: kimliksiz istek 401/403 ile reddedilmeli (404 kabul edilmez).'
        );

        $unverifiedResponse = $this->actingAs($unverified)->withHeaders($this->jsonHeaders())
            ->postJson($this->transferUri($workspaceId, $editorMembershipId));
        $unverifiedResponse->assertStatus(403, 'TEAM-OWNERSHIP-TRANSFER-AUTH-01: doğrulanmamış (unverified) kullanıcı 403 ile reddedilmeli.');

        $editorRow = $this->membershipRow($editorMembershipId);
        self::assertSame('editor', $editorRow->role, 'TEAM-OWNERSHIP-TRANSFER-AUTH-01: reddedilen isteklerden sonra editor rolü değişmemeli.');

        $ownerRow = $this->membershipRow($ownerMembershipId);
        self::assertSame('owner', $ownerRow->role, 'TEAM-OWNERSHIP-TRANSFER-AUTH-01: reddedilen isteklerden sonra owner rolü değişmemeli.');
    }

    // --- TEAM-OWNERSHIP-TRANSFER-THROTTLE-01 ------------------------------------

    public function test_sixth_transfer_request_within_one_minute_is_throttled_with_429(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-transfer-throttle-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-transfer-throttle-01');
        $ownerMembershipId = $this->ownerMembershipId($workspaceId, $owner->id);

        RateLimiter::clear(sha1((string) $owner->getAuthIdentifier()));

        // Self-transfer is an invalid target (owner cannot transfer to their own
        // row), so it is stably rejected on every attempt without ever rotating
        // ownership — the throttle counter only needs repeated hits on the
        // route, not repeated successful transfers.
        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
                ->postJson($this->transferUri($workspaceId, $ownerMembershipId));

            self::assertNotSame(
                429,
                $response->getStatusCode(),
                sprintf('TEAM-OWNERSHIP-TRANSFER-THROTTLE-01: dakikadaki %d. istek henüz throttle edilmemeli.', $i + 1)
            );
        }

        $sixthResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->transferUri($workspaceId, $ownerMembershipId));

        $sixthResponse->assertStatus(429, 'TEAM-OWNERSHIP-TRANSFER-THROTTLE-01: dakikada 6. istek throttle:5,1 ile 429 almalı.');
    }
}
