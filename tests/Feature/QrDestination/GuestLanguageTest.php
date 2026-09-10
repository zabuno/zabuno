<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Http\Responses\GuestOutOfService;
use App\Models\User;
use App\Support\Localization\GuestLocale;
use App\Support\Localization\GuestText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\UntranslatableStringScanner;
use Tests\TestCase;

/**
 * P1-06 RED — misafir dil seçimi (`docs/85`).
 *
 * MÜŞTERİ SORUNU. Turistik bir restoranda misafirin yarısı Türkçe okumaz.
 * Misafir sayfasının arayüz metinleri Blade şablonuna SABİT Türkçe yazılmıştı
 * ve misafir için dil değiştirme yolu yoktu.
 *
 * İKİ KATMAN AYRIDIR: arayüz metinleri katalogdan gelir ve çevrilir; MENÜ
 * İÇERİĞİNİN çevirisi ayrı ve çok daha büyük bir iştir. İkisini karıştırmak,
 * arayüzü İngilizceye alan misafire menünün de İngilizce olacağını ima
 * ederdi — tutulmayacak bir söz.
 *
 * Requirement IDs: GUEST-I18N-NO-HARDCODED-01, GUEST-I18N-SWITCH-01,
 * GUEST-I18N-REMEMBERED-01, GUEST-I18N-LANG-DIR-01,
 * GUEST-I18N-CONTENT-HONEST-01, GUEST-I18N-EVERY-OFFERED-LOCALE-01,
 * GUEST-I18N-OUT-OF-SERVICE-LANG-01.
 */
final class GuestLanguageTest extends TestCase
{
    use RefreshDatabase;

    private function publishedMenu(string $seed, string $contentLocale = 'tr'): string
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => $seed, 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Zeytin', 'slug' => $seed.'-b',
            'locale' => $contentLocale, 'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId,
            'display_name' => 'Kadıköy', 'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
            'address_line1' => 'Bahariye Cd. No:1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $publicKey = Str::lower(Str::random(10));

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => $publicKey, 'workspace_id' => $workspaceId,
            'location_id' => $locationId, 'name' => 'Ana Menü', 'state' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId, 'name' => 'Balıklar', 'position' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Levrek',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('menu_items')->insert([
            'category_id' => $categoryId, 'product_id' => $productId,
            'price_minor_amount' => 42000, 'currency_code' => 'TRY',
            'position' => 0, 'is_visible' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications")
            ->assertStatus(201);

        return $publicKey;
    }

    private function guest(string $publicKey, string $query = '')
    {
        return $this->withHeaders(['Accept' => 'text/html'])
            ->followingRedirects()
            ->get("/menu/{$publicKey}".$query);
    }

    // --- GUEST-I18N-NO-HARDCODED-01 ---------------------------------------

    public function test_not_one_user_facing_string_is_hardcoded_in_the_template(): void
    {
        $hits = UntranslatableStringScanner::scanFile(
            resource_path('views/public-menu.blade.php'),
        );

        self::assertSame(
            [],
            $hits,
            'GUEST-I18N-NO-HARDCODED-01: şablona sabit yazılan bir cümleyi sahip hiçbir PO '
            .'dosyasından çeviremez. Bulunanlar: '.implode(' | ', $hits)
        );

        // Betik GÖVDESİ de kullanıcı metnidir ve tarayıcı onu atlıyor;
        // burada ayrıca kontrol edilir.
        $source = (string) file_get_contents(resource_path('views/public-menu.blade.php'));

        preg_match_all('/<script\b(?![^>]*application\/(?:ld\+json|json))[^>]*>(.*?)<\/script>/is', $source, $scripts);

        foreach ($scripts[1] as $body) {
            /*
                YORUMLAR ÖNCE DÜŞER.

                Bir yorum kullanıcı metni DEĞİLDİR ve Türkçe yazılmış bir
                açıklama ihlal sayılamaz — aksi hâlde kural, kararın
                gerekçesini yazmayı cezalandırırdı. Aynı ders bu depoda iki
                mimari kapıda daha öğrenildi (`docs/82`).
            */
            $code = (string) preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', $body);

            self::assertDoesNotMatchRegularExpression(
                // Türkçeye özgü harf taşıyan bir DİZE, çevrilmemiş bir
                // kullanıcı metninin en güvenilir işareti.
                '/[\'"][^\'"\n]*[çğıöşüÇĞİÖŞÜ][^\'"\n]*[\'"]/u',
                $code,
                'GUEST-I18N-NO-HARDCODED-01: betik gövdesinde çevrilmemiş metin var.'
            );
        }
    }

    // --- GUEST-I18N-SWITCH-01 / REMEMBERED-01 / LANG-DIR-01 ---------------

    public function test_the_guest_switches_language_and_the_choice_is_remembered(): void
    {
        $key = $this->publishedMenu('lang-switch');

        $turkish = $this->guest($key);
        $turkish->assertOk();
        self::assertStringContainsString('Menüde ara', $turkish->getContent());
        self::assertMatchesRegularExpression('#<html lang="tr" dir="ltr"#', $turkish->getContent());

        // Dil seçimi düz BAĞLANTIDIR: JavaScript çalışmasa da çalışır.
        self::assertStringContainsString('?lang=en', $turkish->getContent());

        $english = $this->guest($key, '?lang=en');
        $english->assertOk();
        self::assertStringContainsString('Search the menu', $english->getContent());
        self::assertMatchesRegularExpression('#<html lang="en" dir="ltr"#', $english->getContent());

        // Seçim AYNI CİHAZDA hatırlanır: misafir her açılışta yeniden
        // seçmemeli.
        $english->assertCookie(GuestLocale::COOKIE, 'en');

        $remembered = $this->withCookie(GuestLocale::COOKIE, 'en')
            ->withHeaders(['Accept' => 'text/html'])
            ->followingRedirects()
            ->get("/menu/{$key}");

        self::assertStringContainsString('Search the menu', $remembered->getContent());
    }

    // --- GUEST-I18N-EVERY-OFFERED-LOCALE-01 -------------------------------

    /**
     * SUNULAN HER DİL GERÇEKTEN ÇEVRİLMİŞTİR.
     *
     * Test, listeyi ELLE YAZMAZ — `GuestLocale::SUPPORTED` üzerinde yürür.
     * Sebebi bu testin kendi yazılış hikâyesidir: `config/app.php` altı dil
     * sayıyor (`supported_locales`) ve bu, misafire altı dil sunulduğu
     * sanısını verdi. Oysa misafir yüzeyinin listesi ayrıdır ve bugün iki
     * dildir. İki listeyi karıştırmak, olmayan bir kusuru kovalamaya yol
     * açtı.
     *
     * Kural bu yüzden şöyle kuruldu: listeye bir dil EKLENDİĞİ gün bu test
     * onu kendiliğinden kapsar ve kataloğu boşsa kırılır. Yani bir dil,
     * çevrilmeden sunulamaz.
     *
     * KARŞILAŞTIRMA DİLİ İNGİLİZCE OLANDIR: her dil için aynı ekranın
     * arama etiketi okunur ve İngilizcesinden FARKLI olması beklenir. Tek
     * tek çeviri metni yazmak, testi bir kopya kataloğa çevirirdi — o zaman
     * cümle değişince test de değişir ve hiçbir şey korumazdı.
     */
    public function test_every_offered_language_is_actually_translated(): void
    {
        $key = $this->publishedMenu('lang-all-offered');

        $english = $this->guest($key, '?lang=en')->getContent();

        foreach (GuestLocale::SUPPORTED as $locale) {
            if ($locale === 'en') {
                continue;
            }

            $response = $this->guest($key, "?lang={$locale}");
            $response->assertOk();

            $content = $response->getContent();

            self::assertMatchesRegularExpression(
                '#<html lang="'.preg_quote($locale, '#').'" dir="(ltr|rtl)"#',
                $content,
                "GUEST-I18N-EVERY-OFFERED-LOCALE-01: {$locale} sayfası kendi dilini ilan etmeli.",
            );

            /*
                Arapça SAĞDAN SOLA ilan edilmeli. Metni çevirip yönü
                bırakmak, cümleleri doğru ama okunamaz hâlde gösterirdi —
                sayfanın yarısı ters akardı.
            */
            self::assertStringContainsString(
                $locale === 'ar' ? 'dir="rtl"' : 'dir="ltr"',
                $content,
                "GUEST-I18N-EVERY-OFFERED-LOCALE-01: {$locale} yön ilanı yanlış.",
            );

            self::assertNotSame(
                $this->searchLabel($english),
                $this->searchLabel($content),
                "GUEST-I18N-EVERY-OFFERED-LOCALE-01: {$locale} kataloğu boş — ekran İngilizceye düşüyor.",
            );
        }
    }

    /**
     * Sayfadaki arama alanının etiketi — dilin gerçekten değiştiğinin en
     * kısa kanıtı. Her dilde var, her dilde farklı ve bir cümle kadar uzun
     * olmadığı için testi kırılgan yapmıyor.
     */
    private function searchLabel(string $html): string
    {
        preg_match('#placeholder="([^"]*)"#', $html, $matches);

        return $matches[1] ?? '';
    }

    public function test_an_unsupported_language_falls_back_instead_of_breaking(): void
    {
        $key = $this->publishedMenu('lang-unknown');

        $response = $this->guest($key, '?lang=klingon');

        $response->assertOk();
        self::assertMatchesRegularExpression('#<html lang="tr"#', $response->getContent());
    }

    // --- GUEST-I18N-CONTENT-HONEST-01 -------------------------------------

    public function test_the_product_does_not_promise_a_translated_menu(): void
    {
        $key = $this->publishedMenu('lang-honest');

        $english = $this->guest($key, '?lang=en');

        // Ürün adı ÇEVRİLMEZ ve olduğu gibi kalır.
        self::assertStringContainsString('Levrek', $english->getContent());

        // Ve bu durum SÖYLENİR: söylememek, tutulmayacak bir söz vermek olurdu.
        self::assertMatchesRegularExpression(
            '#<p class="qr-menu-content-notice">\s*Dish names are in the restaurant’s own language\.#u',
            $english->getContent(),
            'GUEST-I18N-CONTENT-HONEST-01: içerik çevirisinin yokluğu açıkça söylenmeli.'
        );

        // Menü içeriği KENDİ dilini taşır: ekran okuyucu ürün adlarını
        // restoranın dilinde telaffuz etmeli.
        self::assertMatchesRegularExpression('#<section class="qr-menu-category"[^>]+lang="tr"#', $english->getContent());

        /*
            Arayüz zaten içerik diliyle aynıyken uyarı GÖSTERİLMEZ: söylenecek
            bir şey yok ve her sayfada duran bir not okunmaz hâle gelir.

            Dil AÇIKÇA istenir: aynı test içinde bir önceki istek çerezi
            bıraktı ve "varsayılan" artık İngilizce — bu doğru davranış,
            ama burada ölçmek istediğimiz şey o değil.
        */
        /*
            İddia ELEMENTE bakar: sınıf adı stil bloğunda da geçiyor ve ham
            metin araması "misafir bunu OKUYOR MU" sorusunu cevaplamıyor.
        */
        self::assertDoesNotMatchRegularExpression(
            '#<p class="qr-menu-content-notice">#',
            $this->guest($key, '?lang=tr')->getContent(),
        );
    }

    // --- GUEST-I18N-OUT-OF-SERVICE-LANG-01 --------------------------------

    /**
     * SERVİS DIŞI SAYFASI DA MİSAFİRİN DİLİNİ İLAN EDER.
     *
     * Sayfanın CÜMLESİ zaten misafir diliyle kuruluyordu
     * (`GuestOutOfService` `GuestLocale::resolve` çağırıyor), ama
     * `<html lang>` ondan bağımsız, uygulamanın locale'inden türüyordu. Yani
     * belge "İngilizce" diyor, gövde Türkçe konuşuyordu — ekran okuyucu için
     * bu, cümleyi yanlış dilde telaffuz etmek demek.
     *
     * Kapı ZATEN açıktı; şablon ondan geçmiyordu.
     */
    public function test_the_out_of_service_page_declares_the_guests_language(): void
    {
        $turkish = $this->outOfService('/menu/anything', 'tr');

        self::assertMatchesRegularExpression(
            '#<html lang="tr" dir="ltr"#',
            $turkish,
            'GUEST-I18N-OUT-OF-SERVICE-LANG-01: belge dili, gövdeyi kuran misafir diliyle aynı olmalı.',
        );

        $english = $this->outOfService('/menu/anything?lang=en', 'tr');

        self::assertMatchesRegularExpression('#<html lang="en" dir="ltr"#', $english);
    }

    /**
     * SEÇİM YOKKEN BELGE DİLİNİ VEREN, RESTORANIN DİLİDİR.
     *
     * Sıranın son basamağı içerik dilidir: misafir ne `?lang=` yazdı ne de
     * çerezi var; o hâlde cümle restoranın kendi dilinde kurulur
     * (`GuestOutOfService` bunu ZATEN yapıyordu). Ama şablon dili kendisi
     * çözerken içerik dilini GÖREMİYORDU — `resolve(request(), null)`
     * çağrısında düşülecek bir restoran dili yok, dolayısıyla `tr`'ye
     * düşüyordu.
     *
     * Sonuç, düzeltilmeden önce: İngilizce bir gövde taşıyan belge kendini
     * `lang="tr"` ilan ediyordu — ekran okuyucu İngilizce cümleyi Türkçe
     * telaffuz ederdi (WCAG 3.1.1). Bu, `?lang=`/çerez yolları düzeldikten
     * sonra kalan tek sapmaydı.
     *
     * Cümle metni TESTE KOPYALANMAZ: katalogdan okunur ve yalnız Türkçesinden
     * FARKLI olması beklenir. Kopyalasaydık cümle her düzeltildiğinde test de
     * değişir ve hiçbir şey korumazdı.
     */
    public function test_the_out_of_service_page_follows_the_restaurants_language_when_the_guest_chose_none(): void
    {
        $page = $this->outOfService('/menu/anything', 'en');

        self::assertMatchesRegularExpression(
            '#<html lang="en" dir="ltr"#',
            $page,
            'GUEST-I18N-OUT-OF-SERVICE-LANG-01: seçim yokken belge dili, gövdeyi kuran içerik diliyle aynı olmalı.',
        );

        $text = app(GuestText::class);

        $englishHeading = $text->outOfService('en', '02:00')['heading'];

        self::assertNotSame(
            $text->outOfService('tr', '02:00')['heading'],
            $englishHeading,
            'Karşılaştırma anlamsızsa iddia da anlamsız: iki dilin başlığı gerçekten farklı olmalı.',
        );

        // Gövde ile ilan edilen dil AYNI olmalı; ikisini ayrı ayrı doğrulamak,
        // aralarındaki tutarlılığı kanıtlamaz.
        self::assertStringContainsString($englishHeading, $page);
    }

    /**
     * Servis dışı yanıtını, saat kurgusu olmadan doğrudan çizdirir.
     *
     * İstek KAPSAYICIYA da bağlanır: gerçek bir HTTP isteğinde çekirdek bunu
     * zaten yapar ve şablon `request()` üzerinden aynı isteği görür. Bağlamayı
     * atlarsak testin ölçtüğü şey ürünün davranışı değil, testin kendi
     * kurgusunun eksikliği olurdu.
     */
    private function outOfService(string $uri, string $contentLocale): string
    {
        $request = Request::create($uri, 'GET');
        $request->headers->set('Accept', 'text/html');
        $this->app->instance('request', $request);

        return (string) GuestOutOfService::respond($request, 'Zeytin', $contentLocale, '02:00')->getContent();
    }
}
