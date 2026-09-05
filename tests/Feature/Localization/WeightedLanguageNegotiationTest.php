<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Support\Localization\LanguageNegotiator;
use App\Support\Localization\LanguageType;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * `docs/120` §4 — ağırlıklı tespit zinciri ve dil TÜRÜ ayrımı.
 *
 * Sahibin yönlendirmesi: "Drupal'da bahsettiğim ağırlık vardı ve dil
 * değiştirici buna göre otonom çalışıyordu." Doğru mekanizma bu: dil seçimi
 * bir `if/else` yığını değil, sıralı bir ÇÖZÜCÜ kütüğüdür. İlk çözebilen
 * kazanır; çözemeyen sessizce sırayı bırakır.
 *
 * Bu testler SONUCU ölçer, mekanizmayı değil: hangi sinyalin kazandığını ve
 * sıranın değiştiğinde kodun değişmediğini.
 *
 * Requirement ID'leri: I18N-CHAIN-WEIGHTED-06, I18N-CHAIN-CONFIGURED-07,
 * I18N-CHAIN-NULL-08, I18N-TYPE-SPLIT-09, I18N-CHAIN-SHIPPED-10.
 */
final class WeightedLanguageNegotiationTest extends TestCase
{
    private function negotiator(): LanguageNegotiator
    {
        return app(LanguageNegotiator::class);
    }

    /** Zincirin hepsini tanısın diye ölçümlerde dokuz dil de sunuluyor sayılır. */
    private function shipAll(): void
    {
        config()->set('i18n.shipped_locales', ['en', 'tr', 'de', 'ar', 'ru', 'fa', 'ku', 'fr', 'it']);
    }

    /**
     * TARAYICI SİNYALİ OLMAYAN bir istek.
     *
     * `Request::create` kendiliğinden bir `Accept-Language: en-us,en;q=0.5`
     * başlığı kurar — gerçek bir tarayıcının davranışını taklit etmek için.
     * Ama "tarayıcı hiç konuşmadığında ne olur" sorusunu ölçerken o başlık
     * ölçümün kendisini bozar: her istekte İngilizce çıkar ve zincirin geri
     * kalanı hiç denenmemiş olur.
     *
     * @param  array<string, string>  $cookies
     */
    private function requestWithoutBrowserSignal(string $uri, array $cookies = []): Request
    {
        $request = Request::create($uri, 'GET', [], $cookies);
        $request->headers->remove('Accept-Language');
        $request->server->remove('HTTP_ACCEPT_LANGUAGE');

        return $request;
    }

    // --- I18N-CHAIN-WEIGHTED-06 -------------------------------------------

    /**
     * AÇIK SEÇİM EN AĞIRDIR — tarayıcı ne derse desin.
     *
     * `docs/120` §4.2: Almanya'da yaşayan bir Türk, tarayıcısı Almanca olsa
     * da Türkçe okumak isteyebilir. Bir kez seçtiyse sistem onu bir daha
     * sorgulamaz; sorgulasaydı her ziyarette kararını geri alırdı.
     */
    public function test_an_explicit_choice_beats_the_browser(): void
    {
        $this->shipAll();

        $request = Request::create('/app', 'GET', [], ['zbn_language' => 'tr'], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9',
        ]);

        self::assertSame(
            'tr',
            $this->negotiator()->negotiate(LanguageType::Interface, $request),
            'I18N-CHAIN-WEIGHTED-06: kullanıcı bir kez seçtiyse tarayıcı onu ezemez.'
        );
    }

    /**
     * BÖLGE DİLİ SEÇMEZ, BELİRSİZLİĞİ ÇÖZER.
     *
     * İstanbul'daki bir tarayıcı `en` diyorsa dil İngilizcedir. Saat
     * dilimine bakıp Türkçeye çevirmek, kullanıcının açık ayarını görmezden
     * gelmektir — bu yüzden bölge yalnız kendinden ağır hiçbir yöntem
     * çözemediğinde konuşur.
     */
    public function test_the_region_only_speaks_when_nothing_heavier_resolved(): void
    {
        $this->shipAll();

        $withBrowser = Request::create('/app', 'GET', [], ['zbn_timezone' => 'Europe/Istanbul'], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
        ]);

        self::assertSame(
            'en',
            $this->negotiator()->negotiate(LanguageType::Interface, $withBrowser),
            'I18N-CHAIN-WEIGHTED-06: saat dilimi, tarayıcının açık ayarını ezdi.'
        );

        $withoutBrowser = $this->requestWithoutBrowserSignal('/app', ['zbn_timezone' => 'Europe/Istanbul']);

        self::assertSame(
            'tr',
            $this->negotiator()->negotiate(LanguageType::Interface, $withoutBrowser),
            'I18N-CHAIN-WEIGHTED-06: başka hiçbir sinyal yokken bölge belirsizliği çözmeliydi.'
        );
    }

    // --- I18N-CHAIN-CONFIGURED-07 -----------------------------------------

    /**
     * AĞIRLIK YAPILANDIRMADIR, KODA GÖMÜLÜ DEĞİL.
     *
     * `docs/120` §4.2'nin asıl değeri bu: "bir sıralama denemesi bir dağıtım
     * değil, bir ayardır." Ölçüm bunu tek yoldan yapabilir — sırayı
     * DEĞİŞTİRİP sonucun değiştiğini görmek. Kod bu test boyunca hiç
     * değişmiyor; yalnız yapılandırma değişiyor.
     */
    public function test_reordering_the_weights_changes_the_outcome_without_touching_code(): void
    {
        $this->shipAll();

        $request = Request::create('/app', 'GET', [], ['zbn_language' => 'tr'], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9',
        ]);

        self::assertSame('tr', $this->negotiator()->negotiate(LanguageType::Interface, $request));

        // Tarayıcı, açık seçimden AĞIR hâle geliyor — yalnız ayar değişti.
        config()->set('i18n.negotiation.methods.browser.weight', -30);

        self::assertSame(
            'de',
            $this->negotiator()->negotiate(LanguageType::Interface, $request),
            'I18N-CHAIN-CONFIGURED-07: ağırlık değişti ama sonuç değişmedi — sıra koda gömülü demektir.'
        );
    }

    public function test_a_method_removed_from_the_chain_stops_speaking(): void
    {
        $this->shipAll();

        config()->set('i18n.negotiation.chains.interface', ['browser', 'source']);

        $request = Request::create('/app', 'GET', [], ['zbn_language' => 'tr'], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9',
        ]);

        self::assertSame(
            'de',
            $this->negotiator()->negotiate(LanguageType::Interface, $request),
            'I18N-CHAIN-CONFIGURED-07: zincirden çıkarılan yöntem hâlâ konuşuyor.'
        );
    }

    // --- I18N-CHAIN-NULL-08 -----------------------------------------------

    /**
     * `null` ZİNCİRİ KESMEZ.
     *
     * Bir yöntemin çözememesi bir hata değildir; çoğu istekte çoğu yöntem
     * çözemez. Kesseydi, çerezi olmayan her ziyaretçi kaynak dile düşerdi ve
     * tarayıcı ayarı hiç okunmazdı.
     */
    public function test_a_method_that_cannot_resolve_hands_over_instead_of_ending_the_chain(): void
    {
        $this->shipAll();

        // Ne çerez ne parametre var; yalnız tarayıcı konuşabiliyor.
        $request = Request::create('/app', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'ru']);

        self::assertSame(
            'ru',
            $this->negotiator()->negotiate(LanguageType::Interface, $request),
            'I18N-CHAIN-NULL-08: çözemeyen bir yöntem zinciri kesmiş.'
        );
    }

    public function test_a_signalless_request_keeps_the_running_application_language(): void
    {
        /*
            Sinyal yokken karar vermek, dili başka bir yerde (bir konsol
            komutu, bir testin kurduğu bağlam) bilerek ayarlamış olan tarafı
            sessizce ezmek olurdu. Kaynak dil çözücüsü bu yüzden yapılandırma
            dosyasını değil, O ANDA ÇALIŞAN dili döndürür.
        */
        app()->setLocale('tr');
        config()->set('i18n.shipped_locales', ['en', 'tr']);

        self::assertSame(
            'tr',
            $this->negotiator()->negotiate(LanguageType::Interface, $this->requestWithoutBrowserSignal('/app')),
            'I18N-CHAIN-NULL-08: sinyalsiz istek, kurulmuş dili ezdi.'
        );
    }

    // --- I18N-TYPE-SPLIT-09 -----------------------------------------------

    /**
     * ARAYÜZ, İÇERİK VE URL AYRI ZİNCİRLERDİR.
     *
     * `docs/118` E4'te ölçülen buydu: kurumsal sayfa dilini ADRESTEN, ürün
     * arayüzü TARAYICIYLA PAZARLIKTAN alıyor. İkisi bugüne kadar KAZAYLA
     * doğruydu, çünkü ayrım hiçbir yerde yazılı değildi. Kazayla doğru olan
     * bir şey bir gün kazayla yanlış olur.
     *
     * Bir kullanıcının arayüzü İngilizce, okuduğu sayfa Türkçe olabilir ve
     * bu bir hata değildir.
     */
    public function test_the_address_decides_the_content_language_and_the_browser_cannot_touch_it(): void
    {
        $this->shipAll();

        $request = Request::create('/tr/urun/qr-menu/', 'GET', [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
        ]);

        self::assertSame(
            'tr',
            $this->negotiator()->negotiate(LanguageType::Content, $request),
            'I18N-TYPE-SPLIT-09: içerik dili pazarlıkla değişti — `/tr/` altındaki bir sayfa Türkçe yazılmıştır.'
        );

        self::assertSame(
            'en',
            $this->negotiator()->negotiate(LanguageType::Interface, $request),
            'I18N-TYPE-SPLIT-09: arayüz dili adresten geldi — iki zincir birbirine karışmış.'
        );
    }

    public function test_the_url_chain_reads_the_path_prefix(): void
    {
        $this->shipAll();

        self::assertSame(
            'en',
            $this->negotiator()->negotiate(LanguageType::Url, Request::create('/en/pricing/')),
            'I18N-TYPE-SPLIT-09: adres zinciri yol önekini okumuyor.'
        );
    }

    // --- I18N-CHAIN-SHIPPED-10 --------------------------------------------

    /**
     * ARAYÜZ ZİNCİRİ `shipped_locales` İLE SÜZÜLÜR — içerik zinciri SÜZÜLMEZ.
     *
     * Bir yöntem sunulmayan bir dil döndürürse o cevap DÜŞER ve sıra devam
     * eder. Aksi hâlde 2026-09-05'te kapatılan kusur geri gelirdi: yarım
     * çevrilmiş bir dil sunulur ve ekranda karışık dil belirir.
     *
     * İçerik zinciri süzülmez, çünkü `/tr/` altındaki bir sayfa Türkçe
     * YAZILMIŞTIR; onu İngilizce ilan etmek ekran okuyucuyu ve arama
     * motorunu yanıltır.
     */
    public function test_an_unshipped_language_is_dropped_from_the_interface_chain_but_not_from_content(): void
    {
        config()->set('i18n.shipped_locales', ['en']);

        $request = Request::create('/tr/urun/qr-menu/', 'GET', [], ['zbn_language' => 'tr'], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'tr-TR,tr;q=0.9',
        ]);

        self::assertSame(
            'en',
            $this->negotiator()->negotiate(LanguageType::Interface, $request),
            'I18N-CHAIN-SHIPPED-10: sunulmayan bir dil arayüze girdi.'
        );

        self::assertSame(
            'tr',
            $this->negotiator()->negotiate(LanguageType::Content, $request),
            'I18N-CHAIN-SHIPPED-10: içerik zinciri sunulan diller listesiyle süzülmüş — adres dili düşürülemez.'
        );
    }

    /**
     * KÜTÜKTE OLMAYAN BİR DİL HİÇBİR ZİNCİRDEN GEÇEMEZ.
     *
     * `?language=xx` ya da `/xx/` ile gelen bir istek, altyapının hiç
     * tanımadığı bir dili uygulamaya sokamamalı; aksi hâlde `setLocale`
     * çağrısı olmayan bir kataloğu işaret ederdi.
     */
    public function test_a_language_outside_the_registry_is_never_selected(): void
    {
        $this->shipAll();

        $request = Request::create('/app', 'GET', ['language' => 'ja'], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'ja,ko;q=0.8',
        ]);

        self::assertSame(
            'en',
            $this->negotiator()->negotiate(LanguageType::Interface, $request),
            'I18N-CHAIN-SHIPPED-10: kütükte olmayan bir dil seçildi.'
        );
    }

    /**
     * BÖLGELİ ETİKET TABAN DİLE İNER.
     *
     * Katalog taban dillerle anahtarlanır; `tr-TR` yüzünden birini
     * İngilizceye düşürmek, desteklenen bir dili desteklenmiyormuş gibi
     * göstermek olurdu.
     */
    public function test_a_regional_tag_is_reduced_to_its_base_language(): void
    {
        $this->shipAll();

        $request = Request::create('/app', 'GET', [], ['zbn_language' => 'de-AT']);

        self::assertSame('de', $this->negotiator()->negotiate(LanguageType::Interface, $request));
    }
}
