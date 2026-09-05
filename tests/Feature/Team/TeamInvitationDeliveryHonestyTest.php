<?php

declare(strict_types=1);

namespace Tests\Feature\Team;

use App\Application\Mail\Port\MailTransportSelectorPort;
use App\Mail\TeamInvitationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * P0-06 RED — davet gönderimi SESSİZ KALMAZ (`docs/110` P0-06, kabul ölçütü 3).
 *
 * `StoreTeamInvitationController` çıplak bir `Mail::to(...)->send(...)`
 * çağırıyordu. Taşıyıcı patlarsa davet OLUŞUYOR, e-posta GİTMİYOR ve ekranda
 * hiçbir iz kalmıyordu: sahip listede bekleyen daveti görüyor, davet edilen
 * kişi hiçbir şey almıyor ve ikisi de sebebini öğrenemiyordu.
 *
 * Çözüm `ContactDeliveryTest`'in çözdüğü sorunun aynısıdır ve oradan
 * TÜRETİLİR — ikinci bir desen kurulmaz: KAYIT ÖNCE GELİR, GÖNDERİM SONRA.
 * Davet oluşturulmuşsa oluşturulmuş kalır (sahip tekrar denediğinde çift
 * davet üretmeyiz), ama teslimatın olmadığı kayda geçer ve ekranda görünür.
 *
 * Aynı çağrı yolu ikinci bir eksiği de kapatır: gönderim artık kasadan
 * seçilen sürücüyle çıkar. Öncesinde superadmin kasaya Mailgun anahtarını
 * girdiğinde iletişim formu çalışıyor ama DAVET hâlâ `mail.default`
 * (üretimde `log`) üzerinden "gidiyordu".
 *
 * Requirement IDs: TEAM-INVITATION-DELIVERY-RECORDED-01,
 * TEAM-INVITATION-DELIVERY-FAILURE-KEPT-02,
 * TEAM-INVITATION-DELIVERY-NO-LEAK-03,
 * TEAM-INVITATION-DELIVERY-VISIBLE-04,
 * TEAM-INVITATION-DELIVERY-UNKNOWN-05,
 * TEAM-INVITATION-DELIVERY-VAULT-TRANSPORT-06.
 */
final class TeamInvitationDeliveryHonestyTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    /** @return array<string, string> */
    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function verifiedUser(string $email): User
    {
        return User::factory()->create([
            'name' => 'Ayşe Yılmaz',
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

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->grantEntitlements($workspaceId);

        return $workspaceId;
    }

    private function invitationsUri(int $workspaceId): string
    {
        return "/api/workspaces/{$workspaceId}/team/invitations";
    }

    // --- TEAM-INVITATION-DELIVERY-RECORDED-01 -----------------------------

    public function test_a_delivered_invitation_is_recorded_as_handed_to_the_provider(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('ayse-inv-hon-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-inv-hon-01');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), [
                'email' => 'mehmet-inv-hon-01@example.test',
                'role' => 'editor',
            ]);

        $response->assertStatus(201);
        self::assertSame(
            'sent',
            $response->json('delivery'),
            'TEAM-INVITATION-DELIVERY-RECORDED-01: yanıt gönderimin durumunu taşımalı.'
        );

        Mail::assertSent(TeamInvitationMail::class);

        $row = DB::table('team_invitations')->where('workspace_id', $workspaceId)->first();

        self::assertNotNull($row->delivered_at, 'TEAM-INVITATION-DELIVERY-RECORDED-01: gönderim kayda geçmeli.');
        self::assertNull($row->delivery_failure);
    }

    // --- TEAM-INVITATION-DELIVERY-FAILURE-KEPT-02 -------------------------

    public function test_a_failed_delivery_never_loses_the_invitation(): void
    {
        $owner = $this->verifiedUser('ayse-inv-hon-02@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-inv-hon-02');

        // Sağlayıcı cevap vermiyor.
        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException('mailgun ulaşılamıyor'));

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), [
                'email' => 'mehmet-inv-hon-02@example.test',
                'role' => 'editor',
            ]);

        /*
            DAVET OLUŞMUŞSA OLUŞMUŞ KALIR.

            Gönderim düştü diye kaydı geri almak, sahip tekrar denediğinde
            ÇİFT davet üretirdi; üstelik davet edilen kişinin elindeki
            bağlantı da geçersizleşirdi. Asıl işlem bozulmaz — bozulan yalnız
            teslimattır ve o kayda geçer.
        */
        $response->assertStatus(201, 'TEAM-INVITATION-DELIVERY-FAILURE-KEPT-02: davet oluşturulmuş kalmalı.');
        self::assertSame(
            'failed',
            $response->json('delivery'),
            'TEAM-INVITATION-DELIVERY-FAILURE-KEPT-02: "gönderildi" DENMEZ.'
        );

        $row = DB::table('team_invitations')->where('workspace_id', $workspaceId)->first();

        self::assertNotNull($row, 'TEAM-INVITATION-DELIVERY-FAILURE-KEPT-02: davet durmalı.');
        self::assertSame('pending', $row->status);
        self::assertNull($row->delivered_at);
        self::assertNotEmpty(
            $row->delivery_failure,
            'Sebep kayda geçmeli; yoksa "hiç denenmedi" ile "denendi ve düştü" ayırt edilemez.'
        );
    }

    // --- TEAM-INVITATION-DELIVERY-NO-LEAK-03 ------------------------------

    public function test_the_provider_answer_never_reaches_the_screen(): void
    {
        $owner = $this->verifiedUser('ayse-inv-hon-03@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-inv-hon-03');

        /*
            SAĞLAYICININ CÜMLESİ EKRANA ÇIKMAZ.

            Taşıyıcı hataları sıklıkla uç adresini, alan adını, hatta yanıt
            gövdesinin tamamını taşır. Kullanıcıya DURUM söylenir, loga
            AYRINTI yazılır: ekrana düşen bir sağlayıcı cevabı, ürünün
            altyapısını tanıtan ücretsiz bir haritadır.
        */
        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException(
            'Expected response code 250 but got 535 from smtp.gizli-sunucu.example: key=sk-COK-GIZLI'
        ));

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), [
                'email' => 'mehmet-inv-hon-03@example.test',
                'role' => 'editor',
            ]);

        $body = (string) $response->getContent();

        foreach (['sk-COK-GIZLI', 'gizli-sunucu.example', '535', 'smtp'] as $forbidden) {
            self::assertStringNotContainsStringIgnoringCase(
                $forbidden,
                $body,
                "TEAM-INVITATION-DELIVERY-NO-LEAK-03: yanıt \"{$forbidden}\" sızdırıyor."
            );
        }

        // Liste de aynı sınırı taşır: kaydedilen sebep içeride kalır.
        $list = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->invitationsUri($workspaceId));

        self::assertStringNotContainsStringIgnoringCase('gizli-sunucu.example', (string) $list->getContent());
    }

    // --- TEAM-INVITATION-DELIVERY-VISIBLE-04 ------------------------------

    public function test_the_pending_list_says_the_email_did_not_go_out(): void
    {
        $owner = $this->verifiedUser('ayse-inv-hon-04@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-inv-hon-04');

        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException('mailgun ulaşılamıyor'));

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), [
                'email' => 'mehmet-inv-hon-04@example.test',
                'role' => 'editor',
            ])->assertStatus(201);

        $list = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->invitationsUri($workspaceId));

        $list->assertStatus(200);

        $rows = $list->json();

        self::assertCount(1, $rows);
        self::assertSame(
            ['id', 'email', 'role', 'status', 'delivery'],
            array_keys($rows[0]),
            'TEAM-INVITATION-DELIVERY-VISIBLE-04: satır yalnız türetilmiş teslim durumunu ekler — ham sütun değil.'
        );
        self::assertSame('failed', $rows[0]['delivery']);
    }

    // --- TEAM-INVITATION-DELIVERY-UNKNOWN-05 ------------------------------

    public function test_a_row_from_before_delivery_tracking_is_unknown_not_sent(): void
    {
        $owner = $this->verifiedUser('ayse-inv-hon-05@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-inv-hon-05');

        /*
            BİLİNMİYOR, "GÖNDERİLDİ" DEĞİLDİR.

            Bu sütunlar var olmadan önce oluşmuş davetlerin teslim geçmişi
            YOK. Onları "gönderildi" saymak, sahibe hiç yapmadığımız bir işi
            yaptığımızı söylemek olurdu; "gönderilemedi" saymak ise olmayan
            bir arıza uydurmak. Üçüncü hâl gerçeğin kendisidir.
        */
        $invitationId = (int) DB::table('team_invitations')->insertGetId([
            'workspace_id' => $workspaceId,
            'email' => 'eski-inv-hon-05@example.test',
            'role' => 'editor',
            'status' => 'pending',
            'invited_by' => $owner->id,
            'token_hash' => hash('sha256', 'eski-token-inv-hon-05'),
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $list = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->invitationsUri($workspaceId));

        $rows = $list->json();

        self::assertSame($invitationId, $rows[0]['id']);
        self::assertSame('unknown', $rows[0]['delivery']);
    }

    // --- TEAM-INVITATION-DELIVERY-VAULT-TRANSPORT-06 ----------------------

    public function test_the_invitation_leaves_through_the_vault_selected_transport(): void
    {
        Mail::fake();

        $owner = $this->verifiedUser('ayse-inv-hon-06@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-inv-hon-06');

        /*
            KASA > env, DAVET İÇİN DE.

            İletişim formu kasadan seçilen sürücüyü kullanıyordu, davet
            kullanmıyordu. Sonuç: superadmin panelden Mailgun anahtarını
            giriyor, iletişim mesajı çıkıyor, davet hâlâ `log`'a düşüyordu —
            ve ekranda ikisi de aynı görünüyordu.
        */
        $this->app->bind(MailTransportSelectorPort::class, fn (): MailTransportSelectorPort => new class implements MailTransportSelectorPort
        {
            public function select(): string
            {
                return 'kasadan-secilen-surucu';
            }
        });

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->invitationsUri($workspaceId), [
                'email' => 'mehmet-inv-hon-06@example.test',
                'role' => 'editor',
            ])->assertStatus(201);

        // Ad UYDURMA bir addır: varsayılan sürücüyle karışan bir ad
        // (`array`, `log`) seçicinin hiç çağrılmadığı hâlde de geçerdi.
        Mail::assertSent(TeamInvitationMail::class, function (TeamInvitationMail $mail): bool {
            return $mail->mailer === 'kasadan-secilen-surucu';
        });
    }
}
