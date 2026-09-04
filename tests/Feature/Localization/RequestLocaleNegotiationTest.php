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
 * Requirement ID'leri: I18N-NEGOTIATE-HTML-01, I18N-NEGOTIATE-FALLBACK-02,
 * I18N-NEGOTIATE-REGIONAL-03, I18N-NEGOTIATE-DIR-04.
 */
final class RequestLocaleNegotiationTest extends TestCase
{
    use RefreshDatabase;

    // --- I18N-NEGOTIATE-HTML-01 -------------------------------------------

    public function test_a_turkish_browser_gets_a_turkish_document_language(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'tr,en;q=0.8'])->get('/login');

        $response->assertStatus(200);
        $response->assertSee('<html lang="tr" dir="ltr">', false);
    }

    // --- I18N-NEGOTIATE-REGIONAL-03 ---------------------------------------

    public function test_a_regional_tag_resolves_to_its_base_language(): void
    {
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

    public function test_an_arabic_browser_gets_a_right_to_left_document(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'ar'])->get('/login');

        $response->assertStatus(200);
        // Yön locale'in özelliğidir; şablon onu bilmez (`docs/37` §2.2).
        $response->assertSee('<html lang="ar" dir="rtl">', false);
    }

    public function test_the_workspace_shell_negotiates_the_same_way(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)
            ->withHeaders(['Accept-Language' => 'tr'])
            ->get('/app');

        $response->assertStatus(200);
        $response->assertSee('<html lang="tr"', false);
    }
}
