<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use Tests\Support\CorporateIdentityTokens;
use Tests\TestCase;

/**
 * KIMLIK-01…03 — kurumsal palet CESUR ama OKUNUR.
 *
 * ── Neden bu kapı var ────────────────────────────────────────────────────
 *
 * Sahibin isteği açık: *"bir uzay teknolojileri şirketi gibi, abartı dursun,
 * görünsün, hissettirsin."* Bu isteğin en kolay yanlış cevabı, koyu bir
 * zemine soluk bir gri yazmaktır: fotoğrafı güzel çıkar, okunmaz.
 *
 * `docs/145` §2'nin cümlesi bu yüzden şu: **cesur olmak okunmaz olmak
 * değildir.** Bu dosya o cümlenin ölçüsüdür ve palet DEĞİŞTİĞİNDE de
 * ölçmeye devam eder — bir gün biri moru bir ton açarsa, o tonun altındaki
 * beyaz yazının hâlâ okunduğunu kimsenin gözle kontrol etmesi gerekmez.
 *
 * ── Kullanıcı yolculuğu ──────────────────────────────────────────────────
 *
 * Bir kebapçı, öğle güneşinde, telefonundan zabuno.com'u açıyor. Ekran
 * parlıyor, gözlük takmıyor. "Hesap aç" düğmesinin üstündeki yazıyı
 * okuyamazsa kaydolmaz — ve kimse bunu bir hata raporu olarak görmez, çünkü
 * ortada bir hata mesajı yoktur, yalnız gelmeyen bir müşteri vardır.
 *
 * Ölçülen eski değer tam olarak buydu: marka sarısı düğmenin üstünde beyaz
 * yazı, 1.73:1. Kimlik kararı onu 5.84:1 yaptı.
 *
 * ── Ne ölçülmüyor ────────────────────────────────────────────────────────
 *
 * Yarı saydam jetonlar (ışıma, cam, gölge). Bir `color-mix()` değerinin
 * kontrastı ARKASINDAKİ yüzeye bağlıdır ve o yüzey CSS'te yazılı değildir.
 * Onları tahmin edip yeşil göstermek, ölçmemekten kötü olurdu; ölçülemeyen
 * bir sonuç "bilinmiyor"dur ve `docs/145` §8'de öyle kayıtlıdır.
 */
final class CorporateIdentityContrastTest extends TestCase
{
    private const IDENTITY_FILE = 'resources/css/site-identity.css';

    /**
     * METİN eşleşmeleri: `[mürekkep, zemin, asgari oran, ne olduğu]`.
     *
     * Asgariler WCAG 2.2'den: normal metin 4.5, gelişmiş (AAA) 7. Gövde
     * metni ve zeminleri için 7 seçildi çünkü kurumsal sayfa TELEFONDA ve
     * GÜNEŞTE okunuyor; asgariyi hedef saymak, en kötü koşulda asgarinin
     * altına düşmek demektir.
     *
     * @return list<array{0: string, 1: string, 2: float, 3: string}>
     */
    private function textPairs(): array
    {
        return [
            ['--zc-text', '--zc-canvas', 7.0, 'gövde metni, sayfa zemini'],
            ['--zc-text', '--zc-raised', 7.0, 'gövde metni, kart zemini'],
            ['--zc-text', '--zc-sunken', 7.0, 'gövde metni, gömülü zemin'],
            ['--zc-text-soft', '--zc-canvas', 4.5, 'ikincil metin, sayfa zemini'],
            ['--zc-text-soft', '--zc-raised', 4.5, 'ikincil metin, kart zemini'],

            ['--zc-on-action', '--zc-action', 4.5, 'birincil düğmenin yazısı'],
            ['--zc-on-support', '--zc-support', 4.5, 'ikincil dolgunun yazısı'],
            ['--zc-on-accent', '--zc-accent', 4.5, 'marka vurgusunun yazısı'],
            ['--zc-on-neutral', '--zc-neutral', 4.5, 'nötr dolgunun yazısı'],

            ['--zc-on-info', '--zc-info', 4.5, 'bilgi rozetinin yazısı'],
            ['--zc-on-success', '--zc-success', 4.5, 'başarı rozetinin yazısı'],
            ['--zc-on-warning', '--zc-warning', 4.5, 'uyarı rozetinin yazısı'],
            ['--zc-on-error', '--zc-error', 4.5, 'hata rozetinin yazısı'],

            /*
                DURUM RENKLERİ METİN OLARAK DA KULLANILIR.

                `site-identity.css` §5 `--fg-danger`, `--fg-success` ve
                `--fg-warning`i doğrudan bu jetonlara bağlıyor; yani bunlar
                yalnız bir rozetin zemini değil, bir cümlenin mürekkebi de
                oluyor. Ölçülmeseydi, açık kipte kırmızı bir hata cümlesi
                beyaz zeminde okunmayabilirdi.
            */
            ['--zc-error', '--zc-canvas', 4.5, 'hata cümlesi, sayfa zemini'],
            ['--zc-success', '--zc-canvas', 4.5, 'başarı cümlesi, sayfa zemini'],
            ['--zc-warning', '--zc-canvas', 4.5, 'uyarı cümlesi, sayfa zemini'],
            ['--zc-support', '--zc-canvas', 4.5, 'bağlantı metni, sayfa zemini'],
        ];
    }

    /**
     * METİN OLMAYAN eşleşmeler: WCAG 1.4.11, asgari 3:1.
     *
     * Bir kenarın ya da düğme yüzeyinin işi okunmak değil, NEREDE BİTTİĞİNİ
     * söylemektir. 3:1 o işin asgarisidir ve dekoratif ayraçlar bu listede
     * bilerek YOKTUR — bir bölüm çizgisi bilgi taşımaz.
     *
     * @return list<array{0: string, 1: string, 2: float, 3: string}>
     */
    private function surfacePairs(): array
    {
        return [
            ['--zc-edge-control', '--zc-canvas', 3.0, 'kontrol kenarı, sayfa zemini'],
            ['--zc-edge-control', '--zc-raised', 3.0, 'kontrol kenarı, kart zemini'],
            ['--zc-focus', '--zc-canvas', 3.0, 'odak halkası, sayfa zemini'],
            ['--zc-focus', '--zc-raised', 3.0, 'odak halkası, kart zemini'],
            ['--zc-action', '--zc-canvas', 3.0, 'birincil düğmenin yüzeyi'],
            ['--zc-raised', '--zc-canvas', 1.05, 'kart, zeminden ayrışıyor'],
        ];
    }

    /**
     * DERİN BANT — tema tanımaz, bu yüzden ayrı ölçülür.
     *
     * Kahraman ve harekete çağrı bandı açık kipte de koyudur (`docs/145`
     * §3). İçindeki metin `--zc-text` DEĞİL `--zc-deep-text`tir; ölçülmemiş
     * olsaydı, açık kipi seçmiş bir ziyaretçi kahraman bölümünde koyu
     * mürekkep koyu zemin görürdü.
     *
     * @return list<array{0: string, 1: string, 2: float, 3: string}>
     */
    private function deepPairs(): array
    {
        return [
            ['--zc-deep-text', '--zc-deep-canvas', 7.0, 'derin bandın gövde metni'],
            ['--zc-deep-text', '--zc-deep-raised', 7.0, 'derin banttaki kartın metni'],
            ['--zc-deep-text-soft', '--zc-deep-canvas', 4.5, 'derin bandın ikincil metni'],
            ['--zc-deep-accent', '--zc-deep-canvas', 4.5, 'derin banttaki marka vurgusu'],
            ['--zc-deep-support', '--zc-deep-canvas', 4.5, 'derin banttaki bağlantı'],
            ['--zc-on-action', '--zc-action', 4.5, 'derin banttaki düğmenin yazısı'],
        ];
    }

    private function tokens(): CorporateIdentityTokens
    {
        return new CorporateIdentityTokens(
            (string) file_get_contents(base_path(self::IDENTITY_FILE))
        );
    }

    // --- KIMLIK-01 -------------------------------------------------------------

    public function test_raw_colour_lives_only_in_the_primitive_block(): void
    {
        /*
            TEK HAM RENK KAYNAĞI.

            §1 dışında bir yerde yazılan bir hex, kimliğin ikinci bir kaynağı
            olurdu: bir gün moru koyulaştıran kişi §1'i değiştirir, o kaçak
            değer olduğu yerde kalır ve sayfa iki farklı mora bölünür.

            Ölçüm yorumsuz kaynakta yapılır: bir gerekçe metninde geçen
            `#ffb300` bir renk SEÇİMİ değil, bir açıklamadır.
        */
        $css = CorporateIdentityTokens::withoutComments(
            (string) file_get_contents(base_path(self::IDENTITY_FILE))
        );

        $end = strpos($css, "\n}");

        self::assertIsInt($end, 'İlkel palet bloğu (§1) bulunamadı — ölçüm dayanaksız.');

        $afterPrimitives = substr($css, $end);

        self::assertStringNotContainsString(
            '#',
            $afterPrimitives,
            'KIMLIK-01: §1 dışında ham bir renk var. Kurumsal kimliğin tek ham renk kaynağı '
            .'ilkel palet bloğudur; geri kalan her şey `var(--zc-…)` ya da `color-mix()` ile '
            .'ona bağlanır.'
        );

        /*
            KABUK VE TEMA DOSYALARINDA HİÇ HAM RENK YOK.

            Kimlik katmanı doğru yazılıp kabuk yanlış yazılırsa sonuç yine
            iki kaynaktır — üstelik bu kez fark edilmesi daha zor, çünkü
            kabuktaki bir hex "kimlik dosyası temiz" diye bakılan yerde
            görünmez.
        */
        foreach (['resources/css/site-shell.css', 'resources/css/daisy-theme.css'] as $relative) {
            $other = CorporateIdentityTokens::withoutComments(
                (string) file_get_contents(base_path($relative))
            );

            foreach (['#', 'rgb(', 'hsl(', 'oklch('] as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $other,
                    "KIMLIK-01: [{$relative}] ham bir renk taşıyor ([{$needle}]). "
                    .'Kurumsal yüzeyde renk yalnız jetondan gelir.'
                );
            }
        }
    }

    // --- KIMLIK-02 -------------------------------------------------------------

    public function test_every_text_pairing_is_readable_in_both_modes(): void
    {
        $tokens = $this->tokens();
        $measured = 0;

        foreach ([false, true] as $dark) {
            $mode = $dark ? 'koyu' : 'açık';

            foreach ([...$this->textPairs(), ...$this->deepPairs()] as [$ink, $ground, $minimum, $what]) {
                $ratio = $tokens->contrast($ink, $ground, $dark);

                self::assertNotNull(
                    $ratio,
                    "KIMLIK-02: [{$mode}] kipte [{$ink}] / [{$ground}] ÇÖZÜLEMEDİ ({$what}). "
                    .'Ölçülemeyen bir sonuç yeşil gösterilmez: jeton zinciri düz bir renge varmıyor.'
                );

                self::assertGreaterThanOrEqual(
                    $minimum,
                    $ratio,
                    sprintf(
                        'KIMLIK-02: [%s] kipte %s okunmuyor — [%s] / [%s] = %.2f:1, asgari %.1f:1. '
                        .'Cesur olmak okunmaz olmak değildir (`docs/145` §2).',
                        $mode,
                        $what,
                        $ink,
                        $ground,
                        $ratio,
                        $minimum,
                    )
                );

                $measured++;
            }
        }

        self::assertGreaterThan(40, $measured, 'Beklenenden az eşleşme ölçüldü — liste boşalmış olabilir.');
    }

    // --- KIMLIK-03 -------------------------------------------------------------

    public function test_every_non_text_boundary_is_visible_in_both_modes(): void
    {
        $tokens = $this->tokens();

        foreach ([false, true] as $dark) {
            $mode = $dark ? 'koyu' : 'açık';

            foreach ($this->surfacePairs() as [$mark, $ground, $minimum, $what]) {
                $ratio = $tokens->contrast($mark, $ground, $dark);

                self::assertNotNull(
                    $ratio,
                    "KIMLIK-03: [{$mode}] kipte [{$mark}] / [{$ground}] ÇÖZÜLEMEDİ ({$what})."
                );

                self::assertGreaterThanOrEqual(
                    $minimum,
                    $ratio,
                    sprintf(
                        'KIMLIK-03: [%s] kipte %s görünmüyor — [%s] / [%s] = %.2f:1, asgari %.1f:1 '
                        .'(WCAG 1.4.11).',
                        $mode,
                        $what,
                        $mark,
                        $ground,
                        $ratio,
                        $minimum,
                    )
                );
            }
        }
    }
}
