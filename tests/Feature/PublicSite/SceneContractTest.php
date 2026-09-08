<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Tests\TestCase;

/**
 * SAHNE SÖZLEŞMESİ — `docs/146`.
 *
 * ── SAHİBİN EMRİ ─────────────────────────────────────────────────────────
 *
 * *"Bir uzay teknolojileri şirketi gibi, abartı dursun, görünsün,
 * hissettirsin."* (2026-09-08)
 *
 * ── NE ÖLÇÜLÜYOR, NE ÖLÇÜLMÜYOR ──────────────────────────────────────────
 *
 * Burada bir kare bile çizilmiyor. "Sahne akıcı mı", "yıldızlar güzel mi",
 * "320 pikselde taşıyor mu" soruları burada SORULAMAZ; cevapları
 * `scripts/scene-perf-gate` ve `scripts/mobile-ux-audit` içinde, gerçek
 * Chrome'da.
 *
 * Burada ölçülen şey SÖZLEŞMENİN KENDİSİ: sahnenin markup'ı yerinde mi,
 * hareket doğru kapının arkasında mı, tam kanamalı bant kırpılan bir kabın
 * içinde mi, ve kurumsal sayfa hâlâ React yüklemiyor mu. Dördü de bir
 * yorum satırıyla değil, kaynakla kanıtlanabilir.
 *
 * Requirement ID'leri: SAHNE-B1…B6.
 */
final class SceneContractTest extends TestCase
{
    private const SCENE_CSS = 'resources/css/site-scene.css';

    private const SCENE_JS = 'resources/js/site';

    private function document(string $uri = '/'): DOMXPath
    {
        $html = (string) $this->get($uri)->getContent();

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($dom);
    }

    // --- SAHNE-B1 ----------------------------------------------------------

    public function test_the_home_page_carries_the_whole_scene_vocabulary(): void
    {
        /*
            DAĞARCIK EKSİKSİZ.

            Bu kapı bir "sınıf adı var mı" kontrolü değil: sahibin istediği
            altı efektin her birinin sayfada BİR KARŞILIĞI olduğunu
            donduruyor. Bir sonraki döngü bunlardan birini kaldırırsa, karar
            sessizce değil kırmızı bir testle olur.
        */
        $xpath = $this->document();

        $expectations = [
            'derinlikli yıldız alanı' => '//canvas[@data-scene="field"]',
            'parallax düzlemleri (en az üç)' => '//*[@data-plane]',
            'kaydırmaya bağlı bölüm geçişi' => '//*[@data-scene-progress]',
            'akan bant' => '//*[contains(concat(" ", @class, " "), " scene-drift ")]',
            '3D görünen 2D kart' => '//*[contains(concat(" ", @class, " "), " scene-tilt ")]',
            'atmosfer (nebula)' => '//*[contains(concat(" ", @class, " "), " scene-nebula ")]',
            'hüzme' => '//*[contains(concat(" ", @class, " "), " scene-beam ")]',
            'ufuk' => '//*[contains(concat(" ", @class, " "), " scene-horizon ")]',
            'giriş (reveal)' => '//*[contains(concat(" ", @class, " "), " scene-reveal ")]',
        ];

        foreach ($expectations as $label => $query) {
            $nodes = $xpath->query($query);

            self::assertNotFalse($nodes);
            self::assertGreaterThan(
                0,
                $nodes->length,
                "SAHNE-B1: ana sayfada [{$label}] yok. Dağarcık `docs/146` §4'te yazılı ve "
                .'sahibin emrinin doğrudan karşılığı.'
            );
        }

        /* Parallax bir "efekt" değil bir DERİNLİK: en az üç düzlem şart. */
        $planes = $xpath->query('//*[@data-plane]');
        self::assertNotFalse($planes);
        self::assertGreaterThanOrEqual(
            3,
            $planes->length,
            'SAHNE-B1: üçten az parallax düzlemi var. İki düzlem derinlik değil, kayma üretir.'
        );

        /* İki yön: biri satır başına, öteki satır sonuna akar. */
        $directions = [];

        foreach ($xpath->query('//*[@data-direction]') as $node) {
            if ($node instanceof DOMElement) {
                $directions[] = $node->getAttribute('data-direction');
            }
        }

        self::assertContains('start', $directions, 'SAHNE-B1: satır başına akan bant yok.');
        self::assertContains('end', $directions, 'SAHNE-B1: satır sonuna akan bant yok.');
    }

    // --- SAHNE-B2 ----------------------------------------------------------

    public function test_every_full_bleed_band_lives_inside_a_clipping_stage(): void
    {
        /*
            KURAL PAZARLIĞA KAPALI (`site-identity.css` §6).

            `.site-bleed` `100vw` kullanır ve `100vw` dikey kaydırma çubuğunu
            SAYAR. Kırpmayan bir kabın içinde bant, görüntü alanından çubuk
            kadar geniş olur ve sayfayı YATAY olarak kaydırır — 320 pikselde
            ölçülen ilk kusur ve `scripts/mobile-ux-audit`in ilk kuralı.

            Kısıtı yorum olarak yazmak yetmiyordu; bu kapı onu ÖLÇÜYE
            çeviriyor ve gerçekten çizilmiş belge üzerinde çalışıyor — yani
            bir Blade döngüsünün ürettiği bantları da görür.
        */
        $xpath = $this->document();
        $offenders = [];

        foreach ($xpath->query('//*[contains(concat(" ", @class, " "), " site-bleed ")]') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $hasStage = false;

            for ($parent = $node->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
                if (str_contains(' '.$parent->getAttribute('class').' ', ' site-stage ')) {
                    $hasStage = true;
                    break;
                }
            }

            if (! $hasStage) {
                $offenders[] = $node->getAttribute('class');
            }
        }

        self::assertSame(
            [],
            $offenders,
            'SAHNE-B2: `.site-bleed` bir `.site-stage` dışında kullanılmış: '
            .implode(' | ', $offenders)
            .' — kırpma olmadan bant sayfayı yatay kaydırır.'
        );
    }

    // --- SAHNE-B3 ----------------------------------------------------------

    public function test_no_motion_is_born_without_the_reduced_motion_gate(): void
    {
        /*
            TEK YÖNLÜ KAPI.

            Kural `no-preference` üzerinden yazılır, `reduce` üzerinden
            değil: ikinci biçimde animasyon önce tanımlanır sonra iptal
            edilir ve iptali yazmayı unutan TEK bir satır sessizce hareket
            eder. Bu kapı, sahne dosyasında `no-preference` bloğunun DIŞINDA
            hareket başlatan bir bildirim kalmadığını dondurur.

            İptaller (`animation: none`) serbesttir ve olmalıdır: derece
            merdiveni ile yüksek kontrast blokları hareketi tam da böyle
            kapatıyor.
        */
        $css = (string) file_get_contents(base_path(self::SCENE_CSS));
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        $outside = $this->withoutMotionAllowedBlocks($css);

        preg_match_all(
            '#(animation|animation-name|transition|transition-property)\s*:\s*([^;}]+)#i',
            $outside,
            $matches,
            PREG_SET_ORDER,
        );

        $offenders = [];

        foreach ($matches as [, $property, $value]) {
            $value = trim($value);

            if ($value === 'none' || $value === '') {
                continue;
            }

            $offenders[] = "{$property}: {$value}";
        }

        self::assertSame(
            [],
            $offenders,
            'SAHNE-B3: `'.self::SCENE_CSS.'` içinde `@media (prefers-reduced-motion: no-preference)` '
            ."DIŞINDA hareket başlatan bildirim var:\n  · ".implode("\n  · ", $offenders)
            ."\nParallax bazı insanlarda fiziksel rahatsızlık yapar; bu bir zevk meselesi değil."
        );
    }

    // --- SAHNE-B4 ----------------------------------------------------------

    public function test_the_scene_engine_ships_no_react_and_names_no_corporate_token(): void
    {
        /*
            İKİ SINIR, TEK TARAMA.

            1. React: kurumsal sayfalar React paketini hiç yüklemiyor
               (HOME-NO-REACT-05) ve bu ölçülmüş bir kazanç. Sahnenin
               etkileşim durumu yok — tuval, kaydırma, imleç. Bir bileşen
               ağacı ve 60 KB'lık bir çalışma zamanı, hiçbir şey karşılığında
               ödenen bedel olurdu.
            2. Kurumsal jeton adı: `KIMLIK-06` zaten `resources/js` altında
               `--zc-` arıyor. Burada aynı sınır motorun kendisi için AÇIKÇA
               yazılıyor, çünkü sebebi farklı: motor paletin nereden
               geldiğini BİLMEMELİ, yalnız `--scene-*` köprüsünü okumalı.
        */
        $offenders = [];

        foreach ($this->sceneSources() as $path) {
            $contents = (string) file_get_contents($path);
            $relative = str_replace(base_path().'/', '', $path);

            if (preg_match('#from\s+[\'"]react#', $contents) === 1) {
                $offenders[] = "{$relative} (React)";
            }

            if (str_contains($contents, '--zc-')) {
                $offenders[] = "{$relative} (kurumsal jeton)";
            }
        }

        self::assertSame([], $offenders, 'SAHNE-B4: '.implode(', ', $offenders));
    }

    // --- SAHNE-B5 ----------------------------------------------------------

    public function test_the_corporate_shell_loads_the_scene_entry_point(): void
    {
        $layout = (string) file_get_contents(base_path('resources/views/public/layout.blade.php'));

        self::assertStringContainsString(
            'resources/js/site.ts',
            $layout,
            'SAHNE-B5: kurumsal kabuk sahne motorunu hiç istemiyor — sayfa hareketsiz kalır.'
        );

        /*
            VE HÂLÂ REACT YOK. `HOME-NO-REACT-05` bunu ana sayfa için zaten
            donduruyor; burada aynı iddia KABUK seviyesinde tekrarlanıyor,
            çünkü sahne motoru bir gün "biraz React ekleyelim" denilerek
            değiştirilebilecek tek yeni yüzey.
        */
        $html = (string) $this->get('/')->getContent();

        self::assertStringNotContainsString('id="app"', $html);

        /*
            ÇİZİLEN BELGEDE ADRES, KAYNAK ADI DEĞİL.

            Üretim derlemesinde `resources/js/site.ts` adı HTML'de görünmez;
            görünen şey manifest'in ürettiği parça adıdır. Kaynak adını
            aramak, derlenmiş bir ortamda her zaman kırılan bir kapı olurdu.
            Bu yüzden manifest okunuyor ve ÇIKTININ sayfada olduğu
            doğrulanıyor.
        */
        $manifestPath = public_path('build/manifest.json');

        if (! is_file($manifestPath)) {
            self::markTestSkipped('SAHNE-B5: derleme çıktısı yok — `npm run build` çalıştırılmadı.');
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        self::assertIsArray($manifest);
        self::assertArrayHasKey(
            'resources/js/site.ts',
            $manifest,
            'SAHNE-B5: sahne motoru paketlenmemiş — kabuk onu isteyecek ve 404 alacak.'
        );
        self::assertStringContainsString($manifest['resources/js/site.ts']['file'], $html);
    }

    // --- SAHNE-B6 ----------------------------------------------------------

    public function test_the_weight_budget_is_written_down_with_its_reason(): void
    {
        /*
            BÜTÇE KALDIRILMADI, YÜKSELTİLDİ.

            Sahibin emri sahneyi zorunlu kıldı ve JavaScript'i sıfırda tutan
            eski tavan onu taşıyamıyordu. Kaldırılmış bir bütçe, bir gün "bir
            kütüphane daha ekleyelim" denildiğinde kimsenin fark etmeyeceği
            bir yerdir. Bu kapı, tavanın ve GEREKÇESİNİN yazılı kaldığını
            dondurur — sayıyı değil, sayının hesabını korur.
        */
        $path = base_path('scripts/scene-budget.json');

        self::assertFileExists($path, 'SAHNE-B6: ağırlık bütçesi dosyası yok.');

        $budget = json_decode((string) file_get_contents($path), true);

        self::assertIsArray($budget);
        self::assertSame('SITE-SCENE-BUDGET-01', $budget['requirement'] ?? null);

        foreach (['siteEntryGzipBytes', 'corporateCssGzipBytes'] as $limit) {
            self::assertArrayHasKey($limit, $budget['limits'] ?? []);
            self::assertGreaterThan(
                0,
                $budget['limits'][$limit],
                "SAHNE-B6: [{$limit}] sıfır ya da negatif — sıfır bütçe bir bütçe değildir."
            );
            self::assertNotEmpty(
                $budget['rationale'][$limit] ?? '',
                "SAHNE-B6: [{$limit}] için gerekçe yazılmamış. Gerekçesiz bir tavan, "
                .'ilk sıkışmada sessizce yükseltilir.'
            );
        }
    }

    // --- Yardımcılar -------------------------------------------------------

    /**
     * Hareketin doğmasına izin verilen blokları ÇIKARIR; geriye kalan metin,
     * kapının dışıdır.
     *
     * Ayraç sayarak yürüyor, düzenli ifadeyle değil: iç içe kurallar
     * (`:root[data-motion='on'] .x { … }`) yüzünden `@media` bloğunun sonu
     * bir örüntüyle bulunamaz.
     */
    private function withoutMotionAllowedBlocks(string $css): string
    {
        $out = '';
        $cursor = 0;

        /*
            SORGU BİLEŞİK OLABİLİR.

            Eğilme efekti `@media (pointer: fine) and (prefers-reduced-motion:
            no-preference)` altında yaşıyor: giriş kipi ayrımı ve hareket
            kapısı AYNI blokta. Düz bir dize araması onu kaçırırdı ve kapı,
            doğru yazılmış bir kuralı ihlal sayardı — gürültü üreten bir kapı
            kapatılır.
        */
        while (preg_match('#@media[^{]*prefers-reduced-motion:\s*no-preference[^{]*#', $css, $match, PREG_OFFSET_CAPTURE, $cursor) === 1) {
            $start = $match[0][1];
            $out .= substr($css, $cursor, $start - $cursor);
            $brace = strpos($css, '{', $start + strlen($match[0][0]) - 1);

            if ($brace === false) {
                break;
            }

            $depth = 0;
            $length = strlen($css);

            for ($i = $brace; $i < $length; $i++) {
                if ($css[$i] === '{') {
                    $depth++;
                } elseif ($css[$i] === '}') {
                    $depth--;

                    if ($depth === 0) {
                        break;
                    }
                }
            }

            $cursor = min($i + 1, $length);
        }

        return $out.substr($css, $cursor);
    }

    /** @return list<string> */
    private function sceneSources(): array
    {
        $files = [base_path('resources/js/site.ts')];
        $directory = base_path(self::SCENE_JS);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'ts') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
