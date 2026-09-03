<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P1-01 RED — çalışan bir iletişim yolu (`docs/88`).
 *
 * MÜŞTERİ SORUNU. "Tıkanırsam kime sorarım?" sorusunun cevabı sayfada
 * "henüz bağlı bir iletişim formu yok" yazıyordu.
 *
 * KARAR: mesaj ÖNCE SAKLANIR, sonra (yapılandırılmışsa) e-postayla gönderilir.
 *
 * Gereksinim iletişimi P0-06'ya (gerçek e-posta) bağlıyor ve o madde
 * sahibin sağlayıcı hesabını bekliyor. Ama "ulaşmak" için e-posta şart
 * değil: saklanan bir mesaj kaybolmaz ve sahibi onu okuyabilir. E-postaya
 * bağlamak, sağlayıcı gelene kadar formu ölü tutardı — yani sorunun kendisi
 * devam ederdi.
 *
 * Requirement IDs: CONTACT-PERSISTED-01, CONTACT-CONFIRMED-01,
 * CONTACT-VALIDATED-01, CONTACT-NO-AUTH-01, CONTACT-HONEYPOT-01.
 */
final class ContactMessageTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function message(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Hüseyin',
            'email' => 'huseyin@example.com',
            'message' => 'Kadıköy\'de 40 masalık bir restoranım var, fiyat alabilir miyim?',
        ], $overrides);
    }

    // --- CONTACT-NO-AUTH-01 -----------------------------------------------

    public function test_the_form_is_reachable_without_an_account(): void
    {
        $response = $this->get('/contact');

        $response->assertOk();

        $html = $response->getContent();

        // Alanların ETİKETİ olmalı: yer tutucu bir etiket değildir ve
        // ekran okuyucu onu alan adı olarak okumaz.
        self::assertMatchesRegularExpression('#<label[^>]+for="contact-name"#', $html);
        self::assertMatchesRegularExpression('#<label[^>]+for="contact-email"#', $html);
        self::assertMatchesRegularExpression('#<label[^>]+for="contact-message"#', $html);
    }

    // --- CONTACT-PERSISTED-01 / CONTACT-CONFIRMED-01 ----------------------

    public function test_a_message_is_kept_and_the_sender_is_told(): void
    {
        $response = $this->post('/contact', $this->message());

        $response->assertRedirect();
        $response->assertSessionHas('contact.sent');

        $row = DB::table('contact_messages')->first();

        self::assertNotNull($row, 'CONTACT-PERSISTED-01: mesaj kaybolmamalı.');
        self::assertSame('Hüseyin', (string) $row->name);
        self::assertSame('huseyin@example.com', (string) $row->email);
        self::assertStringContainsString('40 masalık', (string) $row->message);

        // Teyit EKRANDA görünür: "gönderildi" demeyen bir form, gönderilip
        // gönderilmediğini bilmeyen bir kullanıcı bırakır.
        $confirmed = $this->followingRedirects()->post('/contact', $this->message());
        self::assertMatchesRegularExpression('#[Tt]hank you|received#', $confirmed->getContent());
    }

    // --- CONTACT-VALIDATED-01 ---------------------------------------------

    public function test_a_broken_address_is_refused_with_a_reason(): void
    {
        $this->post('/contact', $this->message(['email' => 'bu-bir-adres-degil']))
            ->assertSessionHasErrors('email');

        self::assertSame(0, DB::table('contact_messages')->count());
    }

    public function test_an_empty_message_is_refused(): void
    {
        $this->post('/contact', $this->message(['message' => '   ']))
            ->assertSessionHasErrors('message');

        self::assertSame(0, DB::table('contact_messages')->count());
    }

    // --- CONTACT-HONEYPOT-01 ----------------------------------------------

    public function test_a_bot_that_fills_the_hidden_field_is_dropped_silently(): void
    {
        /*
            Bal küpü: insan görmediği bir alanı doldurmaz.

            Ret SESSİZDİR ve başarı gibi görünür — bota "yakalandın" demek,
            bir sonraki denemede o alanı atlamasını öğretirdi.
        */
        $response = $this->post('/contact', $this->message(['website' => 'https://spam.example']));

        $response->assertRedirect();
        $response->assertSessionHas('contact.sent');

        self::assertSame(
            0,
            DB::table('contact_messages')->count(),
            'CONTACT-HONEYPOT-01: bal küpü dolu bir gönderim saklanmamalı.'
        );
    }
}
