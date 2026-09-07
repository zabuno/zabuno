<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Domain\Support\SupportReference;
use App\Mail\ContactMessageReceived;
use App\Mail\SupportRequestAcknowledged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * FF-201 RED — destek kanalı, kamu tarafı (`docs/125`, `docs/107` Faz 1.6).
 *
 * MÜŞTERİ SORUNU. Kadıköy'deki kebapçı iletişim formuna "menüm görünmüyor"
 * yazıyor; mesaj tabloya düşüyor ve sahibe e-posta gidiyor. Ama gönderene
 * HİÇBİR ŞEY dönmüyor: referans numarası yok, alındı bildirimi yok, yanıt
 * taahhüdü yok. Ertesi gün aradığında "hangi mesaj?" sorusuna cevabı yok.
 *
 * KARAR: kamu formu artık `support_requests` tablosuna yazar; her satır
 * okunur bir referans (`ZB-XXXXX`) alır; gönderene referanslı bir alındı
 * e-postası çıkar ve sonucu satıra yazılır; sahibe giden bildirim de
 * referansı taşır. `contact_messages` SİLİNMEZ ama artık yazılmaz.
 *
 * Requirement IDs: SUPPORT-PUBLIC-PERSISTED-01, SUPPORT-REFERENCE-FORMAT-01,
 * SUPPORT-PUBLIC-SCREEN-REFERENCE-01, SUPPORT-ACK-SENT-01,
 * SUPPORT-ACK-FAILURE-KEPT-01, SUPPORT-OWNER-NOTIFIED-WITH-REFERENCE-01,
 * SUPPORT-OWNER-NOTIFY-OFF-01, SUPPORT-HONEYPOT-01, SUPPORT-LEGACY-TABLE-01.
 */
final class PublicSupportRequestTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function message(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Hüseyin',
            'email' => 'huseyin@example.com',
            'message' => 'Menüm misafirlere görünmüyor, karekodu okutunca boş sayfa açılıyor.',
        ], $overrides);
    }

    // --- SUPPORT-PUBLIC-PERSISTED-01 / SUPPORT-REFERENCE-FORMAT-01 ----------

    public function test_a_public_message_becomes_a_support_request_with_a_readable_reference(): void
    {
        Mail::fake();

        $response = $this->post('/contact', $this->message());

        $response->assertRedirect('/contact');
        $response->assertSessionHas('contact.sent');
        $response->assertSessionHas('contact.reference');

        $row = DB::table('support_requests')->first();

        self::assertNotNull($row, 'SUPPORT-PUBLIC-PERSISTED-01: talep kaybolmamalı.');
        self::assertSame('Hüseyin', (string) $row->name);
        self::assertSame('huseyin@example.com', (string) $row->email);
        self::assertStringContainsString('boş sayfa', (string) $row->message);
        self::assertSame('public_contact', (string) $row->channel);
        self::assertSame('received', (string) $row->status);
        self::assertNull($row->workspace_id, 'Kamu formu bir çalışma alanına bağlanmaz.');
        self::assertNull($row->user_id);
        self::assertNotNull($row->received_at);
        self::assertNull($row->first_response_at);

        /*
            REFERANS OKUNUR VE TELEFONDA SÖYLENEBİLİR: `ZB-` öneki ve beş
            karakter; 0/O ve 1/I/L gibi birbirine karışan karakterler
            alfabede YOK. Bir müşteri destek hattında "sıfır mı O mu?"
            diye sormamalı.
        */
        self::assertMatchesRegularExpression(
            SupportReference::PATTERN,
            (string) $row->reference,
            'SUPPORT-REFERENCE-FORMAT-01: referans biçimi ZB-XXXXX olmalı.'
        );
        self::assertSame($row->reference, session('contact.reference'));

        // Kamu formu konu alanı taşımıyor; konu mesajın ilk satırından
        // türetilir ki sahibin listesinde boş bir sütun durmasın.
        self::assertNotSame('', trim((string) $row->subject));
    }

    // --- SUPPORT-LEGACY-TABLE-01 ------------------------------------------

    public function test_the_legacy_contact_table_is_kept_but_no_longer_written(): void
    {
        Mail::fake();

        $this->post('/contact', $this->message())->assertRedirect();

        // Tablo DURUR (geri döndürülebilirlik) ama artık yeni satır almaz.
        self::assertTrue(
            Schema::hasTable('contact_messages'),
            'SUPPORT-LEGACY-TABLE-01: eski tablo silinmez.'
        );
        self::assertSame(0, DB::table('contact_messages')->count());
        self::assertSame(1, DB::table('support_requests')->count());
    }

    // --- SUPPORT-PUBLIC-SCREEN-REFERENCE-01 -------------------------------

    public function test_the_confirmation_screen_shows_the_reference(): void
    {
        Mail::fake();

        $confirmed = $this->followingRedirects()->post('/contact', $this->message());
        $reference = (string) DB::table('support_requests')->value('reference');

        $confirmed->assertOk();
        self::assertStringContainsString(
            $reference,
            (string) $confirmed->getContent(),
            'SUPPORT-PUBLIC-SCREEN-REFERENCE-01: referans ekranda görünmeli; e-posta çıkmasa bile elinde kalsın.'
        );
    }

    // --- SUPPORT-ACK-SENT-01 ----------------------------------------------

    public function test_the_sender_receives_an_acknowledgement_that_carries_the_reference(): void
    {
        /*
            Sürücü `array` (phpunit.xml): dışarı giden bir taşıyıcı VAR
            sayılır. `mailgun` seçilseydi `render()` gerçek taşıyıcıyı
            kurmaya çalışır ve kimlik yokken çökerdi — okunan şey mesajın
            metni, taşıyıcının kendisi değil.
        */
        Mail::fake();

        $this->post('/contact', $this->message())->assertRedirect();

        $row = DB::table('support_requests')->first();
        $reference = (string) $row->reference;

        Mail::assertSent(SupportRequestAcknowledged::class, function (SupportRequestAcknowledged $mail) use ($reference): bool {
            $rendered = $mail->render();

            return $mail->hasTo('huseyin@example.com')
                && str_contains($rendered, $reference)
                && str_contains((string) $mail->envelope()->subject, $reference);
        });

        self::assertNotNull($row->acknowledged_at, 'SUPPORT-ACK-SENT-01: alındı bildirimi kayda geçmeli.');
        self::assertNull($row->acknowledgement_failure);
    }

    // --- SUPPORT-ACK-FAILURE-KEPT-01 --------------------------------------

    public function test_a_failed_acknowledgement_never_loses_the_request(): void
    {
        config(['mail.default' => 'mailgun', 'contact.notify' => null]);

        // Sağlayıcı cevap vermiyor.
        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException('mailgun ulaşılamıyor'));

        $response = $this->post('/contact', $this->message());

        // Ziyaretçi bunu GÖRMEZ: referansı ekranda zaten aldı.
        $response->assertRedirect();
        $response->assertSessionHas('contact.reference');

        $row = DB::table('support_requests')->first();

        self::assertNotNull($row, 'SUPPORT-ACK-FAILURE-KEPT-01: talep durmalı.');
        self::assertNull($row->acknowledged_at);
        self::assertNotEmpty(
            $row->acknowledgement_failure,
            'Sebep kayda geçmeli; yoksa "hiç denenmedi" ile "denendi ve düştü" ayırt edilemez.'
        );
    }

    // --- SUPPORT-OWNER-NOTIFIED-WITH-REFERENCE-01 -------------------------

    public function test_the_owner_notification_carries_the_same_reference(): void
    {
        Mail::fake();
        config(['contact.notify' => 'destek@zabuno.com']);

        $this->post('/contact', $this->message())->assertRedirect();

        $row = DB::table('support_requests')->first();
        $reference = (string) $row->reference;

        Mail::assertSent(ContactMessageReceived::class, function (ContactMessageReceived $mail) use ($reference): bool {
            return $mail->hasTo('destek@zabuno.com')
                && str_contains((string) $mail->envelope()->subject, $reference)
                && str_contains($mail->render(), $reference);
        });

        self::assertNotNull($row->notified_at, 'SUPPORT-OWNER-NOTIFIED-WITH-REFERENCE-01: sahibe bildirim kayda geçmeli.');
        self::assertNull($row->notification_failure);
    }

    // --- SUPPORT-OWNER-NOTIFY-OFF-01 --------------------------------------

    public function test_without_an_owner_address_no_owner_notification_is_pretended(): void
    {
        Mail::fake();
        config(['contact.notify' => null]);

        $this->post('/contact', $this->message())->assertRedirect();

        Mail::assertNotSent(ContactMessageReceived::class);
        // Gönderene alındı yine çıkar: onun adresi biliniyor.
        Mail::assertSent(SupportRequestAcknowledged::class);

        $row = DB::table('support_requests')->first();

        self::assertNotNull($row);
        // Sahibe damga YOK: adres yokken damga atmak, gelmeyen bir e-postayı
        // bekletirdi (`docs/93`).
        self::assertNull($row->notified_at);
        self::assertNull($row->notification_failure);
    }

    // --- SUPPORT-NO-TRANSPORT-IS-NOT-SENT-01 ------------------------------

    public function test_without_an_outbound_transport_no_acknowledgement_is_pretended(): void
    {
        /*
            `log` = kimlik hiçbir kaynaktan gelmemiş (dağıtım sözleşmesi).
            Günlüğe yazılan e-posta kimseye ulaşmaz; "gönderildi" damgası
            basmak, sahibin gelen kutusu için reddettiğimiz yalanın aynısı
            olurdu. Talep yine durur, referans yine ekrandadır.
        */
        Mail::fake();
        config(['mail.default' => 'log', 'contact.notify' => 'destek@zabuno.com']);

        $response = $this->post('/contact', $this->message());

        $response->assertRedirect();
        $response->assertSessionHas('contact.reference');

        Mail::assertNothingSent();

        $row = DB::table('support_requests')->first();

        self::assertNotNull($row);
        self::assertNull($row->acknowledged_at);
        self::assertNull($row->acknowledgement_failure, 'Denenmeyen gönderim bir arıza değildir; sebep yazılmaz.');
        self::assertNull($row->notified_at);
        self::assertNull($row->notification_failure);
    }

    // --- SUPPORT-HONEYPOT-01 ----------------------------------------------

    public function test_a_bot_that_fills_the_hidden_field_is_dropped_silently(): void
    {
        Mail::fake();

        $response = $this->post('/contact', $this->message(['website' => 'https://spam.example']));

        // Ret SESSİZDİR ve başarı gibi görünür — ama referans da yoktur:
        // olmayan bir kaydın numarası uydurulmaz.
        $response->assertRedirect();
        $response->assertSessionHas('contact.sent');
        $response->assertSessionMissing('contact.reference');

        self::assertSame(0, DB::table('support_requests')->count());
        Mail::assertNothingSent();
    }

    public function test_validation_still_refuses_a_broken_address_and_an_empty_message(): void
    {
        $this->post('/contact', $this->message(['email' => 'bu-bir-adres-degil']))
            ->assertSessionHasErrors('email');
        $this->post('/contact', $this->message(['message' => '   ']))
            ->assertSessionHasErrors('message');

        self::assertSame(0, DB::table('support_requests')->count());
    }
}
