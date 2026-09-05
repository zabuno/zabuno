<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Application\Localization\Port\TranslationPort;
use App\Infrastructure\Localization\MoFileTranslator;
use RuntimeException;
use Tests\TestCase;

/**
 * CORE-08 — PO→MO→JSON zincirinin PHP ucu.
 *
 * `modules/core-localization.md` Stage 1 kabul kriteri: "PO→MO→JSON
 * projeksiyon zincirinin tüm altı katalog için tutarlılık testi (scaffold+
 * wiring çalışıyor, English içerik complete)".
 *
 * Requirement ID'leri: I18N-SIX-CATALOGS-10, I18N-MO-READABLE-11,
 * I18N-FALLBACK-CHAIN-12, I18N-NO-GETTEXT-EXT-13, I18N-MEASURABLE-14.
 */
final class TranslationPipelineTest extends TestCase
{
    /**
     * CORE-08'in şart koştuğu TABAN — tavan değil.
     *
     * Liste "tam olarak bu altı dil" anlamına geliyordu ve ölçüldüğünde
     * (`docs/120` §7) altyapının dokuz dili sorunsuz taşıdığı görüldü. Bir
     * dilin eksilmesi hâlâ kapıyı kırar; eklenmesi kırmaz, çünkü ölçüm
     * derlenmiş dizinlerden okunur.
     */
    private const REQUIRED_LOCALES = ['en', 'tr', 'de', 'fr', 'ar', 'ru'];

    private function translator(): TranslationPort
    {
        return app(TranslationPort::class);
    }

    /** @return list<string> */
    private function domains(): array
    {
        $domains = [];

        foreach (glob(base_path('lang/mo/en/*.mo')) ?: [] as $path) {
            $domains[] = basename($path, '.mo');
        }

        sort($domains);

        return $domains;
    }

    // --- I18N-SIX-CATALOGS-10 ---------------------------------------------

    /**
     * HER TANIMLI DİL, HER ALAN İÇİN DERLENMİŞ OLMALI.
     *
     * Ölçüm artık `lang/mo/` altındaki dizinlerden okunuyor, elle yazılmış
     * bir listeden değil. Fark somut: onuncu bir dil eklendiğinde eski
     * hâliyle bu test SESSİZCE geçerdi — yeni dilin yarım derlenmiş olması
     * hiç fark edilmezdi, çünkü test onu hiç aramıyordu. Ölçmediği bir şey
     * için yeşil veren bir kapı, kapı değildir.
     */
    public function test_every_domain_is_compiled_for_every_declared_catalogue(): void
    {
        $domains = $this->domains();
        $locales = $this->compiledLocales();

        self::assertNotEmpty($domains, 'I18N-SIX-CATALOGS-10: hiç derlenmiş katalog yok — `node scripts/i18n build` çalıştırılmamış.');

        foreach (self::REQUIRED_LOCALES as $required) {
            self::assertContains(
                $required,
                $locales,
                "I18N-SIX-CATALOGS-10: CORE-08 tabanındaki `{$required}` kataloğu derlenmemiş."
            );
        }

        foreach ($domains as $domain) {
            foreach ($locales as $locale) {
                self::assertFileExists(
                    base_path("lang/mo/{$locale}/{$domain}.mo"),
                    "I18N-SIX-CATALOGS-10: {$domain}/{$locale} projeksiyonu eksik. Katalog iskeleti Stage 1'den itibaren tamdır; içerik doluluğu kademelidir, dosya varlığı değil."
                );
            }
        }
    }

    /**
     * Derlenmiş katalog dilleri — diskten okunur, tahmin edilmez.
     *
     * @return list<string>
     */
    private function compiledLocales(): array
    {
        $locales = [];

        foreach (glob(base_path('lang/mo/*'), GLOB_ONLYDIR) ?: [] as $path) {
            $locales[] = basename($path);
        }

        sort($locales);

        return $locales;
    }

    public function test_the_source_catalog_is_complete_for_every_domain(): void
    {
        foreach ($this->domains() as $domain) {
            self::assertSame(
                0,
                $this->translator()->missingCount($domain, 'en'),
                "I18N-SIX-CATALOGS-10: kaynak katalog `en` eksiksiz olmalı ({$domain})."
            );
        }
    }

    // --- I18N-MO-READABLE-11 ----------------------------------------------

    public function test_a_compiled_catalog_reads_back_exactly_what_was_written(): void
    {
        $table = MoFileTranslator::parse(
            (string) file_get_contents(base_path('lang/mo/tr/menu.mo'))
        );

        self::assertArrayHasKey('menu.loading', $table);
        self::assertSame('Menü yükleniyor…', $table['menu.loading'], 'I18N-MO-READABLE-11: MO içeriği kayıpsız okunmalı — UTF-8 dâhil.');
    }

    public function test_a_file_that_is_not_a_mo_file_is_refused_instead_of_silently_read_as_empty(): void
    {
        $this->expectException(RuntimeException::class);

        // Sessizce boş tablo dönmek, çevirinin kaybolduğunu gizlerdi.
        MoFileTranslator::parse(str_repeat('x', 64));
    }

    // --- I18N-FALLBACK-CHAIN-12 -------------------------------------------

    public function test_a_translated_string_is_used_and_placeholders_are_filled(): void
    {
        self::assertSame(
            'Tatlılar içindeki ürünler',
            $this->translator()->translate('menu', 'menu.category.items.label', 'tr', ['name' => 'Tatlılar']),
        );
    }

    public function test_an_untranslated_locale_falls_back_to_the_source_text_not_the_key(): void
    {
        self::assertSame(
            'Loading menu…',
            $this->translator()->translate('menu', 'menu.loading', 'de'),
            'I18N-FALLBACK-CHAIN-12: scaffold bir dil, kullanıcıya ham anahtar değil İngilizce metin göstermeli.'
        );
    }

    public function test_an_unknown_key_surfaces_as_itself_so_the_gap_is_visible(): void
    {
        self::assertSame(
            'menu.this.key.does.not.exist',
            $this->translator()->translate('menu', 'menu.this.key.does.not.exist', 'tr'),
        );
    }

    public function test_an_unknown_locale_still_produces_the_source_text(): void
    {
        self::assertSame(
            'Loading menu…',
            $this->translator()->translate('menu', 'menu.loading', 'zz'),
        );
    }

    // --- I18N-NO-GETTEXT-EXT-13 -------------------------------------------

    public function test_the_translator_never_depends_on_the_gettext_extension_or_process_locale(): void
    {
        $source = (string) file_get_contents(app_path('Infrastructure/Localization/MoFileTranslator.php'));

        foreach (['setlocale(', 'gettext(', 'dgettext(', 'bindtextdomain('] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $source,
                "I18N-NO-GETTEXT-EXT-13: `{$forbidden}` süreç genelinde durum değiştirir; çok kiracılı bir istekte bir tenant'ın dili diğerine sızabilir."
            );
        }
    }

    // --- I18N-MEASURABLE-14 -----------------------------------------------

    public function test_missing_translations_are_counted_rather_than_guessed(): void
    {
        $missing = $this->translator()->missingCount('menu', 'de');

        self::assertGreaterThan(
            0,
            $missing,
            'I18N-MEASURABLE-14: çevrilmemiş bir dilde eksik sayısı ölçülebilir olmalı.'
        );
    }

    // --- I18N-TARGET-OPTIONAL-15 ------------------------------------------

    /**
     * Hedef dilin EKSİK olması beklenen durumdur ve derlemeyi kıramaz.
     *
     * Buraya "Türkçe katalog tamdır" gibi bir iddia EKLENMEYECEK. Sebebi
     * güvenlik değil, yön: böyle bir kapı, İngilizce'ye yeni bir dize
     * eklendiği anda kırılır ve tek çıkış yolu olarak makine çevirisini
     * dayatır. Sahiplik kararı (`docs/13` §Kaynak dil) bunun tersidir —
     * çeviriyi sahibi yazar, kod yazmaz.
     *
     * Korunan şey kaynak taraftadır ve yerinde durur:
     * `test_the_source_catalog_is_complete_for_every_domain` her alan adı
     * için `en` kataloğunun eksiksiz olmasını şart koşar. Ekranda görünen
     * bir dize kaynak katalogda yoksa hiçbir dile çevrilemez; asıl kayıp
     * odur, hedef dildeki boşluk değil.
     */
    public function test_an_untranslated_target_locale_is_a_recorded_state_not_a_failure(): void
    {
        $untouched = $this->translator()->missingCount('menu', 'ru');

        self::assertGreaterThan(
            0,
            $untouched,
            'I18N-TARGET-OPTIONAL-15: hiç çevrilmemiş dil ölçülebilir bir boşluk göstermeli.'
        );

        self::assertSame(
            0,
            $this->translator()->missingCount('menu', 'en'),
            'I18N-TARGET-OPTIONAL-15: hedef dil boşken bile kaynak katalog tam kalmalı.'
        );
    }
}
