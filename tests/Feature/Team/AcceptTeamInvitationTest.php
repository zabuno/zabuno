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
 * Blind RED test candidate for POST /api/invitations/accept/{rawToken}
 * (accept a pending Team invitation, S1-WP01A Team invitations accept
 * slice). No production code for this endpoint exists yet: grep of
 * routes/api.php confirms zero "invitations/accept" routes are
 * registered, so every request below is expected to fail RED with a 404
 * from the framework's router (unmatched route), not from malformed
 * fixtures or guessed production response shapes.
 *
 * Frozen contract: token is looked up only via a sha256(raw token) hash
 * column (never a plaintext token column); only the exact authenticated
 * and verified user whose email matches the pending invitation's email
 * may accept it — success is 204 with no body, an editor workspace
 * membership row is created for that user/workspace, and the invitation
 * row flips to status "accepted" with accepted_at/accepted_by set; a
 * different authenticated user gets 404 and no mutation occurs; a guest
 * gets 401/403; an unverified authenticated user (even with a matching
 * email) gets 403; an unknown token is 404; an expired token is 404 or
 * 410 and causes no mutation; a cancelled invitation's token is 404; an
 * already-accepted token used a second time is 404/410 and does not
 * create a duplicate membership; the write is throttled to 5 requests
 * per minute (429 on the 6th attempt).
 *
 * Requirement IDs: TEAM-INVITATIONS-ACCEPT-01,
 * TEAM-INVITATIONS-ACCEPT-EMAIL-MISMATCH-01,
 * TEAM-INVITATIONS-ACCEPT-AUTH-01, TEAM-INVITATIONS-ACCEPT-UNVERIFIED-01,
 * TEAM-INVITATIONS-ACCEPT-UNKNOWN-TOKEN-01,
 * TEAM-INVITATIONS-ACCEPT-EXPIRED-01,
 * TEAM-INVITATIONS-ACCEPT-CANCELLED-01,
 * TEAM-INVITATIONS-ACCEPT-ALREADY-ACCEPTED-01,
 * TEAM-INVITATIONS-ACCEPT-THROTTLE-01.
 */
final class AcceptTeamInvitationTest extends TestCase
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

    private function ensureTeamInvitationsSchemaForFixture(): void
    {
        if (! Schema::hasTable('team_invitations')) {
            self::fail('TEAM-INVITATIONS-SCHEMA-01: team_invitations tablosu henüz yok — bu RED, eksik migration/şemadan kaynaklanmalı, fixture kurulumunda gizli bir sorgu hatasından değil.');
        }

        $requiredColumns = ['token_hash', 'expires_at', 'accepted_at', 'accepted_by', 'status'];
        $missing = array_values(array_filter(
            $requiredColumns,
            static fn (string $column): bool => ! Schema::hasColumn('team_invitations', $column)
        ));

        if ($missing !== []) {
            self::fail(sprintf(
                'TEAM-INVITATIONS-ACCEPT-SCHEMA-01: team_invitations tablosunda kabul akışı için gereken kolon(lar) eksik: %s. Bu RED, eksik migration/şemadan kaynaklanmalı, PDO hatasından değil.',
                implode(', ', $missing)
            ));
        }

        self::assertFalse(
            Schema::hasColumn('team_invitations', 'token'),
            'TEAM-INVITATIONS-ACCEPT-SCHEMA-01: fixture yalnız token_hash kolonuyla çalışmalı; plaintext "token" kolonu beklenmiyor.'
        );
    }

    private function insertInvitation(
        int $workspaceId,
        string $email,
        string $rawToken,
        string $status,
        int $invitedBy,
        ?\DateTimeInterface $expiresAt = null,
        ?\DateTimeInterface $acceptedAt = null,
        ?int $acceptedBy = null,
    ): int {
        $this->ensureTeamInvitationsSchemaForFixture();

        return (int) DB::table('team_invitations')->insertGetId([
            'workspace_id' => $workspaceId,
            'email' => $email,
            'role' => 'editor',
            'status' => $status,
            'invited_by' => $invitedBy,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => $expiresAt ?? now()->addDays(7),
            'accepted_at' => $acceptedAt,
            'accepted_by' => $acceptedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function invitationRow(int $invitationId): ?object
    {
        $this->ensureTeamInvitationsSchemaForFixture();

        return DB::table('team_invitations')->where('id', $invitationId)->first();
    }

    private function membershipRow(int $workspaceId, int $userId): ?object
    {
        return DB::table('workspace_memberships')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->first();
    }

    private function acceptUri(string $rawToken): string
    {
        return "/api/invitations/accept/{$rawToken}";
    }

    // --- TEAM-INVITATIONS-ACCEPT-01 -----------------------------------------

    public function test_matching_verified_user_accepts_pending_invitation_with_204_and_creates_editor_membership(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-accept-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-accept-01');

        $invitee = $this->verifiedUser('Mehmet Demir', 'mehmet-inv-accept-01@example.test');
        $rawToken = bin2hex(random_bytes(32));
        $invitationId = $this->insertInvitation($workspaceId, $invitee->email, $rawToken, 'pending', $owner->id);

        $response = $this->actingAs($invitee)->withHeaders($this->jsonHeaders())
            ->postJson($this->acceptUri($rawToken));

        $response->assertStatus(204, 'TEAM-INVITATIONS-ACCEPT-01: eşleşen doğrulanmış kullanıcı daveti kabul ettiğinde 204 dönmeli.');
        self::assertSame('', $response->getContent(), 'TEAM-INVITATIONS-ACCEPT-01: 204 yanıt gövdesi boş olmalı.');

        $row = $this->invitationRow($invitationId);
        self::assertNotNull($row, 'TEAM-INVITATIONS-ACCEPT-01: satır silinmemeli.');
        self::assertSame('accepted', $row->status, 'TEAM-INVITATIONS-ACCEPT-01: kabul edilen davetin status alanı "accepted" olmalı.');
        self::assertNotNull($row->accepted_at, 'TEAM-INVITATIONS-ACCEPT-01: accepted_at doldurulmalı.');
        self::assertSame($invitee->id, (int) $row->accepted_by, 'TEAM-INVITATIONS-ACCEPT-01: accepted_by kabul eden kullanıcının id\'si olmalı.');

        $membership = $this->membershipRow($workspaceId, $invitee->id);
        self::assertNotNull($membership, 'TEAM-INVITATIONS-ACCEPT-01: workspace membership satırı oluşmalı.');
        self::assertSame('editor', $membership->role, 'TEAM-INVITATIONS-ACCEPT-01: oluşturulan membership rolü tam olarak "editor" olmalı.');
    }

    // --- TEAM-INVITATIONS-ACCEPT-EMAIL-MISMATCH-01 --------------------------

    public function test_authenticated_user_with_different_email_gets_404_and_causes_no_mutation(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-accept-mismatch-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-accept-mismatch-01');

        $invitee = $this->verifiedUser('Mehmet Demir', 'mehmet-inv-accept-mismatch-01@example.test');
        $stranger = $this->verifiedUser('Can Öztürk', 'can-inv-accept-mismatch-01@example.test');

        $rawToken = bin2hex(random_bytes(32));
        $invitationId = $this->insertInvitation($workspaceId, $invitee->email, $rawToken, 'pending', $owner->id);

        $response = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->postJson($this->acceptUri($rawToken));

        $response->assertStatus(404, 'TEAM-INVITATIONS-ACCEPT-EMAIL-MISMATCH-01: eşleşmeyen e-posta ile giriş yapan kullanıcı 404 almalı.');

        $row = $this->invitationRow($invitationId);
        self::assertSame('pending', $row->status, 'TEAM-INVITATIONS-ACCEPT-EMAIL-MISMATCH-01: reddedilen istekten sonra davet hâlâ pending olmalı.');
        self::assertNull($this->membershipRow($workspaceId, $stranger->id), 'TEAM-INVITATIONS-ACCEPT-EMAIL-MISMATCH-01: yabancı için membership oluşmamalı.');
    }

    // --- TEAM-INVITATIONS-ACCEPT-AUTH-01 ------------------------------------

    public function test_guest_is_rejected_with_401_or_403(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-accept-auth-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-accept-auth-01');

        $invitee = $this->verifiedUser('Mehmet Demir', 'mehmet-inv-accept-auth-01@example.test');
        $rawToken = bin2hex(random_bytes(32));
        $invitationId = $this->insertInvitation($workspaceId, $invitee->email, $rawToken, 'pending', $owner->id);

        $response = $this->withHeaders($this->jsonHeaders())->postJson($this->acceptUri($rawToken));

        self::assertContains(
            $response->getStatusCode(),
            [401, 403],
            'TEAM-INVITATIONS-ACCEPT-AUTH-01: kimliksiz istek 401/403 ile reddedilmeli (404 kabul edilmez).'
        );

        self::assertSame('pending', $this->invitationRow($invitationId)->status, 'TEAM-INVITATIONS-ACCEPT-AUTH-01: reddedilen istekten sonra davet hâlâ pending olmalı.');
    }

    // --- TEAM-INVITATIONS-ACCEPT-UNVERIFIED-01 ------------------------------

    public function test_unverified_matching_user_is_rejected_with_403_and_causes_no_mutation(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-accept-unverified-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-accept-unverified-01');

        $inviteeEmail = 'mehmet-inv-accept-unverified-01@example.test';
        $invitee = User::factory()->unverified()->create([
            'name' => 'Mehmet Demir',
            'email' => $inviteeEmail,
        ]);

        $rawToken = bin2hex(random_bytes(32));
        $invitationId = $this->insertInvitation($workspaceId, $inviteeEmail, $rawToken, 'pending', $owner->id);

        $response = $this->actingAs($invitee)->withHeaders($this->jsonHeaders())
            ->postJson($this->acceptUri($rawToken));

        $response->assertStatus(403, 'TEAM-INVITATIONS-ACCEPT-UNVERIFIED-01: e-posta eşleşse bile doğrulanmamış kullanıcı 403 almalı.');

        self::assertSame('pending', $this->invitationRow($invitationId)->status, 'TEAM-INVITATIONS-ACCEPT-UNVERIFIED-01: reddedilen istekten sonra davet hâlâ pending olmalı.');
        self::assertNull($this->membershipRow($workspaceId, $invitee->id), 'TEAM-INVITATIONS-ACCEPT-UNVERIFIED-01: membership oluşmamalı.');
    }

    // --- TEAM-INVITATIONS-ACCEPT-UNKNOWN-TOKEN-01 ---------------------------

    public function test_unknown_token_is_rejected_with_404(): void
    {
        $invitee = $this->verifiedUser('Mehmet Demir', 'mehmet-inv-accept-unknown-01@example.test');

        $this->ensureTeamInvitationsSchemaForFixture();

        $response = $this->actingAs($invitee)->withHeaders($this->jsonHeaders())
            ->postJson($this->acceptUri(bin2hex(random_bytes(32))));

        $response->assertStatus(404, 'TEAM-INVITATIONS-ACCEPT-UNKNOWN-TOKEN-01: bilinmeyen token 404 almalı.');
    }

    // --- TEAM-INVITATIONS-ACCEPT-EXPIRED-01 ---------------------------------

    public function test_expired_token_is_rejected_and_causes_no_mutation(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-accept-expired-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-accept-expired-01');

        $invitee = $this->verifiedUser('Mehmet Demir', 'mehmet-inv-accept-expired-01@example.test');
        $rawToken = bin2hex(random_bytes(32));
        $invitationId = $this->insertInvitation(
            $workspaceId,
            $invitee->email,
            $rawToken,
            'pending',
            $owner->id,
            now()->subMinute(),
        );

        $response = $this->actingAs($invitee)->withHeaders($this->jsonHeaders())
            ->postJson($this->acceptUri($rawToken));

        self::assertContains(
            $response->getStatusCode(),
            [404, 410],
            'TEAM-INVITATIONS-ACCEPT-EXPIRED-01: süresi dolmuş token 404 veya 410 ile reddedilmeli.'
        );

        self::assertSame('pending', $this->invitationRow($invitationId)->status, 'TEAM-INVITATIONS-ACCEPT-EXPIRED-01: reddedilen istekten sonra davet hâlâ pending olmalı.');
        self::assertNull($this->membershipRow($workspaceId, $invitee->id), 'TEAM-INVITATIONS-ACCEPT-EXPIRED-01: membership oluşmamalı.');
    }

    // --- TEAM-INVITATIONS-ACCEPT-CANCELLED-01 -------------------------------

    public function test_cancelled_invitation_token_is_rejected_with_404_and_causes_no_mutation(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-accept-cancelled-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-accept-cancelled-01');

        $invitee = $this->verifiedUser('Mehmet Demir', 'mehmet-inv-accept-cancelled-01@example.test');
        $rawToken = bin2hex(random_bytes(32));
        $invitationId = $this->insertInvitation($workspaceId, $invitee->email, $rawToken, 'cancelled', $owner->id);

        $response = $this->actingAs($invitee)->withHeaders($this->jsonHeaders())
            ->postJson($this->acceptUri($rawToken));

        $response->assertStatus(404, 'TEAM-INVITATIONS-ACCEPT-CANCELLED-01: iptal edilmiş davetin token\'ı 404 almalı.');

        self::assertSame('cancelled', $this->invitationRow($invitationId)->status, 'TEAM-INVITATIONS-ACCEPT-CANCELLED-01: reddedilen istekten sonra davet hâlâ cancelled olmalı.');
        self::assertNull($this->membershipRow($workspaceId, $invitee->id), 'TEAM-INVITATIONS-ACCEPT-CANCELLED-01: membership oluşmamalı.');
    }

    // --- TEAM-INVITATIONS-ACCEPT-ALREADY-ACCEPTED-01 ------------------------

    public function test_already_accepted_token_used_a_second_time_is_rejected_and_does_not_duplicate_membership(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-accept-already-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-accept-already-01');

        $invitee = $this->verifiedUser('Mehmet Demir', 'mehmet-inv-accept-already-01@example.test');
        $rawToken = bin2hex(random_bytes(32));
        $invitationId = $this->insertInvitation(
            $workspaceId,
            $invitee->email,
            $rawToken,
            'accepted',
            $owner->id,
            null,
            now()->subDay(),
            $invitee->id,
        );

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $invitee->id,
            'role' => 'editor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($invitee)->withHeaders($this->jsonHeaders())
            ->postJson($this->acceptUri($rawToken));

        self::assertContains(
            $response->getStatusCode(),
            [404, 410],
            'TEAM-INVITATIONS-ACCEPT-ALREADY-ACCEPTED-01: zaten kabul edilmiş token ikinci kullanımda 404 veya 410 almalı.'
        );

        $membershipCount = DB::table('workspace_memberships')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $invitee->id)
            ->count();
        self::assertSame(1, $membershipCount, 'TEAM-INVITATIONS-ACCEPT-ALREADY-ACCEPTED-01: ikinci kabul denemesi duplicate membership oluşturmamalı.');

        self::assertSame('accepted', $this->invitationRow($invitationId)->status);
    }

    // --- TEAM-INVITATIONS-ACCEPT-THROTTLE-01 --------------------------------

    public function test_sixth_accept_request_within_one_minute_is_throttled_with_429(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-accept-throttle-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-accept-throttle-01');

        $invitee = $this->verifiedUser('Mehmet Demir', 'mehmet-inv-accept-throttle-01@example.test');

        RateLimiter::clear(sha1((string) $invitee->getAuthIdentifier()));

        $rawTokens = [];
        for ($i = 1; $i <= 6; $i++) {
            $rawTokens[] = bin2hex(random_bytes(32));
        }

        // Only the first token is valid/pending; the remaining five are
        // deliberately unknown tokens so the throttle assertion does not
        // depend on six distinct pending invitations for the same email
        // (which the unique(workspace_id, email) fixture constraint would
        // not otherwise allow) — throttling must still fire on the 6th
        // request regardless of business-state outcome.
        $this->insertInvitation($workspaceId, $invitee->email, $rawTokens[0], 'pending', $owner->id);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($invitee)->withHeaders($this->jsonHeaders())
                ->postJson($this->acceptUri($rawTokens[$i]));

            self::assertNotSame(429, $response->getStatusCode(), sprintf('TEAM-INVITATIONS-ACCEPT-THROTTLE-01: dakikadaki %d. istek throttle ile reddedilmemeli.', $i + 1));
        }

        $sixthResponse = $this->actingAs($invitee)->withHeaders($this->jsonHeaders())
            ->postJson($this->acceptUri($rawTokens[5]));

        $sixthResponse->assertStatus(429, 'TEAM-INVITATIONS-ACCEPT-THROTTLE-01: dakikada 6. istek throttle:5,1 ile 429 almalı.');
    }
}
