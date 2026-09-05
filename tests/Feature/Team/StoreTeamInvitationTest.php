<?php

declare(strict_types=1);

namespace Tests\Feature\Team;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * Blind RED test candidate for POST /api/workspaces/{workspace}/team/invitations
 * (create a pending Team invitation, S1-WP01A smallest server-authoritative Team
 * invitations write slice). No production code for this endpoint exists yet:
 * grep of routes/api.php confirms zero "team/invitations" routes are
 * registered, so every request below is expected to fail RED with a 404 from
 * the framework's router (unmatched route), not from malformed fixtures or
 * guessed production response shapes.
 *
 * Frozen contract: access requires Permission::WorkspaceManage (owner-only);
 * non-owner gets an enumeration-safe 404 *before* any business-state
 * validation runs (duplicate/member checks never leak through a 422 to a
 * non-owner); email is required, validated, and normalized to lowercase;
 * role must be exactly "editor" (any other role, including "owner"/"member",
 * is rejected); an email already belonging to a workspace member is
 * rejected; a duplicate pending invitation for the same email
 * (case-insensitive) is rejected and does not create an extra row; a 201
 * response carries only id/email/role/status and the row is persisted with
 * invited_by set to the acting owner; auth+verified is mandatory; the write
 * is throttled to 5 requests per minute (429 on the 6th).
 *
 * Requirement IDs: TEAM-INVITATIONS-STORE-01, TEAM-INVITATIONS-STORE-EMAIL-01,
 * TEAM-INVITATIONS-STORE-ROLE-01, TEAM-INVITATIONS-STORE-ALREADY-MEMBER-01,
 * TEAM-INVITATIONS-STORE-DUPLICATE-01, TEAM-INVITATIONS-STORE-OWNER-ONLY-01,
 * TEAM-INVITATIONS-STORE-AUTH-01, TEAM-INVITATIONS-STORE-THROTTLE-01.
 */
final class StoreTeamInvitationTest extends TestCase
{
    use GrantsPlanEntitlements;
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

        // Owner 2026-08-26'da bu yeteneği plana bağladı; testler
        // yazıldığında ücretsizdi. Kurulum burada, kurucunun içinde yapılır ki
        // her test kendi plan kurgusunu tekrar yazmasın.
        $this->grantEntitlements($workspaceId);

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

    private function countInvitationRows(): int
    {
        $this->ensureTeamInvitationsSchemaForFixture();

        return (int) DB::table('team_invitations')->count();
    }

    private function invitationsUri(int $workspaceId): string
    {
        return "/api/workspaces/{$workspaceId}/team/invitations";
    }

    // --- TEAM-INVITATIONS-STORE-01 ------------------------------------------

    public function test_owner_creates_pending_editor_invitation_with_normalized_lowercase_email_and_persists_invited_by(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-store-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-store-01');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), [
                'email' => 'Mehmet-INV-Store-01@Example.TEST',
                'role' => 'editor',
            ]);

        $response->assertStatus(201, 'TEAM-INVITATIONS-STORE-01: sahibi geçerli davet oluşturduğunda 201 dönmeli.');
        $body = $response->json();

        self::assertIsArray($body);
        /*
            `delivery` EKLENDİ (FF-160, `docs/110` P0-06).

            İddianın aslı "ham sütun sızmasın"dı — `invited_by`, zaman
            damgaları, `token_hash`. `delivery` bunların hiçbiri değil: iki
            sütundan türetilen tek kelimelik hâl. Alan eklendi çünkü onsuz,
            e-postası hiç çıkmamış bir davet ekranda başarılı bir davetle
            aynı görünüyordu.
        */
        self::assertSame(
            ['id', 'email', 'role', 'status', 'delivery'],
            array_keys($body),
            'TEAM-INVITATIONS-STORE-01: yanıt yalnız bu alanları taşımalı — ham sütun değil.'
        );
        self::assertSame('mehmet-inv-store-01@example.test', $body['email'] ?? null, 'TEAM-INVITATIONS-STORE-01: email küçük harfe normalize edilmeli.');
        self::assertSame('editor', $body['role'] ?? null);
        self::assertSame('pending', $body['status'] ?? null);

        $row = DB::table('team_invitations')->where('id', $body['id'])->first();
        self::assertNotNull($row, 'TEAM-INVITATIONS-STORE-01: davet kalıcı olarak yazılmalı.');
        self::assertSame('mehmet-inv-store-01@example.test', $row->email);
        self::assertSame('editor', $row->role);
        self::assertSame('pending', $row->status);
        self::assertSame($owner->id, (int) $row->invited_by, 'TEAM-INVITATIONS-STORE-01: invited_by davet gönderen sahibi olmalı.');
        self::assertSame($workspaceId, (int) $row->workspace_id);
    }

    // --- TEAM-INVITATIONS-STORE-EMAIL-01 ------------------------------------

    public function test_missing_or_invalid_email_is_rejected_with_422(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-store-email-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-store-email-01');

        $missingResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), ['role' => 'editor']);
        $missingResponse->assertStatus(422, 'TEAM-INVITATIONS-STORE-EMAIL-01: email eksikse 422 dönmeli.');

        $invalidResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), ['email' => 'not-an-email', 'role' => 'editor']);
        $invalidResponse->assertStatus(422, 'TEAM-INVITATIONS-STORE-EMAIL-01: geçersiz email formatı 422 dönmeli.');

        // 255 karakter sınırını aşan, biçimce geçerli görünen bir email de
        // reddedilmeli — format geçerliliği tek başına yeterli değildir.
        $overlongLocalPart = str_repeat('a', 250);
        $overlongEmail = $overlongLocalPart.'@example.test';
        self::assertGreaterThan(255, strlen($overlongEmail), 'TEAM-INVITATIONS-STORE-EMAIL-01: fixture email 255 karakteri aşmalı.');

        $overlongResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), ['email' => $overlongEmail, 'role' => 'editor']);
        $overlongResponse->assertStatus(422, 'TEAM-INVITATIONS-STORE-EMAIL-01: 255 karakteri aşan email 422 dönmeli.');
    }

    // --- TEAM-INVITATIONS-STORE-ROLE-01 -------------------------------------

    public function test_any_role_other_than_editor_is_rejected_with_422(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-store-role-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-store-role-01');

        foreach (['owner', 'member', 'admin', ''] as $role) {
            $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
                ->postJson($this->invitationsUri($workspaceId), [
                    'email' => sprintf('role-%s-inv-store-role-01@example.test', $role !== '' ? $role : 'blank'),
                    'role' => $role,
                ]);
            $response->assertStatus(422, sprintf('TEAM-INVITATIONS-STORE-ROLE-01: role="%s" reddedilmeli, yalnız "editor" kabul edilir.', $role));
        }

        $missingRoleResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), ['email' => 'role-missing-inv-store-role-01@example.test']);
        $missingRoleResponse->assertStatus(422, 'TEAM-INVITATIONS-STORE-ROLE-01: role eksikse 422 dönmeli.');
    }

    // --- TEAM-INVITATIONS-STORE-ALREADY-MEMBER-01 ---------------------------

    public function test_email_already_belonging_to_a_workspace_member_is_rejected_with_422_and_creates_no_row(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-store-member-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-store-member-01');

        $existingMember = $this->verifiedUser('Mehmet Demir', 'mehmet-inv-store-member-01@example.test');
        $this->addMember($workspaceId, $existingMember, 'member');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), [
                'email' => 'Mehmet-INV-Store-Member-01@Example.TEST',
                'role' => 'editor',
            ]);

        $response->assertStatus(422, 'TEAM-INVITATIONS-STORE-ALREADY-MEMBER-01: zaten üye olan email için davet oluşturulamaz.');

        $countBefore = $this->countInvitationRows();
        self::assertSame(0, $countBefore, 'TEAM-INVITATIONS-STORE-ALREADY-MEMBER-01: reddedilen istek ekstra satır oluşturmamalı.');
    }

    // --- TEAM-INVITATIONS-STORE-DUPLICATE-01 --------------------------------

    public function test_duplicate_pending_invitation_case_insensitive_is_rejected_with_422_and_creates_no_extra_row(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-store-dup-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-store-dup-01');

        $this->insertPendingInvitation($workspaceId, 'pending-inv-store-dup-01@example.test', 'editor', $owner->id);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), [
                'email' => 'Pending-INV-Store-Dup-01@Example.TEST',
                'role' => 'editor',
            ]);

        $response->assertStatus(422, 'TEAM-INVITATIONS-STORE-DUPLICATE-01: aynı email için zaten pending davet varken 422 dönmeli (case-insensitive).');

        $countAfter = $this->countInvitationRows();
        self::assertSame(1, $countAfter, 'TEAM-INVITATIONS-STORE-DUPLICATE-01: reddedilen tekrar davet ekstra satır oluşturmamalı.');
    }

    // --- TEAM-INVITATIONS-STORE-OWNER-ONLY-01 -------------------------------

    public function test_non_owner_gets_enumeration_safe_404_before_any_business_state_is_revealed(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-store-owner-only-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-store-owner-only-01');

        $member = $this->verifiedUser('Mehmet Demir', 'mehmet-inv-store-owner-only-01@example.test');
        $this->addMember($workspaceId, $member, 'member');

        $editor = $this->verifiedUser('Elif Kaya', 'elif-inv-store-owner-only-01@example.test');
        $this->addMember($workspaceId, $editor, 'editor');

        $stranger = $this->verifiedUser('Can Öztürk', 'can-inv-store-owner-only-01@example.test');

        // The payload is syntactically valid (real email, role=editor) but
        // targets the requester's own already-member email — a business-state
        // conflict a FormRequest cannot see. Requiring 404 here (not 422)
        // proves the authorization gate runs before membership disclosure,
        // without relying on a shape the FormRequest would reject anyway.
        $memberResponse = $this->actingAs($member)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), ['email' => 'mehmet-inv-store-owner-only-01@example.test', 'role' => 'editor']);
        $memberResponse->assertStatus(404, 'TEAM-INVITATIONS-STORE-OWNER-ONLY-01: member davet oluşturamaz — 404 dönmeli, 422 değil (yetki kontrolü, üyelik ifşasından önce gelmeli).');

        $editorResponse = $this->actingAs($editor)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), ['email' => 'y-inv-store-owner-only-01@example.test', 'role' => 'editor']);
        $editorResponse->assertStatus(404, 'TEAM-INVITATIONS-STORE-OWNER-ONLY-01: editor davet oluşturamaz — 404 dönmeli, 403 değil.');

        $strangerResponse = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), ['email' => 'z-inv-store-owner-only-01@example.test', 'role' => 'editor']);
        $strangerResponse->assertStatus(404, 'TEAM-INVITATIONS-STORE-OWNER-ONLY-01: workspace\'e üye olmayan yabancı 404 almalı.');

        $nonexistentResponse = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri(999999999), ['email' => 'w-inv-store-owner-only-01@example.test', 'role' => 'editor']);

        self::assertSame(
            $strangerResponse->getStatusCode(),
            $nonexistentResponse->getStatusCode(),
            'TEAM-INVITATIONS-STORE-OWNER-ONLY-01: yabancı ve var-olmayan workspace aynı imzayı taşımalı (enumeration-safe).'
        );
    }

    // --- TEAM-INVITATIONS-STORE-AUTH-01 -------------------------------------

    public function test_endpoint_requires_authenticated_and_verified_user(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-store-auth-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-store-auth-01');
        $unverified = User::factory()->unverified()->create();

        $guestResponse = $this->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), ['email' => 'guest-inv-store-auth-01@example.test', 'role' => 'editor']);
        self::assertContains(
            $guestResponse->getStatusCode(),
            [401, 403],
            'TEAM-INVITATIONS-STORE-AUTH-01: kimliksiz istek 401/403 ile reddedilmeli (404 kabul edilmez).'
        );

        $unverifiedResponse = $this->actingAs($unverified)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), ['email' => 'unverified-inv-store-auth-01@example.test', 'role' => 'editor']);
        $unverifiedResponse->assertStatus(403, 'TEAM-INVITATIONS-STORE-AUTH-01: doğrulanmamış (unverified) kullanıcı 403 ile reddedilmeli.');
    }

    // --- TEAM-INVITATIONS-STORE-TENANT-SCOPE-01 ------------------------------

    public function test_same_normalized_email_can_be_invited_independently_into_two_different_workspaces(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-store-tenant-01@example.test');
        $workspaceOneId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-store-tenant-01');
        $workspaceTwoId = $this->workspaceOwnedBy($owner, 'Nar Restoranları', 'nar-inv-store-tenant-01');

        $email = 'Shared-INV-Store-Tenant-01@Example.TEST';
        $normalizedEmail = 'shared-inv-store-tenant-01@example.test';

        $firstResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceOneId), ['email' => $email, 'role' => 'editor']);
        $firstResponse->assertStatus(201, 'TEAM-INVITATIONS-STORE-TENANT-SCOPE-01: ilk workspace için davet 201 dönmeli.');

        $secondResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceTwoId), ['email' => $email, 'role' => 'editor']);
        $secondResponse->assertStatus(201, 'TEAM-INVITATIONS-STORE-TENANT-SCOPE-01: aynı email farklı bir workspace için de 201 ile kabul edilmeli — benzersizlik workspace+email bazında.');

        $rowsForEmail = DB::table('team_invitations')->where('email', $normalizedEmail)->orderBy('id')->get();
        self::assertCount(2, $rowsForEmail, 'TEAM-INVITATIONS-STORE-TENANT-SCOPE-01: her workspace için tam olarak bir satır oluşmalı, toplam 2.');
        self::assertSame($workspaceOneId, (int) $rowsForEmail[0]->workspace_id);
        self::assertSame($workspaceTwoId, (int) $rowsForEmail[1]->workspace_id);
    }

    // --- TEAM-INVITATIONS-STORE-REACTIVATE-01 -------------------------------

    /**
     * team_invitations carries a unique(workspace_id, email) constraint
     * (see database/migrations/2026_08_23_000009_create_team_invitations_table.php),
     * so a cancelled invitation for the same email cannot become a second
     * row — reactivation must be an UPDATE of the existing cancelled row
     * back to pending, not an INSERT.
     */
    public function test_owner_reactivates_a_cancelled_invitation_by_reusing_the_same_row(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-store-reactivate-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-store-reactivate-01');

        $cancelledInvitationId = $this->insertPendingInvitation($workspaceId, 'cancelled-inv-store-reactivate-01@example.test', 'editor', $owner->id);
        DB::table('team_invitations')->where('id', $cancelledInvitationId)->update(['status' => 'cancelled', 'updated_at' => now()]);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), [
                'email' => 'Cancelled-INV-Store-Reactivate-01@Example.TEST',
                'role' => 'editor',
            ]);

        $response->assertStatus(201, 'TEAM-INVITATIONS-STORE-REACTIVATE-01: iptal edilmiş bir davet yeniden gönderildiğinde 201 dönmeli.');
        $body = $response->json();

        self::assertSame($cancelledInvitationId, $body['id'] ?? null, 'TEAM-INVITATIONS-STORE-REACTIVATE-01: yeni satır oluşturulmamalı — aynı id yeniden aktifleşmeli.');
        self::assertSame('cancelled-inv-store-reactivate-01@example.test', $body['email'] ?? null);
        self::assertSame('editor', $body['role'] ?? null);
        self::assertSame('pending', $body['status'] ?? null, 'TEAM-INVITATIONS-STORE-REACTIVATE-01: yeniden gönderilen davet pending olmalı.');

        self::assertSame(1, $this->countInvitationRows(), 'TEAM-INVITATIONS-STORE-REACTIVATE-01: reaktivasyon ikinci bir satır oluşturmamalı (workspace_id+email unique).');

        $row = DB::table('team_invitations')->where('id', $cancelledInvitationId)->first();
        self::assertSame('pending', $row->status);
        self::assertSame('cancelled-inv-store-reactivate-01@example.test', $row->email);
        self::assertSame('editor', $row->role);
        self::assertSame($owner->id, (int) $row->invited_by, 'TEAM-INVITATIONS-STORE-REACTIVATE-01: invited_by yeniden gönderen sahibe güncellenmeli.');

        $secondPendingAttempt = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), [
                'email' => 'cancelled-inv-store-reactivate-01@example.test',
                'role' => 'editor',
            ]);
        $secondPendingAttempt->assertStatus(422, 'TEAM-INVITATIONS-STORE-REACTIVATE-01: artık pending olan davet için tekrar istek 422 ile reddedilmeli.');

        self::assertSame(1, $this->countInvitationRows(), 'TEAM-INVITATIONS-STORE-REACTIVATE-01: reddedilen tekrar istek satır sayısını artırmamalı.');
    }

    // --- TEAM-INVITATIONS-STORE-THROTTLE-01 ---------------------------------

    public function test_sixth_valid_request_within_one_minute_is_throttled_with_429(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-store-throttle-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-store-throttle-01');

        RateLimiter::clear(sha1((string) $owner->getAuthIdentifier()));

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
                ->postJson($this->invitationsUri($workspaceId), [
                    'email' => sprintf('throttle-%d-inv-store-throttle-01@example.test', $i),
                    'role' => 'editor',
                ]);

            $response->assertStatus(201, sprintf('TEAM-INVITATIONS-STORE-THROTTLE-01: dakikadaki %d. istek tam olarak 201 dönmeli.', $i));
        }

        $sixthResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), [
                'email' => 'throttle-6-inv-store-throttle-01@example.test',
                'role' => 'editor',
            ]);

        $sixthResponse->assertStatus(429, 'TEAM-INVITATIONS-STORE-THROTTLE-01: dakikada 6. istek throttle:5,1 ile 429 almalı.');
    }
}
