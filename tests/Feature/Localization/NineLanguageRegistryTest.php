<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Support\Localization\DocumentLocale;
use App\Support\Localization\Language;
use Tests\TestCase;

/**
 * `docs/120` §2 ve §6 — dokuz dilin TEK KAYNAKLI tanımı.
 *
 * Altyapı dokuz dili TANIR, ürün bugün yalnız birini SUNAR. İkisi ayrı
 * sorudur ve bu ayrım `docs/120` §1'de yazılıdır: "bu dili tarif edebiliyor
 * muyuz?" ile "bu dilde eksiksiz bir ürün verebiliyor muyuz?".
 *
 * Bu testler birinci soruyu ölçer. İkinci soru `shipped_locales`'in işidir ve
 * bu paket ona DOKUNMAZ.
 *
 * Requirement ID'leri: I18N-NINE-REGISTRY-01, I18N-ENDONYM-02,
 * I18N-NINE-DIRECTION-03, I18N-FLAG-EXCEPTION-04, I18N-REGISTRY-SINGLE-05.
 */
final class NineLanguageRegistryTest extends TestCase
{
    // --- I18N-NINE-REGISTRY-01 --------------------------------------------

    public function test_the_registry_carries_exactly_the_nine_languages_the_owner_named(): void
    {
        $codes = array_map(static fn (Language $l): string => $l->value, Language::cases());

        sort($codes);

        self::assertSame(
            ['ar', 'de', 'en', 'fa', 'fr', 'it', 'ku', 'ru', 'tr'],
            $codes,
            'I18N-NINE-REGISTRY-01: `docs/120` §2 dokuz dil sayıyor; kütük onlardan azını ya da fazlasını taşıyamaz.'
        );
    }

    // --- I18N-ENDONYM-02 --------------------------------------------------

    /**
     * ENDONİM ÇEVRİLMEZ ve KATALOGDAN GELMEZ.
     *
     * Sahibin gerekçesi kendi cümlesinde: "yabancı dil bilmeyen Türk, kendi
     * dilini kendi dilinde okuyabilsin." Bir kullanıcı arayüzü ANLAMADIĞI
     * için dil değiştirmeye gelir; dil adını arayüzün o anki diline
     * çevirmek, onu anladığı tek kelimeden mahrum bırakır — yani aracın
     * kendisini bozar.
     *
     * Bu yüzden endonim sabit veridir. Katalogdan gelseydi çeviri kilidi
     * açıldığı gün bir çevirmen "English"i "İngilizce" yapardı ve kimse
     * bunun bir kusur olduğunu fark etmezdi.
     */
    public function test_every_endonym_is_written_in_its_own_language(): void
    {
        $expected = [
            'en' => 'English',
            'tr' => 'Türkçe',
            'ar' => 'العربية',
            'ru' => 'Русский',
            'fa' => 'فارسی',
            'ku' => 'Kurdî',
            'de' => 'Deutsch',
            'fr' => 'Français',
            'it' => 'Italiano',
        ];

        foreach ($expected as $code => $endonym) {
            self::assertSame(
                $endonym,
                Language::from($code)->endonym(),
                "I18N-ENDONYM-02: `{$code}` endonimi `docs/120` §2 tablosundan sapıyor."
            );
        }
    }

    public function test_an_endonym_is_never_looked_up_through_the_translator(): void
    {
        /*
            Ölçüm mekanizmayı değil SONUCU ölçüyor: arayüz dili ne olursa
            olsun endonim aynı kalmalı. Türkçe bir arayüzde "English"in
            "İngilizce"ye dönmesi, tam olarak yasaklanan davranıştır.
        */
        $inEnglish = array_map(static fn (Language $l): string => $l->endonym(), Language::cases());

        app()->setLocale('tr');
        $inTurkish = array_map(static fn (Language $l): string => $l->endonym(), Language::cases());

        app()->setLocale('ar');
        $inArabic = array_map(static fn (Language $l): string => $l->endonym(), Language::cases());

        self::assertSame($inEnglish, $inTurkish, 'I18N-ENDONYM-02: endonim arayüz diliyle değişti — çevrilmiş demektir.');
        self::assertSame($inEnglish, $inArabic, 'I18N-ENDONYM-02: endonim arayüz diliyle değişti — çevrilmiş demektir.');
    }

    // --- I18N-NINE-DIRECTION-03 -------------------------------------------

    public function test_only_arabic_and_persian_are_right_to_left(): void
    {
        $rtl = array_values(array_map(
            static fn (Language $l): string => $l->value,
            array_filter(Language::cases(), static fn (Language $l): bool => $l->direction() === 'rtl'),
        ));

        sort($rtl);

        self::assertSame(
            ['ar', 'fa'],
            $rtl,
            'I18N-NINE-DIRECTION-03: `docs/120` §2 yalnız `ar` ve `fa` için RTL diyor. '
            .'Kürtçe bugün Kurmancî (Latin, LTR); Soranî ayrı bir dildir (`docs/120` §8).'
        );
    }

    public function test_the_script_of_a_language_is_recorded_not_guessed(): void
    {
        $expected = [
            'en' => 'Latn', 'tr' => 'Latn', 'ku' => 'Latn',
            'de' => 'Latn', 'fr' => 'Latn', 'it' => 'Latn',
            'ar' => 'Arab', 'fa' => 'Arab',
            'ru' => 'Cyrl',
        ];

        foreach ($expected as $code => $script) {
            self::assertSame($script, Language::from($code)->script(), "I18N-NINE-DIRECTION-03: `{$code}` yazı sistemi yanlış.");
        }
    }

    /**
     * YÖN BELGEYE UYGULANIR — `DocumentLocale` ile kütük aynı şeyi söylemeli.
     *
     * İki yerde iki farklı yön listesi, bir gün ayrışır ve ayrıştığı gün
     * Arapça bir sayfa soldan sağa çizilir. Ölçüm bu ayrışmayı imkânsız kılar.
     */
    public function test_the_document_direction_agrees_with_the_registry(): void
    {
        foreach (Language::cases() as $language) {
            self::assertSame(
                $language->direction(),
                DocumentLocale::direction($language->value),
                "I18N-NINE-DIRECTION-03: `{$language->value}` için belge yönü kütükten sapıyor."
            );
        }
    }

    // --- I18N-FLAG-EXCEPTION-04 -------------------------------------------

    /**
     * BAYRAK İSTİSNALARI SİYASİ BİR HASSASİYETTİR, ESTETİK TERCİH DEĞİL.
     *
     * `docs/120` §6: Arapça yirmiden fazla ülkenin dilidir; Kürtçenin devlet
     * bayrağı yoktur ve kullanılan işaretler siyasi iddia taşır; İngilizce
     * için "Birleşik Krallık mı ABD mi" sorusunun doğru cevabı yoktur.
     * Yanlış bayrak sessiz bir hata değildir — kullanıcıyı kimliği üzerinden
     * yanlış yerleştirir.
     */
    public function test_arabic_kurdish_and_english_carry_no_country_flag(): void
    {
        foreach (['ar', 'ku', 'en'] as $code) {
            $language = Language::from($code);

            self::assertFalse(
                $language->hasCountryFlag(),
                "I18N-FLAG-EXCEPTION-04: `{$code}` bir ülkeyle eşleşmiyor; bayrak siyasi bir iddia olurdu."
            );
            self::assertNull(
                $language->countryCode(),
                "I18N-FLAG-EXCEPTION-04: `{$code}` için bir ülke kodu yazılmış — `docs/120` §6 bunu yasaklıyor."
            );
        }
    }

    public function test_the_six_languages_that_map_to_one_country_carry_that_country(): void
    {
        $expected = ['tr' => 'TR', 'ru' => 'RU', 'fa' => 'IR', 'de' => 'DE', 'fr' => 'FR', 'it' => 'IT'];

        foreach ($expected as $code => $country) {
            $language = Language::from($code);

            self::assertTrue($language->hasCountryFlag(), "I18N-FLAG-EXCEPTION-04: `{$code}` ülke bayrağı taşımalı.");
            self::assertSame($country, $language->countryCode(), "I18N-FLAG-EXCEPTION-04: `{$code}` ülke kodu yanlış.");
        }
    }

    /**
     * HER DİLİN BİR BÖLGE İŞARETİ VARDIR — bayrağı olmayanın da.
     *
     * Nötr işaret, ülke iddiası taşımayan bir işarettir: dilin kendi kodu.
     * "Bayrağı yok, o hâlde boş bırak" demek, dil değiştiricide hizayı
     * bozardı ve kullanıcı bir dilin eksik olduğunu sanırdı.
     */
    public function test_every_language_has_a_region_mark_and_it_is_never_an_emoji(): void
    {
        foreach (Language::cases() as $language) {
            $mark = $language->regionMark();

            self::assertMatchesRegularExpression(
                '/^[A-Z]{2}$/',
                $mark,
                "I18N-FLAG-EXCEPTION-04: `{$language->value}` bölge işareti iki harfli olmalı."
            );

            /*
                EMOJİ YASAK (sahibin duran kuralı). Bayrak emojisi iki
                "regional indicator" kod noktasından oluşur (U+1F1E6–U+1F1FF);
                veri katmanında hiç bulunmamalı ki hiçbir yüzey onu kazara
                çizmesin.
            */
            self::assertSame(
                1,
                preg_match('/^[\x20-\x7E]+$/', $mark),
                "I18N-FLAG-EXCEPTION-04: `{$language->value}` bölge işareti ASCII değil — emoji sızmış olabilir."
            );
        }
    }

    // --- I18N-REGISTRY-SINGLE-05 ------------------------------------------

    /**
     * KÜTÜK TEK KAYNAKTIR — istemci tarafı ondan SAPAMAZ.
     *
     * Dil değiştirici tarayıcıda çizilir ve kendi dil listesini taşırsa iki
     * gerçek doğar: sunucunun tanıdığı diller ile ekranda görünen diller.
     * Ayrıştıkları gün kullanıcı, sunucunun hiç tanımadığı bir dile tıklar.
     */
    public function test_the_javascript_registry_repeats_the_php_registry_exactly(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/i18n/languages.ts'));

        foreach (Language::cases() as $language) {
            self::assertStringContainsString(
                "'{$language->value}'",
                $source,
                "I18N-REGISTRY-SINGLE-05: `{$language->value}` istemci kütüğünde yok."
            );
            self::assertStringContainsString(
                $language->endonym(),
                $source,
                "I18N-REGISTRY-SINGLE-05: `{$language->value}` endonimi istemci kütüğünde farklı yazılmış."
            );
            self::assertStringContainsString(
                $language->regionMark(),
                $source,
                "I18N-REGISTRY-SINGLE-05: `{$language->value}` bölge işareti istemci kütüğünde yok."
            );
        }
    }
}
