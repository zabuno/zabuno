<?php

declare(strict_types=1);

namespace Tests\Feature\Team;

use App\Mail\TeamInvitationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Blind RED test candidate for the Team invitation *delivery* journey:
 * TeamInvitationMail dispatch + token issuance on store, cancel/reinvite
 * token rotation, the public GET /invitations/{token} accept-shell view,
 * and the safe local-redirect-only /login handoff. None of
 * App\Mail\TeamInvitationMail, GET /invitations/{token}, or a
 * redirect-carrying login JSON response exist yet in production code, so
 * every assertion below is expected to fail RED against missing
 * mail/token/route/view/login-response wiring — never against a fixture
 * or syntax defect.
 *
 * The guest GET is a raw Blade mount shell: React has not hydrated in a
 * test request, so no anchor is rendered yet. We assert the exact
 * data-login-url="..." mount dataset value, never an <a href> selector.
 *
 * Generic non-available states are NOT collapsed to one label: unknown,
 * mismatched-user and cancelled tokens are "invalid"; an expired token is
 * "expired"; an already-accepted token is "consumed". Every one of those
 * states must still ship an empty `sensitive` payload.
 *
 * Requirement IDs: TEAM-INVITATIONS-DELIVERY-MAIL-01,
 * TEAM-INVITATIONS-DELIVERY-DUPLICATE-01, TEAM-INVITATIONS-DELIVERY-REINVITE-01,
 * TEAM-INVITATIONS-DELIVERY-GUEST-VIEW-01, TEAM-INVITATIONS-DELIVERY-MATCH-VIEW-01,
 * TEAM-INVITATIONS-DELIVERY-INVALID-VIEW-01, TEAM-INVITATIONS-DELIVERY-LOGIN-REDIRECT-01,
 * TEAM-INVITATIONS-DELIVERY-LOGIN-REDIRECT-UNSAFE-01.
 */
final class InvitationDeliveryJourneyTest extends TestCase
{
    use RefreshDatabase;

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function verifiedUser(string $name, string $email): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
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

    private function ensureTeamInvitationsSchema(): void
    {
        if (! Schema::hasTable('team_invitations')) {
            self::fail('TEAM-INVITATIONS-DELIVERY-SCHEMA-01: team_invitations tablosu yok — RED eksik migrasyondan kaynaklanmalı.');
        }
    }

    private function invitationsUri(int $workspaceId): string
    {
        return "/api/workspaces/{$workspaceId}/team/invitations";
    }

    private function cancelUri(int $workspaceId, int $invitationId): string
    {
        return "/api/workspaces/{$workspaceId}/team/invitations/{$invitationId}";
    }

    private function fetchInvitationRow(int $invitationId): ?object
    {
        $this->ensureTeamInvitationsSchema();

        return DB::table('team_invitations')->where('id', $invitationId)->first();
    }

    private function extractRawTokenFromMail(TeamInvitationMail $mail): string
    {
        $rendered = $mail->render();

        self::assertMatchesRegularExpression(
            '#/invitations/([A-Za-z0-9_-]{64})#',
            $rendered,
            'TEAM-INVITATIONS-DELIVERY-MAIL-01: mail gövdesinde server absolute GET /invitations/{64-char rawToken} linki olmalı.'
        );

        preg_match('#/invitations/([A-Za-z0-9_-]{64})#', $rendered, $matches);

        return $matches[1];
    }

    /**
     * Stores an invitation, captures the freshest TeamInvitationMail (by
     * excluding $excludeMail when a prior mail already exists for this
     * test), and returns its raw token. Mail::fake() must already be set.
     */
    private function storeInvitationAndCaptureRawToken(User $owner, int $workspaceId, string $email, ?TeamInvitationMail $excludeMail = null): string
    {
        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), ['email' => $email, 'role' => 'editor']);
        $response->assertStatus(201);

        $captured = null;
        Mail::assertSent(TeamInvitationMail::class, function (TeamInvitationMail $m) use (&$captured, $excludeMail): bool {
            if ($excludeMail !== null && $m === $excludeMail) {
                return false;
            }
            $captured = $m;

            return true;
        });
        self::assertNotNull($captured, 'TEAM-INVITATIONS-DELIVERY-MAIL-01: yeni bir TeamInvitationMail yakalanmalı.');

        return $this->extractRawTokenFromMail($captured);
    }

    // --- TEAM-INVITATIONS-DELIVERY-MAIL-01 -----------------------------------

    public function test_store_sends_team_invitation_mail_with_opaque_token_and_hashed_persistence(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-deliv-mail-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-deliv-mail-01');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), [
                'email' => 'Mehmet-INV-Deliv-Mail-01@Example.TEST',
                'role' => 'editor',
            ]);

        $response->assertStatus(201, 'TEAM-INVITATIONS-DELIVERY-MAIL-01: davet oluşturma 201 dönmeli.');
        $body = $response->json();

        self::assertIsArray($body);
        self::assertSame(
            ['id', 'email', 'role', 'status'],
            array_keys($body),
            'TEAM-INVITATIONS-DELIVERY-MAIL-01: mail gönderimi 201 yanıt şeklini değiştirmemeli.'
        );

        $normalizedEmail = 'mehmet-inv-deliv-mail-01@example.test';

        Mail::assertSent(TeamInvitationMail::class, function (TeamInvitationMail $mail) use ($normalizedEmail): bool {
            return $mail->hasTo($normalizedEmail);
        });
        Mail::assertSentCount(1);
        Mail::assertNotSent(TeamInvitationMail::class, fn (TeamInvitationMail $mail): bool => ! $mail->hasTo($normalizedEmail));

        $row = $this->fetchInvitationRow((int) $body['id']);
        self::assertNotNull($row);
        self::assertNotNull($row->token_hash ?? null, 'TEAM-INVITATIONS-DELIVERY-MAIL-01: token_hash yazılmalı.');
        self::assertSame(64, strlen((string) $row->token_hash), 'TEAM-INVITATIONS-DELIVERY-MAIL-01: token_hash sha256 hex uzunluğunda (64) olmalı.');
        self::assertFalse(
            Schema::hasColumn('team_invitations', 'token'),
            'TEAM-INVITATIONS-DELIVERY-MAIL-01: plaintext token kolonu olmamalı.'
        );

        self::assertNotNull($row->expires_at ?? null, 'TEAM-INVITATIONS-DELIVERY-MAIL-01: expires_at yazılmalı.');
        $expiresAt = \Carbon\Carbon::parse($row->expires_at);
        $expectedExpiry = now()->addDays(7);
        self::assertEqualsWithDelta(
            $expectedExpiry->timestamp,
            $expiresAt->timestamp,
            60,
            'TEAM-INVITATIONS-DELIVERY-MAIL-01: expires_at yaklaşık 7 gün sonrası olmalı.'
        );

        $sentMail = null;
        Mail::assertSent(TeamInvitationMail::class, function (TeamInvitationMail $mail) use (&$sentMail): bool {
            $sentMail = $mail;

            return true;
        });
        self::assertNotNull($sentMail);

        $rawToken = $this->extractRawTokenFromMail($sentMail);
        self::assertSame(
            hash('sha256', $rawToken),
            $row->token_hash,
            'TEAM-INVITATIONS-DELIVERY-MAIL-01: DB token_hash tam olarak sha256(rawToken) olmalı.'
        );
    }

    // --- TEAM-INVITATIONS-DELIVERY-DUPLICATE-01 ------------------------------

    public function test_duplicate_pending_invitation_is_rejected_without_token_or_mail_rotation(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-deliv-dup-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-deliv-dup-01');

        $first = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), [
                'email' => 'dup-inv-deliv-dup-01@example.test',
                'role' => 'editor',
            ]);
        $first->assertStatus(201);

        $firstRow = $this->fetchInvitationRow((int) $first->json('id'));
        $firstTokenHash = $firstRow->token_hash ?? null;
        $firstExpiresAt = $firstRow->expires_at ?? null;

        $second = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), [
                'email' => 'Dup-INV-Deliv-Dup-01@Example.TEST',
                'role' => 'editor',
            ]);
        $second->assertStatus(422, 'TEAM-INVITATIONS-DELIVERY-DUPLICATE-01: pending davet tekrar isteği 422 dönmeli.');

        Mail::assertSentCount(1, 'TEAM-INVITATIONS-DELIVERY-DUPLICATE-01: reddedilen tekrar istek yeni mail göndermemeli.');

        $rowAfter = $this->fetchInvitationRow((int) $first->json('id'));
        self::assertSame($firstTokenHash, $rowAfter->token_hash ?? null, 'TEAM-INVITATIONS-DELIVERY-DUPLICATE-01: token_hash rotate edilmemeli.');
        self::assertSame($firstExpiresAt, $rowAfter->expires_at ?? null, 'TEAM-INVITATIONS-DELIVERY-DUPLICATE-01: expires_at rotate edilmemeli.');
    }

    // --- TEAM-INVITATIONS-DELIVERY-REINVITE-01 -------------------------------

    public function test_cancel_then_reinvite_reuses_row_id_but_rotates_token_and_invalidates_old_link(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-deliv-reinv-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-deliv-reinv-01');

        $email = 'reinv-inv-deliv-reinv-01@example.test';
        $oldRawToken = $this->storeInvitationAndCaptureRawToken($owner, $workspaceId, $email);

        $row = DB::table('team_invitations')->where('workspace_id', $workspaceId)->where('email', $email)->first();
        $invitationId = (int) $row->id;

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->deleteJson($this->cancelUri($workspaceId, $invitationId))
            ->assertStatus(204, 'TEAM-INVITATIONS-DELIVERY-REINVITE-01: cancel 204 dönmeli.');

        $firstSentMail = null;
        Mail::assertSent(TeamInvitationMail::class, function (TeamInvitationMail $m) use (&$firstSentMail): bool {
            $firstSentMail = $m;

            return true;
        });

        $reinvite = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), ['email' => $email, 'role' => 'editor']);
        $reinvite->assertStatus(201, 'TEAM-INVITATIONS-DELIVERY-REINVITE-01: iptal sonrası reinvite 201 dönmeli.');
        self::assertSame($invitationId, $reinvite->json('id'), 'TEAM-INVITATIONS-DELIVERY-REINVITE-01: aynı row id yeniden pending olmalı.');
        self::assertSame('pending', $reinvite->json('status'));

        Mail::assertSentCount(2, 'TEAM-INVITATIONS-DELIVERY-REINVITE-01: reinvite ikinci bir mail üretmeli.');

        $newRawToken = null;
        Mail::assertSent(TeamInvitationMail::class, function (TeamInvitationMail $m) use (&$newRawToken, $firstSentMail): bool {
            if ($m === $firstSentMail) {
                return false;
            }
            $newRawToken = $this->extractRawTokenFromMail($m);

            return true;
        });
        self::assertNotNull($newRawToken, 'TEAM-INVITATIONS-DELIVERY-REINVITE-01: ikinci farklı bir mail nesnesi olmalı.');
        self::assertNotSame($oldRawToken, $newRawToken, 'TEAM-INVITATIONS-DELIVERY-REINVITE-01: token rotate edilmeli.');

        $updatedRow = $this->fetchInvitationRow($invitationId);
        self::assertSame(hash('sha256', $newRawToken), $updatedRow->token_hash, 'TEAM-INVITATIONS-DELIVERY-REINVITE-01: DB token_hash yeni raw token ile eşleşmeli.');

        $this->assertNonAvailableState("/invitations/{$oldRawToken}", 'invalid', 'TEAM-INVITATIONS-DELIVERY-REINVITE-01-OLD');

        $newLinkResponse = $this->withHeaders($this->jsonHeaders())->getJson("/invitations/{$newRawToken}");
        $newLinkResponse->assertStatus(200, 'TEAM-INVITATIONS-DELIVERY-REINVITE-01: yeni link 200 dönmeli.');
        self::assertSame('guest', $newLinkResponse->json('data.state'), 'TEAM-INVITATIONS-DELIVERY-REINVITE-01: yeni link geçerli (guest state) olmalı.');
    }

    // --- TEAM-INVITATIONS-DELIVERY-GUEST-VIEW-01 -----------------------------

    public function test_guest_get_invitation_returns_auth_shell_without_sensitive_workspace_data(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-deliv-guest-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Gizli Restoran Adı', 'gizli-inv-deliv-guest-01');

        $rawToken = $this->storeInvitationAndCaptureRawToken($owner, $workspaceId, 'guest-inv-deliv-guest-01@example.test');

        $htmlResponse = $this->withHeaders(['Accept' => 'text/html'])
            ->get("/invitations/{$rawToken}");
        $htmlResponse->assertStatus(200, 'TEAM-INVITATIONS-DELIVERY-GUEST-VIEW-01: guest GET 200 dönmeli.');
        $htmlResponse->assertSee('data-auth-view="invitation-accept"', false);
        $htmlResponse->assertSee('data-invitation-status="guest"', false);
        $htmlResponse->assertSee('data-authenticated="false"', false);

        $content = $htmlResponse->getContent();
        self::assertIsString($content);
        self::assertStringNotContainsString('Gizli Restoran Adı', $content, 'TEAM-INVITATIONS-DELIVERY-GUEST-VIEW-01: guest DOM workspace adı taşımamalı.');
        self::assertStringNotContainsString('guest-inv-deliv-guest-01@example.test', $content, 'TEAM-INVITATIONS-DELIVERY-GUEST-VIEW-01: guest DOM davet emailini taşımamalı.');
        self::assertStringNotContainsString('editor', $content, 'TEAM-INVITATIONS-DELIVERY-GUEST-VIEW-01: guest DOM rol bilgisini taşımamalı.');

        // React has not hydrated in a test request, so no <a href> exists
        // yet — assert the exact raw Blade mount dataset value instead.
        $htmlResponse->assertSee(
            'data-login-url="/login?redirect=%2Finvitations%2F'.$rawToken.'"',
            false
        );
    }

    // --- TEAM-INVITATIONS-DELIVERY-MATCH-VIEW-01 -----------------------------

    public function test_matching_verified_session_get_invitation_returns_available_state_with_real_workspace_data(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-deliv-match-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Nar Restoranları', 'nar-inv-deliv-match-01');

        $invitee = $this->verifiedUser('Elif Kaya', 'elif-inv-deliv-match-01@example.test');

        $rawToken = $this->storeInvitationAndCaptureRawToken($owner, $workspaceId, 'elif-inv-deliv-match-01@example.test');

        $response = $this->actingAs($invitee)->withHeaders($this->jsonHeaders())
            ->getJson("/invitations/{$rawToken}");

        $response->assertStatus(200, 'TEAM-INVITATIONS-DELIVERY-MATCH-VIEW-01: eşleşen doğrulanmış oturum 200 dönmeli.');
        $body = $response->json();

        self::assertSame('available', $body['data']['state'] ?? null);
        self::assertTrue($body['data']['authenticated'] ?? null, 'TEAM-INVITATIONS-DELIVERY-MATCH-VIEW-01: authenticated true olmalı.');
        self::assertSame('Nar Restoranları', $body['data']['workspaceName'] ?? null, 'TEAM-INVITATIONS-DELIVERY-MATCH-VIEW-01: gerçek workspace adı görünmeli.');
        self::assertSame('elif-inv-deliv-match-01@example.test', $body['data']['email'] ?? null);
        self::assertSame('editor', $body['data']['role'] ?? null);
        self::assertSame(
            "/api/invitations/accept/{$rawToken}",
            $body['data']['acceptUrl'] ?? null,
            'TEAM-INVITATIONS-DELIVERY-MATCH-VIEW-01: exact accept URL raw token ile üretilmeli.'
        );
    }

    // --- TEAM-INVITATIONS-DELIVERY-INVALID-VIEW-01 ---------------------------

    private function assertNonAvailableState(string $uri, string $expectedState, string $requirementId, ?User $as = null): void
    {
        if ($as !== null) {
            $request = $this->actingAs($as);
        } else {
            $this->app['auth']->guard('web')->logout();
            $request = $this;
        }

        $response = $request->withHeaders($this->jsonHeaders())->getJson($uri);

        $response->assertStatus(200, "{$requirementId}: 200 dönmeli, 500 değil.");
        self::assertSame($expectedState, $response->json('data.state'), "{$requirementId}: state=\"{$expectedState}\" olmalı.");
        self::assertSame([], $response->json('data.sensitive') ?? [], "{$requirementId}: sensitive boş olmalı.");
    }

    public function test_unknown_mismatched_cancelled_tokens_are_invalid_expired_is_expired_accepted_is_consumed(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-deliv-inv-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-inv-deliv-inv-01');

        $this->assertNonAvailableState(
            '/invitations/'.str_repeat('a', 64),
            'invalid',
            'TEAM-INVITATIONS-DELIVERY-INVALID-VIEW-01-UNKNOWN'
        );

        $targetEmail = 'target-inv-deliv-inv-01@example.test';
        $rawToken = $this->storeInvitationAndCaptureRawToken($owner, $workspaceId, $targetEmail);
        $invitationId = (int) DB::table('team_invitations')->where('workspace_id', $workspaceId)->where('email', $targetEmail)->value('id');
        $strangerUser = $this->verifiedUser('Can Öztürk', 'can-inv-deliv-inv-01@example.test');

        $this->assertNonAvailableState(
            "/invitations/{$rawToken}",
            'invalid',
            'TEAM-INVITATIONS-DELIVERY-INVALID-VIEW-01-MISMATCH',
            $strangerUser
        );

        DB::table('team_invitations')->where('id', $invitationId)->update(['expires_at' => now()->subDay()]);
        $this->assertNonAvailableState("/invitations/{$rawToken}", 'expired', 'TEAM-INVITATIONS-DELIVERY-INVALID-VIEW-01-EXPIRED');
        DB::table('team_invitations')->where('id', $invitationId)->update(['expires_at' => now()->addDays(7)]);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())->deleteJson($this->cancelUri($workspaceId, $invitationId))->assertStatus(204);
        $this->assertNonAvailableState("/invitations/{$rawToken}", 'invalid', 'TEAM-INVITATIONS-DELIVERY-INVALID-VIEW-01-CANCELLED');

        // Accepted token: reinvite fresh, accept, then re-check GET.
        $lastMail = null;
        Mail::assertSent(TeamInvitationMail::class, function (TeamInvitationMail $m) use (&$lastMail): bool {
            $lastMail = $m;

            return true;
        });
        $secondRawToken = $this->storeInvitationAndCaptureRawToken($owner, $workspaceId, $targetEmail, $lastMail);

        $accepter = $this->verifiedUser('Target Kullanıcı', $targetEmail);
        $this->actingAs($accepter)->withHeaders($this->jsonHeaders())
            ->postJson("/api/invitations/accept/{$secondRawToken}")
            ->assertStatus(204);

        $this->assertNonAvailableState("/invitations/{$secondRawToken}", 'consumed', 'TEAM-INVITATIONS-DELIVERY-INVALID-VIEW-01-ACCEPTED');
    }

    // --- TEAM-INVITATIONS-DELIVERY-LOGIN-REDIRECT-01 -------------------------

    public function test_login_view_with_local_redirect_param_is_saved_and_returned_after_successful_login(): void
    {
        $user = $this->verifiedUser('Login Redirect Kullanıcı', 'login-redirect-inv-deliv-01@example.test');
        DB::table('password_reset_tokens')->truncate();

        $intendedPath = '/invitations/'.str_repeat('b', 64);

        $htmlLoginPage = $this->get('/login?redirect='.urlencode($intendedPath));
        $htmlLoginPage->assertStatus(200, 'TEAM-INVITATIONS-DELIVERY-LOGIN-REDIRECT-01: /login?redirect=... 200 dönmeli.');
        self::assertSame(
            $intendedPath,
            session('url.intended'),
            'TEAM-INVITATIONS-DELIVERY-LOGIN-REDIRECT-01: server session intended path güvenli local yolu kaydetmeli.'
        );

        $loginResponse = $this->withHeaders($this->jsonHeaders())->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $loginResponse->assertStatus(200, 'TEAM-INVITATIONS-DELIVERY-LOGIN-REDIRECT-01: başarılı login 200 dönmeli.');
        self::assertSame(
            $intendedPath,
            $loginResponse->json('redirect'),
            'TEAM-INVITATIONS-DELIVERY-LOGIN-REDIRECT-01: login JSON yanıtı redirect olarak aynı local pathi vermeli.'
        );
    }

    // --- TEAM-INVITATIONS-DELIVERY-LOGIN-REDIRECT-UNSAFE-01 ------------------

    public function test_protocol_relative_or_external_redirect_is_never_stored_and_login_falls_back_to_app(): void
    {
        $user = $this->verifiedUser('Login Unsafe Redirect Kullanıcı', 'login-unsafe-inv-deliv-01@example.test');
        DB::table('password_reset_tokens')->truncate();

        foreach (['//evil.example.test/phish', 'https://evil.example.test/phish', 'http://evil.example.test'] as $unsafeRedirect) {
            $this->get('/login?redirect='.urlencode($unsafeRedirect));

            self::assertNotSame(
                $unsafeRedirect,
                session('url.intended'),
                sprintf('TEAM-INVITATIONS-DELIVERY-LOGIN-REDIRECT-UNSAFE-01: "%s" asla sessiona alınmamalı.', $unsafeRedirect)
            );

            $loginResponse = $this->withHeaders($this->jsonHeaders())->postJson('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

            $loginResponse->assertStatus(200);
            self::assertSame(
                '/app',
                $loginResponse->json('redirect'),
                sprintf('TEAM-INVITATIONS-DELIVERY-LOGIN-REDIRECT-UNSAFE-01: unsafe redirect "%s" için login /app fallback vermeli.', $unsafeRedirect)
            );

            $this->post('/logout');
        }
    }

    // --- TEAM-INVITATIONS-DELIVERY-MATCH-VIEW-HTML-01 ------------------------

    public function test_matching_verified_session_html_get_invitation_returns_available_auth_shell_with_real_data(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-inv-deliv-match-html-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Lale Restoranları', 'lale-inv-deliv-match-html-01');

        $invitee = $this->verifiedUser('Elif Kaya', 'elif-inv-deliv-match-html-01@example.test');

        $rawToken = $this->storeInvitationAndCaptureRawToken($owner, $workspaceId, 'elif-inv-deliv-match-html-01@example.test');

        $htmlResponse = $this->actingAs($invitee)->withHeaders(['Accept' => 'text/html'])
            ->get("/invitations/{$rawToken}");

        $htmlResponse->assertStatus(200, 'TEAM-INVITATIONS-DELIVERY-MATCH-VIEW-HTML-01: eşleşen doğrulanmış oturum HTML GET 200 dönmeli.');
        $htmlResponse->assertSee('data-auth-view="invitation-accept"', false);
        $htmlResponse->assertSee('data-invitation-status="available"', false);
        $htmlResponse->assertSee('data-authenticated="true"', false);
        $htmlResponse->assertSee('data-workspace-name="Lale Restoranları"', false);
        $htmlResponse->assertSee('data-invited-email="elif-inv-deliv-match-html-01@example.test"', false);
        $htmlResponse->assertSee('data-role="editor"', false);
        $htmlResponse->assertSee(
            'data-accept-url="/api/invitations/accept/'.$rawToken.'"',
            false
        );
    }
}
