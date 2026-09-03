<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Mail\ContactMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * P0-06 RED — gelen mesaj gerçekten ULAŞIR (`docs/93`).
 *
 * `docs/88` mesajı SAKLIYORDU ve bu doğru bir başlangıçtı: saklanan bir
 * mesaj kaybolmaz. Ama sahibin onu görmesi için panele bakması gerekiyordu —
 * yani ulaşmış olmuyordu, duruyordu.
 *
 * SAKLAMA ÖNCE GELİR, GÖNDERİM SONRA. Gönderim başarısız olsa bile mesaj
 * durur ve sebebi kayda geçer: sağlayıcı bir gün cevap vermediğinde
 * kaybolan bir talep olmamalı.
 *
 * Requirement IDs: CONTACT-DELIVERED-01, CONTACT-DELIVERY-FAILURE-KEPT-01,
 * CONTACT-DELIVERY-OFF-IS-NOT-AN-ERROR-01, CONTACT-DELIVERY-NO-SECRET-01.
 */
final class ContactDeliveryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function message(): array
    {
        return [
            'name' => 'Hüseyin',
            'email' => 'huseyin@example.com',
            'message' => 'Kadıköy\'de 40 masalık bir restoranım var.',
        ];
    }

    // --- CONTACT-DELIVERED-01 ---------------------------------------------

    public function test_a_message_reaches_the_inbox_when_delivery_is_configured(): void
    {
        Mail::fake();
        config(['mail.default' => 'mailgun', 'contact.notify' => 'destek@zabuno.com']);

        $this->post('/contact', $this->message())->assertRedirect();

        Mail::assertSent(ContactMessageReceived::class, function ($mail): bool {
            // Bildirim SAHİBE gider, gönderene değil: gönderen ekranda zaten
            // teyit aldı ve kum havuzu alanı ona ulaşamaz.
            return $mail->hasTo('destek@zabuno.com');
        });

        $row = DB::table('contact_messages')->first();

        self::assertNotNull($row->delivered_at, 'CONTACT-DELIVERED-01: gönderim kayda geçmeli.');
        self::assertNull($row->delivery_failure);
    }

    // --- CONTACT-DELIVERY-FAILURE-KEPT-01 ---------------------------------

    public function test_a_failed_delivery_never_loses_the_message(): void
    {
        config(['mail.default' => 'mailgun', 'contact.notify' => 'destek@zabuno.com']);

        // Sağlayıcı cevap vermiyor.
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('mailgun ulaşılamıyor'));

        $response = $this->post('/contact', $this->message());

        // Ziyaretçi bunu GÖRMEZ: gönderim bizim iç meselemiz ve onun
        // mesajı kaybolmadı.
        $response->assertRedirect();
        $response->assertSessionHas('contact.sent');

        $row = DB::table('contact_messages')->first();

        self::assertNotNull($row, 'CONTACT-DELIVERY-FAILURE-KEPT-01: mesaj durmalı.');
        self::assertNull($row->delivered_at);
        self::assertNotEmpty(
            $row->delivery_failure,
            'Sebep kayda geçmeli; yoksa "hiç denenmedi" ile "denendi ve düştü" ayırt edilemez.'
        );
    }

    // --- CONTACT-DELIVERY-OFF-IS-NOT-AN-ERROR-01 --------------------------

    public function test_with_no_provider_configured_the_message_is_kept_without_pretending(): void
    {
        Mail::fake();
        config(['mail.default' => 'log', 'contact.notify' => null]);

        $this->post('/contact', $this->message())->assertRedirect();

        Mail::assertNothingSent();

        $row = DB::table('contact_messages')->first();

        self::assertNotNull($row);
        // "Gönderildi" DEMEZ: sağlayıcı yokken damga atmak, sahibin
        // gelmeyen bir e-postayı beklemesine yol açardı.
        self::assertNull($row->delivered_at);
    }

    // --- CONTACT-DELIVERY-NO-SECRET-01 ------------------------------------

    public function test_no_credential_is_written_into_the_repository(): void
    {
        /*
            Anahtar DEPOYA GİRMEZ.

            Yapılandırma onu ortamdan okur; örnek dosyalar yalnız DEĞİŞKEN
            ADINI taşır. Bir anahtarın depoya girmesi, onu gören herkese
            vermek demektir ve geçmişten silmek pratikte imkânsızdır.
        */
        foreach (['config/services.php', 'config/mail.php', '.env.example', '.env.production.example'] as $file) {
            /*
                YORUMLAR ÖNCE DÜŞER.

                Bir yorum sır DEĞİLDİR. Kuralın ilk hâli, kum havuzu alanının
                nasıl göründüğünü AÇIKLAYAN bir cümleyi ihlal sayıyordu — yani
                kısıtı belgelemeyi cezalandırıyordu. Bu depoda aynı ders daha
                önce üç kapıda öğrenildi (`docs/82`, `docs/85`).
            */
            $contents = (string) preg_replace(
                ['#/\*.*?\*/#s', '#^\s*(//|\#).*$#m'],
                '',
                (string) file_get_contents(base_path($file)),
            );

            self::assertDoesNotMatchRegularExpression(
                /*
                    `\s` SATIR SONUNU DA YUTAR.

                    İlk hâl `MAILGUN_SECRET\s*=\s*\S+` idi ve boş bir
                    `MAILGUN_SECRET=` satırından sonra gelen SONRAKİ SATIRI
                    değer sanıyordu. Aynı tuzağa bu oturumda odak CSS'inde de
                    düşülmüştü: aranan şey AYNI SATIRDA olmalı.
                */
                '/[a-z0-9]+\.mailgun\.org|MAILGUN_SECRET[ \t]*=[ \t]*\S+/i',
                $contents,
                "[{$file}] gerçek bir Mailgun değeri taşıyor olabilir."
            );
        }

        /*
            ÖLÇÜLEN ŞEY "GERÇEK BİR SIR YOK", "tam olarak null" DEĞİL.

            İlk hâl `assertNull` idi ve yerelde geçip CI'da kırıldı: CI
            `.env.example`'ı `.env` olarak kopyalıyor, dolayısıyla boş bir
            `MAILGUN_SECRET=` satırı `null` değil BOŞ DİZE üretiyor. İkisi de
            "sır yok" demektir; iddia ortamdan bağımsız olmalı.
        */
        self::assertEmpty(
            config('services.mailgun.secret'),
            'Testte gizli anahtar olmamalı; ortamdan gelir.'
        );
    }
}
