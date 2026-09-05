<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FF-93 — tarayıcının dili uygulamanın dilini SEÇER.
 *
 * MÜŞTERİ SORUNU. Ürün altı dil taşıyor ve Türkçe katalog dolu; ama hiçbir
 * yerde bir dil SEÇİMİ yapılmıyordu. `app()->getLocale()` her istekte
 * yapılandırmadaki `en` kalıyor, `<html lang>` de ondan türüyordu. İstemci
 * çevirici locale'i `<html lang>`'den okur — yani Türkçe çeviriler yazılmış
 * olsa bile hiçbir Türk kullanıcı onları GÖREMİYORDU.
 *
 * Kamu sayfaları bunu kendi içinde tek tek çözüyordu (`getPreferredLanguage`
 * çağrıları). Kabuklar (giriş, çalışma alanı) hiç çözmüyordu; aynı üründe
 * iki farklı gerçek vardı.
 *
 * ═══ 2026-09-05: PAZARLIK ARTIK SUNULAN DİL LİSTESİNİ OKUYOR ═══
 *
 * FF-93 pazarlığı `app.supported_locales` üzerinden yapıyordu — altı dillik
 * bir liste. Ama hangi dilin GERÇEKTEN sunulabileceğine karar veren yer
 * `i18n.shipped_locales` ve o listedeki her dilin katalogu TAM olmak zorunda
 * (`ShippedLocalesAreCompleteTest`).
 *
 * İki liste ayrışınca ortaya sahibin ekranındaki manzara çıktı: Türkçe
 * tarayıcı Türkçe belge alıyor, ama katalog tam olmadığı için ekranda
 * KARIŞIK DİL beliriyordu — "Menus", "Preview & publish" İngilizce; "Ürün
 * ekle", "Hepsi tükendi" Türkçe. Yarım çeviri, çevirisizlikten kötüdür:
 * kullanıcı ürünün bozuk olduğunu düşünür.
 *
 * `shipped_locales` adı "sunulan diller" diyordu ve hiçbir uygulama kodu onu
 * okumuyordu — bu deponun tekrar eden kusuru (`docs/109` §8.7): çalışan ama
 * söylediği şeyi yapmayan bir ayar.
 *
 * Bu testler artık DEĞİŞMEZ KURALI ölçüyor, belirli bir dili değil: sunulan
 * belge dili her zaman `shipped_locales` içindedir. Sahip yarın Türkçeyi geri
 * açarsa testler değişmeden geçmeye devam eder; kural aynı kalır.
 *
 * Requirement ID'leri: I18N-NEGOTIATE-HTML-01, I18N-NEGOTIATE-FALLBACK-02,
 * I18N-NEGOTIATE-REGIONAL-03, I18N-NEGOTIATE-DIR-04, I18N-SHIPPED-ONLY-05.
 */
final class RequestLocaleNegotiationTest extends TestCase
{
    use RefreshDatabase;

    // --- I18N-SHIPPED-ONLY-05 ---------------------------------------------

    /**
     * SUNULMAYAN BİR DİL SUNULMAZ — hangi dil olduğundan bağımsız.
     *
     * Bu testin gücü, bir dil ADI taşımamasında: yarın sahip Türkçeyi geri
     * açarsa da, yeni bir dil eklerse de aynı kural ölçülür.
     */
    public function test_the_document_language_is_always_one_that_is_actually_shipped(): void
    {
        /** @var list<string> $shipped */
        $shipped = config('i18n.shipped_locales');

        foreach (['tr,en;q=0.8', 'tr-TR,tr;q=0.9', 'de', 'ar', 'ru', 'fr', 'ja,ko;q=0.8'] as $header) {
            $html = (string) $this->withHeaders(['Accept-Language' => $header])->get('/login')->getContent();

            preg_match('/<html lang="([a-z-]+)"/', $html, $matches);

            self::assertNotEmpty($matches, "I18N-SHIPPED-ONLY-05: [{$header}] belge dili yazılmamış.");
            self::assertContains(
                $matches[1],
                $shipped,
                "I18N-SHIPPED-ONLY-05: [{$header}] için `{$matches[1]}` sunuldu ama sunulan diller listesinde yok — "
                .'yarım çevrilmiş bir dil, çevirisizlikten kötüdür.'
            );
        }
    }

    /**
     * LİSTEDEKİ BİR DİL GERÇEKTEN SEÇİLİR.
     *
     * Yukarıdaki kural tek başına "her zaman İngilizce ver" ile de geçerdi ve
     * o gün pazarlık ölü bir koda dönüşürdü. Burası pazarlığın hâlâ ÇALIŞTIĞINI
     * ölçüyor: liste genişlediğinde tarayıcının dili yine seçiliyor.
     */
    public function test_a_shipped_language_is_actually_negotiated(): void
    {
        config()->set('i18n.shipped_locales', ['en', 'tr']);

        $response = $this->withHeaders(['Accept-Language' => 'tr,en;q=0.8'])->get('/login');

        $response->assertStatus(200);
        $response->assertSee('<html lang="tr" dir="ltr">', false);
    }

    // --- I18N-NEGOTIATE-REGIONAL-03 ---------------------------------------

    public function test_a_regional_tag_resolves_to_its_base_language(): void
    {
        config()->set('i18n.shipped_locales', ['en', 'tr']);

        $response = $this->withHeaders(['Accept-Language' => 'tr-TR,tr;q=0.9'])->get('/login');

        $response->assertStatus(200);
        // `tr-TR` katalogda yok; taban dile inilir, yoksa kullanıcı hiç
        // desteklenmiyormuş gibi İngilizce görürdü.
        $response->assertSee('<html lang="tr"', false);
    }

    // --- I18N-NEGOTIATE-FALLBACK-02 ---------------------------------------

    public function test_an_unsupported_language_falls_back_to_english(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'ja,ko;q=0.8'])->get('/login');

        $response->assertStatus(200);
        $response->assertSee('<html lang="en"', false);
    }

    public function test_a_request_without_the_header_stays_on_the_configured_default(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('<html lang="en"', false);
    }

    // --- I18N-NEGOTIATE-DIR-04 --------------------------------------------

    /**
     * YÖN LOCALE'İN ÖZELLİĞİDİR ve sunulan dile bağlıdır.
     *
     * Arapça bugün sunulmuyor; sunulduğu gün belge sağdan sola olmalı. Test
     * o günü şimdiden ölçüyor, çünkü yön bir şablon kararı değil dilin
     * kendisinin özelliğidir (`docs/37` §2.2).
     */
    public function test_a_right_to_left_language_gets_a_right_to_left_document_when_shipped(): void
    {
        config()->set('i18n.shipped_locales', ['en', 'ar']);

        $response = $this->withHeaders(['Accept-Language' => 'ar'])->get('/login');

        $response->assertStatus(200);
        $response->assertSee('<html lang="ar" dir="rtl">', false);
    }

    public function test_the_workspace_shell_negotiates_the_same_way(): void
    {
        config()->set('i18n.shipped_locales', ['en', 'tr']);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)
            ->withHeaders(['Accept-Language' => 'tr'])
            ->get('/app');

        $response->assertStatus(200);
        $response->assertSee('<html lang="tr"', false);
    }
}
