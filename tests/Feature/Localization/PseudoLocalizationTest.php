<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Application\Localization\Port\TranslationPort;
use App\Support\Localization\PseudoLocalizer;
use Tests\TestCase;

/**
 * `docs/121` §4 — sahte-yerelleştirme: çeviri YAPMADAN çeviriye hazırlığı ölçmek.
 *
 * Sahibin sırası bağlayıcı: çeviri EN SONDA. Ama "en başında tercüme için
 * gereken tüm önlemleri alacağız". Sahte-yerelleştirme o önlemlerin
 * dördünü (Ö1, Ö2, Ö3, Ö7) tek bir kelime çevrilmeden ölçen tek araçtır.
 *
 * BU BİR ÇEVİRİ DEĞİLDİR. Hiçbir dile ait değil, hiçbir çevirmen çalışmadı,
 * kilit açılmadı, `shipped_locales` genişlemedi. Yalnız bir ÖLÇÜM DİLİDİR.
 *
 * Requirement ID'leri: I18N-PSEUDO-TRANSFORM-15, I18N-PSEUDO-EXPAND-16,
 * I18N-PSEUDO-PLACEHOLDER-17, I18N-PSEUDO-NEVER-PROD-18, I18N-PSEUDO-OFF-19.
 */
final class PseudoLocalizationTest extends TestCase
{
    // --- I18N-PSEUDO-TRANSFORM-15 -----------------------------------------

    /**
     * AKSANLI HARF, GÖMÜLÜ METNİ AÇIĞA ÇIKARIR (Ö1).
     *
     * Katalogdan geçen her metin dönüşür. Ekranda dönüşmemiş bir kelime
     * kalıyorsa o kelime KODDA gömülüdür ve çeviri kilidi açıldığı gün
     * çevrilemez — çünkü katalogda hiç görünmez.
     */
    public function test_a_catalogue_string_comes_back_visibly_transformed(): void
    {
        $output = PseudoLocalizer::transform('Save changes');

        self::assertNotSame('Save changes', $output, 'I18N-PSEUDO-TRANSFORM-15: metin hiç dönüşmemiş.');
        self::assertStringStartsWith('⟦', $output, 'I18N-PSEUDO-TRANSFORM-15: baş ayraç yok — kesilen cümle görünmez.');
        self::assertStringEndsWith('⟧', $output, 'I18N-PSEUDO-TRANSFORM-15: son ayraç yok.');
        self::assertStringContainsString('Şåvê', $output, 'I18N-PSEUDO-TRANSFORM-15: harfler aksanlanmamış.');
    }

    /**
     * AYRAÇ ORTASINDAN KESİLEN CÜMLEYİ AÇIĞA ÇIKARIR (Ö2, Ö5).
     *
     * `"Toplam " . $n . " ürün"` gibi birleştirilerek kurulan bir cümle
     * ekranda `⟦…⟧ 5 ⟦…⟧` olarak görünür: üç ayrı katalog anahtarı olduğu
     * gözle anlaşılır. Tek bir ayraç çifti görülüyorsa cümle tektir.
     */
    public function test_each_catalogue_entry_carries_its_own_pair_of_brackets(): void
    {
        $joined = PseudoLocalizer::transform('Total ').'5'.PseudoLocalizer::transform(' items');

        self::assertSame(
            2,
            substr_count($joined, '⟦'),
            'I18N-PSEUDO-TRANSFORM-15: birleştirilmiş cümle tek parça görünüyor — kusur gizlenir.'
        );
    }

    // --- I18N-PSEUDO-EXPAND-16 --------------------------------------------

    /**
     * DOLGU, UZAYAN METNİN KIRDIĞI DÜZENİ AÇIĞA ÇIKARIR (Ö7).
     *
     * Almanca İngilizceden ortalama %35 uzundur ve kısa etiketlerde bu oran
     * %100'e çıkar. Sabit genişlikli bir düğme İngilizcede güzel, Almancada
     * kırpılmış görünür — ve 320 pikselde bu iki katı acıtır.
     *
     * Ölçüm oranı ölçer, belirli bir uzunluğu değil: dolgu oranı bir ayardır
     * ve `docs/121` §7'de değişebileceği yazılı.
     */
    public function test_a_short_label_grows_at_least_by_the_configured_ratio(): void
    {
        $source = 'Save';
        $output = PseudoLocalizer::transform($source);

        // Ayraçlar ölçüme girmez: onlar dolgu değil, sınır işaretidir.
        $inner = mb_substr($output, 1, mb_strlen($output) - 2);

        self::assertGreaterThanOrEqual(
            (int) ceil(mb_strlen($source) * 1.35),
            mb_strlen($inner),
            'I18N-PSEUDO-EXPAND-16: metin Almancanın ortalama uzamasını taşımıyor — dar ekranda kırılan yer görünmez.'
        );
    }

    // --- I18N-PSEUDO-PLACEHOLDER-17 ---------------------------------------

    /**
     * YER TUTUCU DÖNÜŞMEZ (Ö3).
     *
     * `{count}` aksanlansaydı çalışma anında değiştirilemezdi ve ekranda ham
     * bir `{çôûñt}` kalırdı. Ölçüm aracının kendisi ürünü bozarsa, ölçtüğü
     * hiçbir şeye güvenilmez.
     */
    public function test_named_placeholders_survive_untouched(): void
    {
        $output = PseudoLocalizer::transform('{count} items in {menu}');

        self::assertStringContainsString('{count}', $output, 'I18N-PSEUDO-PLACEHOLDER-17: yer tutucu bozuldu.');
        self::assertStringContainsString('{menu}', $output, 'I18N-PSEUDO-PLACEHOLDER-17: yer tutucu bozuldu.');
    }

    public function test_an_empty_string_stays_empty(): void
    {
        // Boş bir dize bir arayüz durumudur, çevrilecek bir metin değil.
        self::assertSame('', PseudoLocalizer::transform(''));
    }

    // --- I18N-PSEUDO-NEVER-PROD-18 ----------------------------------------

    /**
     * ÜRETİMDE ASLA AÇILAMAZ — ayar açık olsa bile.
     *
     * Bu bir ölçüm kipidir ve müşterinin ekranında `⟦Şåvê çhàñgêš⟧` görmesi,
     * ürünün bozulduğu anlamına gelir. Bir ortam değişkeninin yanlışlıkla
     * üretime taşınması gerçek bir olaydır; kapı bu yüzden ayarın kendisine
     * DEĞİL, ortama bakar.
     */
    public function test_production_refuses_pseudo_localization_even_when_the_setting_is_on(): void
    {
        config()->set('i18n.pseudo_localization', true);
        app()->detectEnvironment(static fn (): string => 'production');

        self::assertFalse(
            PseudoLocalizer::isEnabled(),
            'I18N-PSEUDO-NEVER-PROD-18: üretimde sahte-yerelleştirme açıldı — müşteri ekranında ölçüm dili görünürdü.'
        );
    }

    public function test_it_is_enabled_in_development_when_the_setting_is_on(): void
    {
        config()->set('i18n.pseudo_localization', true);
        app()->detectEnvironment(static fn (): string => 'local');

        self::assertTrue(PseudoLocalizer::isEnabled());
    }

    // --- I18N-PSEUDO-OFF-19 -----------------------------------------------

    /**
     * VARSAYILAN KAPALIDIR ve çevirmen zincirini değiştirmez.
     *
     * Kapalıyken çevirici tam olarak eskisi gibi davranır; ölçüm aracı
     * ürünün normal davranışına hiçbir maliyet bindirmez.
     */
    public function test_the_default_is_off_and_the_translator_behaves_normally(): void
    {
        self::assertFalse(config('i18n.pseudo_localization'), 'I18N-PSEUDO-OFF-19: ölçüm kipi varsayılan olarak açık.');

        $translated = app(TranslationPort::class)->translate('auth', 'auth.login.heading', 'en');

        self::assertStringNotContainsString('⟦', $translated, 'I18N-PSEUDO-OFF-19: kapalıyken ölçüm dili sızmış.');
    }

    /**
     * AÇIKKEN ÇEVİRİCİNİN KENDİSİ DÖNÜŞTÜRÜR.
     *
     * Dönüşümü her çağıran yerin ayrı ayrı yapması gerekseydi, unutulan her
     * çağrı "gömülü metin" gibi görünürdü — yani ölçüm aracı yalancı pozitif
     * üretirdi.
     */
    public function test_when_enabled_every_translated_string_comes_back_in_the_measurement_language(): void
    {
        config()->set('i18n.pseudo_localization', true);
        app()->detectEnvironment(static fn (): string => 'local');

        // Bağlama yeniden kurulmalı: kip, çevirici çözülürken okunur.
        app()->forgetInstance(TranslationPort::class);

        $translated = app(TranslationPort::class)->translate('auth', 'auth.login.heading', 'en');

        self::assertStringStartsWith(
            '⟦',
            $translated,
            'I18N-PSEUDO-OFF-19: kip açıkken çevirici hâlâ kaynak metni döndürüyor.'
        );
    }
}
