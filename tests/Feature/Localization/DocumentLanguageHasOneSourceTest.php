<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use Tests\TestCase;

/**
 * FF-249 — `<html lang>` ile gövdenin AYRIŞMASI yazılamaz.
 *
 * ── Neden davranış testi yetmiyor ────────────────────────────────────────
 *
 * `PrintedLanguageMatchesDocumentTest` kusuru bugünkü sayfalarda ölçüyor.
 * Ama kusurun kendisi bir sayfa hatası değildi, bir ŞEKİL hatasıydı: belge
 * dili ile gövde dili İKİ AYRI KAYNAKTAN geliyordu ve iki kaynak varken
 * tutarlılık dikkate kalır. Dikkat bir kapı değildir; yarın eklenen bir sayfa
 * aynı kusuru sessizce geri getirebilirdi.
 *
 * Somut hâli şuydu — kabukta:
 *
 *     <html lang="{{ $pageLocale ?? DocumentLocale::tag() }}">
 *
 * ve denetleyicilerde, ondan bağımsız:
 *
 *     SiteText::pick($request->getPreferredLanguage(['en', 'tr']))
 *
 * `??`nin sağ tarafı gövdeyi çizen dili hiç bilmiyordu. Bu test o şekli
 * yasaklar: kurumsal kabukta belge dilinin tek kaynağı `PageLanguage`tir ve
 * kabuğun dil seçimi ürünün SUNULAN dil listesinden gelir.
 *
 * Requirement ID'leri: I18N-LANG-ONE-SOURCE-01, I18N-SHELL-NO-OWN-NEGOTIATION-02.
 */
final class DocumentLanguageHasOneSourceTest extends TestCase
{
    /**
     * Kurumsal kabuk `<html lang>`i YALNIZ tek nesneden yazar.
     *
     * ── I18N-LANG-ONE-SOURCE-01 ──────────────────────────────────────────
     */
    public function test_the_corporate_shell_writes_the_document_language_from_one_object_only(): void
    {
        $layout = (string) file_get_contents(resource_path('views/public/layout.blade.php'));

        preg_match_all('/<html\s+lang="([^"]*)"/', $layout, $matches);

        self::assertSame(
            ['{{ $lang->documentTag() }}'],
            $matches[1],
            'I18N-LANG-ONE-SOURCE-01: kabuk belge dilini `$lang->documentTag()` dışında bir kaynaktan yazıyor. '
            .'`??` ile ikinci bir kaynağa düşmek, gövdeyi çizen dilden bağımsız bir etiket basmaktır — '
            .'ölçülen kusur (2026-09-08) tam olarak buydu.'
        );
    }

    /**
     * Kurumsal site kendi dil pazarlığını YAPMAZ.
     *
     * ── I18N-SHELL-NO-OWN-NEGOTIATION-02 ─────────────────────────────────
     *
     * Pazarlık tek yerde yapılır (`NegotiateLocale`) ve tek listeyi okur
     * (`i18n.shipped_locales`). Bir denetleyicinin kendi listesiyle ikinci bir
     * seçim yapması, aynı istekte iki farklı "seçilmiş dil" demekti — ve
     * ikisi de aynı ekranda görünüyordu.
     *
     * YARDIM MAKALESİ İSTİSNADIR ve gerekçesi ayrıdır: orada seçilen şey
     * arayüz dili değil, hangi BELGENİN sunulacağıdır. O belge bütün hâlinde
     * yazılmıştır ve varlığı kendi kapısıyla zorlanır (`HelpContentTest`).
     */
    public function test_no_public_site_surface_negotiates_the_interface_language_on_its_own(): void
    {
        $offenders = [];

        foreach ($this->phpFilesUnder([app_path('Http/Controllers'), app_path('Support/Site')]) as $file) {
            /*
                YORUMLAR AYIKLANIR, yoksa kapı kendi gerekçesini kusur sayar:
                bu düzeltmenin bıraktığı açıklamalar kaldırılan çağrıyı ADIYLA
                anıyor ve bir kaydın "burada şu vardı" demesi, o şeyin hâlâ
                orada olduğu anlamına gelmez. Ölçülen şey ÇALIŞAN koddur.
            */
            $source = $this->executableSource($file);

            if (! str_contains($source, 'getPreferredLanguage(')) {
                continue;
            }

            /*
                Kabuk metnini çizen çağrılar yasaklanır; hangi BELGENİN
                sunulacağını ve ziyaretçinin hangi dilde YAZDIĞINI soranlar
                değil. Ayrımı yapan şey çağrının kendisi değil, sonucunun
                nereye gittiği — bu yüzden izin verilen iki kullanım adıyla
                anılıyor ve her biri kendi dosyasında gerekçelendirilmiş.
            */
            if (str_contains($source, 'HelpLibrary::SUPPORTED')) {
                continue;
            }

            if (str_contains($source, 'NewSupportRequest')) {
                continue;
            }

            $offenders[] = str_replace(base_path().'/', '', $file);
        }

        self::assertSame(
            [],
            $offenders,
            'I18N-SHELL-NO-OWN-NEGOTIATION-02: şu dosyalar kendi dil pazarlığını yapıyor: '
            .implode(', ', $offenders).'. Arayüz dili `NegotiateLocale`de bir kez seçilir ve '
            .'`i18n.shipped_locales`i okur; ikinci bir seçim o kararı sessizce ezer.'
        );
    }

    /** Dosyanın yorumsuz, yalnız çalışan kısmı. */
    private function executableSource(string $file): string
    {
        $out = '';

        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }

                $out .= $token[1];

                continue;
            }

            $out .= $token;
        }

        return $out;
    }

    /**
     * @param  list<string>  $roots
     * @return list<string>
     */
    private function phpFilesUnder(array $roots): array
    {
        $files = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            /** @var iterable<\SplFileInfo> $iterator */
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($iterator as $entry) {
                if ($entry->isFile() && $entry->getExtension() === 'php') {
                    $files[] = $entry->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }
}
