<?php

declare(strict_types=1);

namespace Tests\Feature\Url;

use App\Domain\Publication\BusinessType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADDRESS-SPACE-01 — FF-121, sahibin sorusu (2026-09-04):
 * "yeni url adreslerine göre, login, register, urls, panels, urls?"
 *
 * Sitede ÜÇ ayrı adres uzayı var ve üçünün kuralı farklı. Bu test o ayrımı
 * dondurur; ayrım yazılı olmazsa bir gün biri diğerinin altına sızar.
 *
 * 1. KURUMSAL SİTE — `/tr/…`, `/en/…`
 *    Dil ADRESTE. Bu sayfalar indekslenir; her dil ayrı bir canonical sayfadır
 *    ve karşılıklı hreflang ister.
 *
 * 2. UYGULAMA (panel + kimlik) — `/login`, `/register`, `/app/{ws}/…`
 *    Dil ADRESTE DEĞİL ve bu bir eksiklik değil bir karar:
 *    - Bu sayfalar `noindex` ve robots'ta kapalı; dil segmenti SEO'ya hiçbir
 *      şey kazandırmaz, yalnız aynı ekranın iki adresi olur.
 *    - Uygulamanın dili KULLANICININ ayarıdır. Adrese yazmak, kaydedilmiş
 *      tercihle adresin çekişmesi demekti: `/tr/app/…` açan bir İngilizce
 *      kullanıcı hangisini görecek?
 *    - E-postayla giden bağlantılar (davet, şifre sıfırlama, e-posta
 *      doğrulama) dilden bağımsız ve KALICI olmak zorunda. `/tr/sifre-sifirla/
 *      {token}` alıcının dili farklıysa kırılır.
 *
 * 3. KİRACI (misafir) — `/restoran/{isletme}/menu/{anahtar}`, `/q/{token}`
 *    Menünün TEK bir kanonik adresi vardır; tür segmentinin dili işletmenin
 *    kendi dilidir ve misafirin arayüz dili çerezle taşınır.
 */
final class AddressSpaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_and_auth_pages_keep_their_locale_free_addresses(): void
    {
        // Bu adresler e-postalarda, yer imlerinde ve basılı belgelerde yaşıyor.
        $this->get('/login')->assertStatus(200);
        $this->get('/register')->assertStatus(200);

        // Dil dizininin altında AYNI ekranın ikinci bir adresi YOKTUR.
        $this->get('/tr/login')->assertStatus(404);
        $this->get('/en/register')->assertStatus(404);
    }

    public function test_the_application_and_auth_pages_are_never_indexable(): void
    {
        /*
            Dil segmenti bu sayfalara hiçbir şey kazandırmaz çünkü zaten
            indekslenmiyorlar. Kural burada ölçülür ki "sonra ekleriz" diye
            bırakılmış bir boşluk olmasın.
        */
        foreach (['/login', '/register', '/forgot-password'] as $path) {
            $response = $this->get($path);

            self::assertStringContainsString(
                'noindex',
                (string) $response->headers->get('X-Robots-Tag'),
                "ADDRESS-SPACE-01: {$path} indekslenebilir görünüyor.",
            );
        }
    }

    public function test_robots_closes_the_application_and_opens_the_public_surfaces(): void
    {
        $robots = (string) $this->get('/robots.txt')->getContent();

        foreach (['/app', '/login', '/register', '/platform', '/api'] as $closed) {
            self::assertStringContainsString("Disallow: {$closed}", $robots);
        }

        // Misafirin gördüğü yüzeyler açık kalır.
        self::assertStringContainsString('Allow: /menu/', $robots);
        self::assertStringContainsString('Sitemap:', $robots);
    }

    public function test_the_three_address_spaces_can_never_collide(): void
    {
        /*
            Çakışma ilk segmentte çözülür. Kiracı adresi bir tür segmentiyle
            başlar, kurumsal site bir dil koduyla, uygulama ise sabit
            kelimelerle. Üçü de rezerve listesinde olmalı — biri eksikse bir
            gün bir işletme slug'ı `/login`'i gölgeler.
        */
        /** @var list<string> $reserved */
        $reserved = config('url-policy.reserved_slugs');

        $roots = array_merge(
            BusinessType::allSegments(),
            ['tr', 'en'],
            ['login', 'register', 'app', 'platform', 'engineering', 'api', 'q', 'menu'],
        );

        foreach ($roots as $root) {
            self::assertContains(
                $root,
                $reserved,
                "ADDRESS-SPACE-01: '{$root}' rezerve değil — bir kiracı bu kökü gölgeleyebilir.",
            );
        }
    }

    public function test_a_locale_directory_never_swallows_the_tenant_or_application_space(): void
    {
        // Kurumsal kapı `/{locale}/{path?}` deseniyle çalışıyor; deseni
        // gevşetmek bu iki adresi de yutardı.
        $this->get('/pricing')->assertStatus(200);
        $this->get('/app')->assertRedirect();
    }

    /**
     * PANELİN HER ADRESİ YENİLENEBİLİR OLMALI — sahibin 2026-09-05 bildirimi
     * (ekranda çıplak bir "404 Not Found").
     *
     * Panel gezintisi gerçek adres yazıyor (`pushState`) ve bölüm içindeki
     * ekranların adresi İKİ segmentli olabiliyor: `sectionHref()` bir alt yol
     * ekliyor ve `/app/{ws}/settings/brand` gibi adresler üretiyor. Sunucu
     * rotası ise tek segment kabul ediyordu — yani o adres yalnız istemci
     * gezintisiyle ÇALIŞIYOR, yenilendiğinde ya da bağlantı paylaşıldığında
     * 404 veriyordu.
     *
     * Bu, fragment'ten gerçek adrese geçmenin bütün gerekçesini yok eder:
     * `docs/38` §4 adresi "paylaşılabilir ve yer imine eklenebilir" olsun diye
     * istiyor. Yenilenemeyen bir adres, fragment'ten yalnız görünüşte farklıdır
     * — ve fragment en azından 404 vermezdi.
     *
     * Bölüm adı burada yine DOĞRULANMIYOR (rotanın kendi gerekçesi): sunucu
     * aynı kabuğu döndürür, hangi bölümün geçerli olduğuna istemci karar verir.
     * Ölçülen tek şey, adresin sunucuda BİR KARŞILIĞI olduğu.
     */
    public function test_a_screen_address_with_a_sub_path_still_resolves_on_a_full_reload(): void
    {
        foreach ([
            '/app/zeytin/settings',
            '/app/zeytin/settings/brand',
            '/app/zeytin/menus/12',
            '/app/zeytin/qr-codes/5/history',
        ] as $address) {
            /*
                Oturum açılmamış bir istek YÖNLENDİRİLİR (401/302), 404 ALMAZ:
                404, "böyle bir ekran yok" demektir ve bu yanlış bir cümledir.
                Rotanın kendi yorumu da bunu şart koşuyor.
            */
            $this->get($address)->assertRedirect();
        }
    }
}
