<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Application\Mail\Port\MailTransportSelectorPort;
use App\Domain\Platform\PlatformRole;
use App\Mail\SupportRequestReplied;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * FF-201 RED — süperadmin destek uçları (`docs/125` §5).
 *
 * EKRAN BU PAKETTE YOK: `PlatformApp.tsx` başka bir pakette değişiyor.
 * Uçlar var ki ekran geldiğinde sunucu tarafı hazır olsun ve durum
 * geçişinin kuralı (ilk yanıt zamanı yalnız bir kez damgalanır) şimdiden
 * kilitlensin.
 *
 * Requirement IDs: SUPPORT-ADMIN-LIST-01, SUPPORT-ADMIN-SUPERADMIN-ONLY-01,
 * SUPPORT-ADMIN-STATUS-01, SUPPORT-ADMIN-FIRST-RESPONSE-ONCE-01,
 * SUPPORT-ADMIN-STATUS-VALIDATED-01.
 *
 * SUPPORT-REPLY-01 RED — süperadmin kuyruk satırından düz metin cevap yollar
 * (`docs/107` Faz 1.6'nın tek uygulanabilir boşluğu).
 *
 * BU UÇ EXACTLY-ONCE DEĞİLDİR. Sunucu tarafında yinelenen gönderimi eleyen
 * bir anahtar bu pakette YOK; ölçülen tek şey, GERÇEKTEN dışarı çıkmış bir
 * cevaptan sonra damganın bir kez atılmasıdır. Gövde hiçbir yerde kalıcı
 * saklanmaz: yeni tablo, yeni sütun, yeni migration yoktur.
 *
 * Requirement IDs: SUPPORT-REPLY-SENT-01, SUPPORT-REPLY-NO-TRANSPORT-01,
 * SUPPORT-REPLY-RETRY-01, SUPPORT-REPLY-GUARDED-01.
 */
final class PlatformSupportRequestAdminTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->getKey(),
            'role' => PlatformRole::SuperAdmin->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function seedPublicRequest(string $subject, string $status = 'received'): int
    {
        return (int) DB::table('support_requests')->insertGetId([
            'reference' => 'ZB-'.strtoupper(substr(str_shuffle('23456789ABCDEFGHJKMNPQRSTUVWXYZ'), 0, 5)),
            'workspace_id' => null,
            'user_id' => null,
            'name' => 'Hüseyin',
            'email' => 'huseyin@example.com',
            'subject' => $subject,
            'message' => 'Menüm görünmüyor.',
            'channel' => 'public_contact',
            'status' => $status,
            'locale' => 'tr',
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // --- SUPPORT-ADMIN-SUPERADMIN-ONLY-01 ---------------------------------

    public function test_a_verified_user_without_the_platform_role_gets_an_enumeration_safe_404(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $id = $this->seedPublicRequest('Menüm görünmüyor');

        $this->actingAs($user)->withHeaders($this->jsonHeaders())
            ->getJson('/api/admin/support-requests')
            ->assertStatus(404);

        $this->actingAs($user)->withHeaders($this->jsonHeaders())
            ->putJson("/api/admin/support-requests/{$id}/status", ['status' => 'answered'])
            ->assertStatus(404);

        self::assertSame('received', DB::table('support_requests')->where('id', $id)->value('status'));
    }

    // --- SUPPORT-ADMIN-LIST-01 --------------------------------------------

    public function test_the_platform_list_shows_every_channel_and_filters_by_status(): void
    {
        Mail::fake();

        $admin = $this->superAdmin();
        $this->seedPublicRequest('Kamu talebi');
        $closed = $this->seedPublicRequest('Kapanmış', 'closed');

        $response = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->getJson('/api/admin/support-requests');

        $response->assertOk();
        self::assertCount(2, $response->json());
        self::assertSame(
            ['id', 'reference', 'workspace_id', 'name', 'email', 'subject', 'message', 'channel', 'status', 'received_at', 'first_response_at', 'acknowledgement'],
            array_keys($response->json('0')),
        );

        $filtered = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->getJson('/api/admin/support-requests?status=closed');

        $filtered->assertOk();
        self::assertCount(1, $filtered->json());
        self::assertSame($closed, $filtered->json('0.id'));
    }

    // --- SUPPORT-ADMIN-STATUS-01 / SUPPORT-ADMIN-FIRST-RESPONSE-ONCE-01 ---

    public function test_answering_stamps_the_first_response_exactly_once(): void
    {
        $admin = $this->superAdmin();
        $id = $this->seedPublicRequest('Menüm görünmüyor');

        $answered = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson("/api/admin/support-requests/{$id}/status", ['status' => 'answered']);

        $answered->assertOk();
        self::assertSame('answered', $answered->json('status'));

        $firstResponseAt = DB::table('support_requests')->where('id', $id)->value('first_response_at');
        self::assertNotNull($firstResponseAt, 'SUPPORT-ADMIN-FIRST-RESPONSE-ONCE-01: ilk yanıt damgalanmalı.');

        /*
            İLK YANIT BİR KEZ DAMGALANIR. Talep kapanıp yeniden açılsa ve
            tekrar cevaplansa bile ilk yanıt zamanı değişmez — yoksa
            "kaç saatte cevap verdik" ölçümü her geçişte sıfırlanırdı.
        */
        $this->travel(2)->hours();

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson("/api/admin/support-requests/{$id}/status", ['status' => 'closed'])
            ->assertOk();
        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson("/api/admin/support-requests/{$id}/status", ['status' => 'answered'])
            ->assertOk();

        self::assertSame(
            $firstResponseAt,
            DB::table('support_requests')->where('id', $id)->value('first_response_at'),
        );

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson('/api/admin/support-requests/999999/status', ['status' => 'answered'])
            ->assertStatus(404);
    }

    // --- SUPPORT-ADMIN-STATUS-VALIDATED-01 --------------------------------

    public function test_an_unknown_status_is_refused(): void
    {
        $admin = $this->superAdmin();
        $id = $this->seedPublicRequest('Menüm görünmüyor');

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson("/api/admin/support-requests/{$id}/status", ['status' => 'deleted'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        self::assertSame('received', DB::table('support_requests')->where('id', $id)->value('status'));
    }

    // --- SUPPORT-REPLY-01 -------------------------------------------------

    private const REPLY_URI = '/api/admin/support-requests/%d/reply';

    private const REPLY_BODY = 'Menü yayınlanmamış görünüyor; Menüler ekranından Yayınla düğmesine basın.';

    /**
     * Seçici, hangi taşıyıcının seçildiğini ispatlanabilir kılar
     * (`PasswordResetDeliveryTest` deseni). `log`, deponun dağıtım
     * sözleşmesinde "dışarı giden taşıyıcı yok" demektir.
     */
    private function selectMailer(string $name): void
    {
        $this->app->bind(MailTransportSelectorPort::class, fn (): MailTransportSelectorPort => new class($name) implements MailTransportSelectorPort
        {
            public function __construct(private readonly string $name) {}

            public function select(): string
            {
                return $this->name;
            }
        });
    }

    /** @return list<object> */
    private function replyAudits(): array
    {
        return DB::table('platform_audits')->where('scope', 'support.reply')->orderBy('id')->get()->all();
    }

    /**
     * SUPPORT-REPLY-SENT-01.
     *
     * SAHİBİN YOLCULUĞU: Hüseyin "menüm görünmüyor" yazdı. Süperadmin bugün
     * kuyruğu okuyup uygulamadan ÇIKIYOR, kendi posta programını açıyor,
     * adresi elle kopyalıyor ve cevabı orada yazıyor — sonra dönüp "Mark
     * answered"a basıyor. İki ayrı yerde yapılan bu iş, cevabın gidip
     * gitmediğini kimsenin bilmediği tek nokta. Bu senaryo o iki adımı tek
     * adıma indiriyor: cevap GERÇEKTEN dışarı çıkarsa —ve yalnız o zaman—
     * satır `answered` olur ve ilk yanıt damgası düşer.
     */
    public function test_a_superadmin_reply_that_really_leaves_the_building_answers_the_row_and_stamps_it_once(): void
    {
        Mail::fake();
        Config::set('mail.default', 'array');
        $this->selectMailer('array');

        $admin = $this->superAdmin();
        $id = $this->seedPublicRequest('Menüm görünmüyor');
        $reference = (string) DB::table('support_requests')->where('id', $id)->value('reference');

        $response = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson(sprintf(self::REPLY_URI, $id), ['body' => self::REPLY_BODY]);

        $response->assertOk();
        self::assertSame('answered', $response->json('status'));

        Mail::assertSent(SupportRequestReplied::class, function (SupportRequestReplied $mail) use ($reference): bool {
            return $mail->hasTo('huseyin@example.com')
                && $mail->reference === $reference
                && $mail->body === self::REPLY_BODY;
        });

        $row = DB::table('support_requests')->where('id', $id)->first();
        self::assertSame('answered', $row->status);
        self::assertNotNull(
            $row->first_response_at,
            'SUPPORT-REPLY-SENT-01: gerçekten çıkan bir cevap ilk yanıt damgasını düşürmeli.',
        );

        /*
            DENETİM AKTÖRÜ VE REFERANSI TAŞIR, CEVABI TAŞIMAZ. Kimin
            cevapladığı ve hangi talebe cevapladığı bir yönetişim olgusudur;
            cevabın METNİ müşterinin cümlesidir ve denetim izi onu saklamak
            için yapılmadı. Gövde buraya sızarsa, "kalıcı cevap gövdesi yok"
            sözü sessizce bozulmuş olur.
        */
        $audits = $this->replyAudits();
        self::assertCount(1, $audits);
        self::assertSame('reply_sent', $audits[0]->action);
        self::assertSame($admin->id, (int) $audits[0]->actor_user_id);
        self::assertStringContainsString($reference, (string) $audits[0]->subject);
        self::assertStringNotContainsString(
            self::REPLY_BODY,
            (string) $audits[0]->subject.(string) $audits[0]->details,
            'SUPPORT-REPLY-SENT-01: cevap gövdesi denetim izine yazılmaz.',
        );
    }

    /**
     * SUPPORT-REPLY-NO-TRANSPORT-01.
     *
     * GÜNLÜĞE YAZILAN E-POSTA KİMSEYE ULAŞMAZ. Sürücü `log` ise gönderim
     * hiç DENENMEZ; uç 409 ile sebebini söyler ve satır olduğu yerde,
     * `received` olarak, damgasız kalır. Yoksa süperadmin "cevapladım"
     * sanır, Hüseyin bekler ve ölçüm cevap verilmiş gibi görünür.
     */
    public function test_a_reply_with_no_outbound_transport_is_refused_and_changes_nothing(): void
    {
        Mail::fake();
        $this->selectMailer('log');

        $admin = $this->superAdmin();
        $id = $this->seedPublicRequest('Menüm görünmüyor');

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson(sprintf(self::REPLY_URI, $id), ['body' => self::REPLY_BODY])
            ->assertStatus(409)
            ->assertJson(['reason' => 'no_outbound_transport']);

        Mail::assertNothingSent();

        $row = DB::table('support_requests')->where('id', $id)->first();
        self::assertSame('received', $row->status);
        self::assertNull($row->first_response_at);

        $audits = $this->replyAudits();
        self::assertCount(1, $audits);
        self::assertSame('reply_failed', $audits[0]->action);
        self::assertStringNotContainsString(
            self::REPLY_BODY,
            (string) $audits[0]->subject.(string) $audits[0]->details,
        );
    }

    /**
     * SUPPORT-REPLY-RETRY-01.
     *
     * ARIZA BİR SON DEĞİL, BİR DURAKTIR. Taşıyıcı seçimi patlarsa (kasadaki
     * anahtar taşınamıyor) süperadmin ham sağlayıcı cümlesini —içinde bir
     * sır olabilir— görmez; sanitize edilmiş bir 409 görür, satır
     * değişmemiştir ve AYNI gövdeyi yeniden gönderebilir. İkinci deneme
     * çıkarsa damga bir kez düşer.
     */
    public function test_a_failed_reply_is_sanitized_leaves_the_row_untouched_and_the_same_body_can_be_retried(): void
    {
        Mail::fake();
        Config::set('mail.default', 'array');

        $secret = 'mailgun-key-SECRET-9f3a';
        $this->app->bind(MailTransportSelectorPort::class, fn (): MailTransportSelectorPort => new class($secret) implements MailTransportSelectorPort
        {
            public function __construct(private readonly string $secret) {}

            public function select(): string
            {
                throw new RuntimeException('SMTP auth failed with '.$this->secret);
            }
        });

        $admin = $this->superAdmin();
        $id = $this->seedPublicRequest('Menüm görünmüyor');

        $failed = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson(sprintf(self::REPLY_URI, $id), ['body' => self::REPLY_BODY]);

        $failed->assertStatus(409);
        self::assertStringNotContainsString(
            $secret,
            $failed->getContent(),
            'SUPPORT-REPLY-RETRY-01: ham sağlayıcı sebebi tarayıcıya çıkmaz.',
        );

        $row = DB::table('support_requests')->where('id', $id)->first();
        self::assertSame('received', $row->status);
        self::assertNull($row->first_response_at);

        // Taşıyıcı düzeldi; süperadmin AYNI gövdeyi yeniden gönderir.
        $this->selectMailer('array');

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson(sprintf(self::REPLY_URI, $id), ['body' => self::REPLY_BODY])
            ->assertOk();

        Mail::assertSent(SupportRequestReplied::class, 1);

        $retried = DB::table('support_requests')->where('id', $id)->first();
        self::assertSame('answered', $retried->status);
        self::assertNotNull($retried->first_response_at);

        $audits = $this->replyAudits();
        self::assertCount(2, $audits);
        self::assertSame(['reply_failed', 'reply_sent'], [$audits[0]->action, $audits[1]->action]);
        foreach ($audits as $audit) {
            self::assertStringNotContainsString(
                $secret,
                (string) $audit->subject.(string) $audit->details,
                'SUPPORT-REPLY-RETRY-01: sır denetim izine de yazılmaz.',
            );
        }
    }

    /**
     * SUPPORT-REPLY-GUARDED-01.
     *
     * UCUN ÜÇ KAPISI, mevcut süperadmin uçlarıyla aynı dilde: rolü olmayan
     * için talep VAR OLMAZ (404, sayım yapılamaz), boş gövde bir cevap
     * değildir (422) ve olmayan bir talebe cevap yollanmaz (404). Üçünde de
     * tek bir e-posta çıkmaz.
     */
    public function test_the_reply_endpoint_is_superadmin_only_refuses_a_blank_body_and_404s_a_missing_request(): void
    {
        Mail::fake();
        Config::set('mail.default', 'array');
        $this->selectMailer('array');

        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $id = $this->seedPublicRequest('Menüm görünmüyor');

        $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->postJson(sprintf(self::REPLY_URI, $id), ['body' => self::REPLY_BODY])
            ->assertStatus(404);

        $admin = $this->superAdmin();

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson(sprintf(self::REPLY_URI, $id), ['body' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson(sprintf(self::REPLY_URI, 999999), ['body' => self::REPLY_BODY])
            ->assertStatus(404);

        Mail::assertNothingSent();

        $row = DB::table('support_requests')->where('id', $id)->first();
        self::assertSame('received', $row->status);
        self::assertNull($row->first_response_at);
    }
}
