<?php

declare(strict_types=1);

namespace Tests\Feature\Team;

use App\Mail\TeamInvitationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * P0-06 RED — davet YENİDEN GÖNDERİLEBİLİR (`docs/110` P0-06, kabul ölçütü 3).
 *
 * Doğrulama e-postası için Fortify'ın yeniden gönderme ucu vardı; davet için
 * hiçbir yol yoktu. Taşıyıcı bir kez düştüğünde ya da e-posta spam'e
 * takıldığında sahibin elinde tek bir hamle kalıyordu: daveti İPTAL edip
 * yeniden kurmak. Yani ekibini kurabilmek için önce onu bozması gerekiyordu.
 *
 * Sınırlar mevcut kardeş uçlardan OKUNUR, uydurulmaz: aynı yetki kuralı
 * (`Permission::WorkspaceManage`), aynı plan kapısı (CORE-04, 402), aynı
 * `throttle:5,1`, aynı enumeration-safe 404.
 *
 * Requirement IDs: TEAM-INVITATION-RESEND-01, TEAM-INVITATION-RESEND-ROTATES-02,
 * TEAM-INVITATION-RESEND-PENDING-ONLY-03, TEAM-INVITATION-RESEND-PERMISSION-04,
 * TEAM-INVITATION-RESEND-TENANT-05, TEAM-INVITATION-RESEND-THROTTLE-06,
 * TEAM-INVITATION-RESEND-FAILURE-07, TEAM-INVITATION-RESEND-AUTH-08.
 */
final class ResendTeamInvitationTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    /** @return array<string, string> */
    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function verifiedUser(string $email, string $name = 'Ayşe Yılmaz'): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
        ]);
    }

    private function workspaceOwnedBy(User $owner, string $slug): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
            'slug' => $slug,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->addMember($workspaceId, $owner, 'owner');
        $this->grantEntitlements($workspaceId);

        return $workspaceId;
    }

    private function addMember(int $workspaceId, User $user, string $role): void
    {
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedPendingInvitation(int $workspaceId, int $invitedBy, string $email, string $rawToken): int
    {
        return (int) DB::table('team_invitations')->insertGetId([
            'workspace_id' => $workspaceId,
            'email' => $email,
            'role' => 'editor',
            'status' => 'pending',
            'invited_by' => $invitedBy,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resendUri(int $workspaceId, int $invitationId): string
    {
        return "/api/workspaces/{$workspaceId}/team/invitations/{$invitationId}/resend";
    }

    private function rawToken(string $seed): string
    {
        return str_pad(preg_replace('/[^A-Za-z0-9_-]/', '', $seed) ?? '', 64, 'x');
    }

    // --- TEAM-INVITATION-RESEND-01 ----------------------------------------

    public function test_someone_who_can_invite_can_send_a_pending_invitation_again(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('ayse-resend-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-resend-01');
        $invitationId = $this->seedPendingInvitation(
            $workspaceId,
            (int) $owner->getKey(),
            'mehmet-resend-01@example.test',
            $this->rawToken('resend01')
        );

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->resendUri($workspaceId, $invitationId));

        $response->assertStatus(200, 'TEAM-INVITATION-RESEND-01: yeniden gönderme ucu olmalı.');
        self::assertSame(
            ['id', 'email', 'role', 'status', 'delivery'],
            array_keys((array) $response->json()),
            'TEAM-INVITATION-RESEND-01: yanıt kardeş uçlarla aynı satır şeklini taşımalı.'
        );
        self::assertSame($invitationId, $response->json('id'));
        self::assertSame('pending', $response->json('status'));
        self::assertSame('sent', $response->json('delivery'));

        Mail::assertSent(TeamInvitationMail::class, 1);

        $row = DB::table('team_invitations')->where('id', $invitationId)->first();
        self::assertNotNull($row->delivered_at);
        self::assertNull($row->delivery_failure);
    }

    // --- TEAM-INVITATION-RESEND-ROTATES-02 --------------------------------

    public function test_resending_issues_a_new_link_and_kills_the_previous_one(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('ayse-resend-02@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-resend-02');

        $oldRawToken = $this->rawToken('resend02old');
        $invitationId = $this->seedPendingInvitation(
            $workspaceId,
            (int) $owner->getKey(),
            'mehmet-resend-02@example.test',
            $oldRawToken
        );

        $before = DB::table('team_invitations')->where('id', $invitationId)->first();

        $this->travel(2)->days();

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->resendUri($workspaceId, $invitationId))
            ->assertStatus(200);

        $after = DB::table('team_invitations')->where('id', $invitationId)->first();

        /*
            YENİ BAĞLANTI, ESKİSİ ÖLÜR.

            İki geçerli bağlantı bırakmak, iptal edilmiş bir e-postanın
            içindeki bağlantının aylarca çalışması demekti. Depo bu kuralı
            zaten bir kez koymuştu (iptal-sonrası yeniden davet token'ı
            döndürür); yeniden gönderme onunla AYNI kuralı taşır.
        */
        self::assertNotSame(
            $before->token_hash,
            $after->token_hash,
            'TEAM-INVITATION-RESEND-ROTATES-02: token yenilenmeli.'
        );

        /*
            Süre de yenilenir: yedi günü dolmak üzere olan bir davetin yeniden
            gönderilmesi, alıcıya yine yedi gün vermelidir.

            Karşılaştırma DİZE ÜZERİNDEN YAPILMAZ: SQLite ve PostgreSQL aynı
            zaman damgasını farklı biçimlerde döndürür ve iki biçim
            sözlük sırasına göre ters karşılaştırılabilir.
        */
        self::assertTrue(
            Carbon::parse($after->expires_at)->greaterThan(Carbon::parse($before->expires_at)),
            'TEAM-INVITATION-RESEND-ROTATES-02: süre yenilenmeli.'
        );

        // Eski bağlantı artık açılmaz.
        $this->app['auth']->guard('web')->logout();
        $old = $this->withHeaders($this->jsonHeaders())->getJson("/invitations/{$oldRawToken}");
        $old->assertStatus(200);
        self::assertSame('invalid', $old->json('data.state'));

        // Yeni bağlantı postadan çıkar ve açılır.
        $newRawToken = null;
        Mail::assertSent(TeamInvitationMail::class, function (TeamInvitationMail $mail) use (&$newRawToken): bool {
            preg_match('#/invitations/([A-Za-z0-9_-]{64})#', $mail->render(), $matches);
            $newRawToken = $matches[1] ?? null;

            return $newRawToken !== null;
        });

        self::assertNotNull($newRawToken);
        self::assertSame(hash('sha256', $newRawToken), $after->token_hash);
    }

    // --- TEAM-INVITATION-RESEND-PENDING-ONLY-03 ---------------------------

    public function test_an_invitation_that_is_not_pending_can_never_be_resent(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('ayse-resend-03@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-resend-03');

        foreach (['cancelled', 'accepted'] as $index => $status) {
            $invitationId = (int) DB::table('team_invitations')->insertGetId([
                'workspace_id' => $workspaceId,
                'email' => "kapali-{$index}-resend-03@example.test",
                'role' => 'editor',
                'status' => $status,
                'invited_by' => $owner->id,
                'token_hash' => hash('sha256', $this->rawToken('resend03'.$index)),
                'expires_at' => now()->addDays(7),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->actingAs($owner)->withHeaders($this->jsonHeaders())
                ->postJson($this->resendUri($workspaceId, $invitationId))
                ->assertStatus(404, "TEAM-INVITATION-RESEND-PENDING-ONLY-03: `{$status}` davet yeniden gönderilemez.");
        }

        // İptal edilmiş bir daveti yeniden göndermek, onu sessizce diriltmek
        // olurdu: sahip iptal ettiğini biliyor, alıcı geçerli bir bağlantı
        // alıyordu.
        Mail::assertNothingSent();
    }

    // --- TEAM-INVITATION-RESEND-PERMISSION-04 -----------------------------

    public function test_only_a_role_that_may_invite_can_resend(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('ayse-resend-04@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-resend-04');
        $invitationId = $this->seedPendingInvitation(
            $workspaceId,
            (int) $owner->getKey(),
            'mehmet-resend-04@example.test',
            $this->rawToken('resend04')
        );

        // Davet edebilen rol = `Permission::WorkspaceManage` taşıyan rol.
        // Kural burada TEKRAR YAZILMAZ, mevcut uçtan devralınır.
        $manager = $this->verifiedUser('yonetici-resend-04@example.test', 'Yönetici');
        $this->addMember($workspaceId, $manager, 'manager');

        $this->actingAs($manager)->withHeaders($this->jsonHeaders())
            ->postJson($this->resendUri($workspaceId, $invitationId))
            ->assertStatus(200, 'TEAM-INVITATION-RESEND-PERMISSION-04: davet edebilen rol yeniden de gönderebilmeli.');

        foreach (['editor', 'kitchen', 'member'] as $index => $role) {
            $other = $this->verifiedUser("baskasi-{$index}-resend-04@example.test", 'Başkası');
            $this->addMember($workspaceId, $other, $role);

            $this->actingAs($other)->withHeaders($this->jsonHeaders())
                ->postJson($this->resendUri($workspaceId, $invitationId))
                ->assertStatus(404, "TEAM-INVITATION-RESEND-PERMISSION-04: `{$role}` daveti yeniden gönderememeli.");
        }

        Mail::assertSent(TeamInvitationMail::class, 1);
    }

    // --- TEAM-INVITATION-RESEND-TENANT-05 ---------------------------------

    public function test_the_tenant_boundary_is_structural_not_a_screen_rule(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('ayse-resend-05@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-resend-05');
        $invitationId = $this->seedPendingInvitation(
            $workspaceId,
            (int) $owner->getKey(),
            'mehmet-resend-05@example.test',
            $this->rawToken('resend05')
        );

        $stranger = $this->verifiedUser('yabanci-resend-05@example.test', 'Yabancı');
        $strangerWorkspaceId = $this->workspaceOwnedBy($stranger, 'yabanci-resend-05');

        /*
            KİMLİK SATIRIN KENDİSİNDE DEĞİL, İKİLİDE.

            Kendi workspace'inin sahibi, komşunun davet id'sini adres
            çubuğuna yazarak onun ekibine e-posta gönderemez. Sorgu
            `workspace_id` + `id` ikilisiyle eşleşir; yalnız id ile eşleşen
            bir sorgu, yetki kapısı doğru olsa bile kiracı sınırını
            ekrandaki bir kurala indirger.
        */
        $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->postJson($this->resendUri($strangerWorkspaceId, $invitationId))
            ->assertStatus(404);

        Mail::assertNothingSent();
    }

    // --- TEAM-INVITATION-RESEND-THROTTLE-06 -------------------------------

    public function test_resend_is_rate_limited_like_its_sibling_endpoints(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('ayse-resend-06@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-resend-06');
        $invitationId = $this->seedPendingInvitation(
            $workspaceId,
            (int) $owner->getKey(),
            'mehmet-resend-06@example.test',
            $this->rawToken('resend06')
        );

        $last = null;

        for ($i = 0; $i < 6; $i++) {
            $last = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
                ->postJson($this->resendUri($workspaceId, $invitationId));
        }

        // Kardeş uçlar `throttle:5,1` taşıyor. Sınırsız bir yeniden gönderme
        // ucu, ürünü başkasının gelen kutusuna yönelen bir taciz aracına
        // çevirirdi.
        $last->assertStatus(429, 'TEAM-INVITATION-RESEND-THROTTLE-06: kardeş uçlarla aynı sınır geçerli olmalı.');
    }

    // --- TEAM-INVITATION-RESEND-FAILURE-07 --------------------------------

    public function test_a_failed_resend_is_recorded_and_never_claims_success(): void
    {
        $owner = $this->verifiedUser('ayse-resend-07@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-resend-07');
        $invitationId = $this->seedPendingInvitation(
            $workspaceId,
            (int) $owner->getKey(),
            'mehmet-resend-07@example.test',
            $this->rawToken('resend07')
        );

        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException('mailgun ulaşılamıyor'));

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->resendUri($workspaceId, $invitationId));

        $response->assertStatus(200);
        self::assertSame(
            'failed',
            $response->json('delivery'),
            'TEAM-INVITATION-RESEND-FAILURE-07: çıkmayan bir e-posta "gönderildi" diye raporlanamaz.'
        );

        $row = DB::table('team_invitations')->where('id', $invitationId)->first();

        self::assertSame('pending', $row->status, 'Davet ayakta kalmalı: yenilenen bağlantı geçerlidir.');
        self::assertNull($row->delivered_at);
        self::assertNotEmpty($row->delivery_failure);
    }

    // --- TEAM-INVITATION-RESEND-AUTH-08 -----------------------------------

    public function test_a_guest_and_an_unverified_user_are_both_turned_away(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('ayse-resend-08@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-resend-08');
        $invitationId = $this->seedPendingInvitation(
            $workspaceId,
            (int) $owner->getKey(),
            'mehmet-resend-08@example.test',
            $this->rawToken('resend08')
        );

        $this->withHeaders($this->jsonHeaders())
            ->postJson($this->resendUri($workspaceId, $invitationId))
            ->assertStatus(401);

        $unverified = User::factory()->unverified()->create();
        $this->addMember($workspaceId, $unverified, 'owner');

        $this->actingAs($unverified)->withHeaders($this->jsonHeaders())
            ->postJson($this->resendUri($workspaceId, $invitationId))
            ->assertStatus(403);

        Mail::assertNothingSent();
    }
}
