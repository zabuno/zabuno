<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Infrastructure\Content\Pages\ProductOverviewPage;
use App\Support\Localization\SiteText;
use App\Support\Site\HomeStory;
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
 * ── FF-233'ÜN KAPILARI BURAYA TAŞINDI ────────────────────────────────────
 *
 * `HomeSceneContractTest` silindi: sahne sürümü onun ölçtüğü dosyayı
 * (`resources/css/site-motion.css`) ve ölçtüğü işaretlemeyi ortadan
 * kaldırmıştı, yani kapı var olmayan bir şeyi ölçüyordu. Ama içindeki İKİ
 * kapı İÇERİK dürüstlüğünü koruyordu ve onlar bir sürüm kararıyla
 * kaybolamaz — ikisi de aynı adla buraya taşındı:
 *
 *   · HOME-REAL-07  — sayfadaki her iddia ürünün kendi envanterinden gelir
 *     ve ondan AYRIŞAMAZ.
 *   · HOME-HONEST-08 — sayfada uydurma kanıt yok.
 *
 * HOME-SCENE-01/03/04/05 de anlamını korudukları ölçüde taşındı; hangisinin
 * neden taşınmadığı `test_the_retired_home_scene_gates_are_accounted_for`
 * yorumunda tek tek yazılı. HOME-FLUID-04 taşınmadı çünkü hiç kaybolmadı:
 * `PublicHomeContractTest` içinde duruyor ve çizilen HTML üzerinde çalışıyor.
 *
 * Requirement ID'leri: SAHNE-B1…B8, HOME-SCENE-01/03/04/05, HOME-REAL-07,
 * HOME-HONEST-08.
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
            /*
                DÖNGÜ 2'NİN EKLEDİĞİ DÖRT FİKİR (`docs/146` §9 madde 5).

                Döngü 1 kendi eksik listesinde bunları adıyla sayıyordu:
                *"Uzay şirketi dağarcığında henüz olmayanlar: yörünge
                çizgileri, veri akış hatları, ızgara/tel-kafes zemin, …
                bölümler arası gerçek MORPH geçişi."* Dördü de artık sayfada
                ve dördü de burada donduruluyor — bir sonraki döngü birini
                kaldırırsa karar sessizce değil kırmızı bir testle olur.
            */
            'yörünge' => '//*[contains(concat(" ", @class, " "), " scene-orbit ")]',
            'tel kafes zemin' => '//*[contains(concat(" ", @class, " "), " scene-grid ")]',
            'veri hattı' => '//*[contains(concat(" ", @class, " "), " scene-conduit ")]',
            'bölüm morph\'u' => '//*[contains(concat(" ", @class, " "), " scene-morph ")]',
            'yatay eksende düzlem' => '//*[@data-axis]',
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

        /*
            SAĞLI SOLLU, ÜÇ ŞERİTTE VE ÜÇ DÜZLEMDE.

            Sahibin isteği birebir *"sağlı sollu hareket eden landing page"*
            idi. İki şerit bir ZITLIK kurar ama bir DERİNLİK kurmaz: göz iki
            hızı karşılaştırır ve orada durur. Üçüncü şerit hızları bir sıraya
            dizer; yatay eksendeki düzlemler ise bandın KENDİSİNİ hareket
            ettirir — Döngü 1'in kendi eksik listesindeki cümle buydu:
            *"bölümlerin kendisi yatay hareket etmiyor."*
        */
        $lanes = $xpath->query('//*[contains(concat(" ", @class, " "), " scene-drift ")]');
        self::assertNotFalse($lanes);
        self::assertGreaterThanOrEqual(
            3,
            $lanes->length,
            'SAHNE-B1: üçten az akan şerit var. İki şerit zıtlık kurar, derinlik kurmaz.'
        );

        $axes = [];

        foreach ($xpath->query('//*[@data-axis]') as $node) {
            if ($node instanceof DOMElement) {
                $axes[] = $node->getAttribute('data-axis');
            }
        }

        self::assertGreaterThanOrEqual(
            2,
            count(array_unique($axes)),
            'SAHNE-B1: yatay düzlemlerin hepsi AYNI yöne akıyor. İki katman aynı yöne '
            .'kayarsa göz tek bir blok görür; derinlik ZIT yönden doğar.'
        );
    }

    // --- SAHNE-B7 ----------------------------------------------------------

    public function test_the_scene_reaches_the_other_corporate_pages(): void
    {
        /*
            SAHNE YALNIZ ANA SAYFADA DEĞİL (`docs/146` §9 madde 1).

            Döngü 1'in eksik listesinin İLK maddesi buydu. Bu kapı, önsöz
            bandının üç canlı kurumsal adreste de çizildiğini donduruyor —
            ve bir sayfanın sahnesini sessizce kaybetmesini imkânsız kılıyor.

            KALAN YÜZEYLER DÖNGÜ 3'TE KAPANDI: yardım makalesi, on üç yasal
            belge ve kütükten çizilen sayfalar artık bandı giyiyor — ama
            okunan yüzeylerde SAKİN kipte. O sözleşme ayrı bir kapıda
            (`CalmSceneOnReadingSurfacesTest`, SAHNE-B9…B12), çünkü ölçtüğü
            şey farklı: burada "bant var mı", orada "bant SUSUYOR mu".
        */
        foreach (['/pricing', '/about', '/contact'] as $uri) {
            $xpath = $this->document($uri);

            $prologue = $xpath->query('//*[contains(concat(" ", @class, " "), " site-prologue ")]');
            self::assertNotFalse($prologue);
            self::assertGreaterThan(
                0,
                $prologue->length,
                "SAHNE-B7: [{$uri}] önsöz bandını çizmiyor — sayfa sitenin geri kalanından "
                .'başka bir ürün gibi görünür.'
            );

            /*
                SAYFA BAŞINA TEK TUVAL — VE BU SAYI ÖLÇÜMDEN SONRA DA 1.

                Döngü 3 maliyeti ölçtü (`scripts/scene-webgl-context-cost`,
                gerçek Chrome, CPU ×4, 320×480 ve 1280×800): ikinci, üçüncü ve
                dördüncü bağlam GERÇEKTEN açıldı (`data-scene-live` dört
                tuvalde de `true`) ve kare süresine etkisi ÖLÇÜLEBİLİR
                DEĞİLDİ — p50 16,7 ms sabit, p95 farkı ±0,1 ms, uzun kare 0.
                JS yığını 320'de 787→890 KB, 1280'de 797→1449 KB.

                Buna rağmen sayı 1'de kalıyor ve gerekçesi ölçümün KENDİ
                sınırı: bu ölçüm GPU belleğini göremez — sürücü tarafındaki
                doku ve tampon belleği hiçbir tarayıcı API'sinden okunmuyor,
                üstelik başsız Chrome yazılım rasterleştirici kullanıyor. Yani
                elde edilen sayı "ikinci bağlam bedava" demiyor; "ikinci
                bağlamın ölçebildiğimiz kısmı bedava" diyor. Asıl kalemi
                göremeyen bir ölçüm, bir kapıyı gevşetmeye yetmez.
            */
            $canvases = $xpath->query('//canvas[@data-scene="field"]');
            self::assertNotFalse($canvases);
            self::assertSame(
                1,
                $canvases->length,
                "SAHNE-B7: [{$uri}] {$canvases->length} tuval taşıyor. İkinci bir WebGL "
                .'bağlamının maliyeti henüz ÖLÇÜLMEDİ.'
            );
        }

        /* Ana sayfa da aynı kurala tabi: üç derin bant var ama tuval BİR. */
        $home = $this->document();
        $homeCanvases = $home->query('//canvas[@data-scene="field"]');
        self::assertNotFalse($homeCanvases);
        self::assertSame(1, $homeCanvases->length, 'SAHNE-B7: ana sayfada birden çok tuval var.');
    }

    // --- SAHNE-B8 ----------------------------------------------------------

    public function test_the_touch_camera_cannot_steal_the_scroll(): void
    {
        /*
            DOKUNMA KAMERASI, KAYDIRMANIN YANINDA — YERİNE DEĞİL.

            Sahne artık parmakla sürüklenebiliyor (`runtime.ts`, `docs/146` §9
            madde 6). O sürükleme dikey kaydırmayı çalarsa sayfa okunamaz hâle
            gelir; sözleşme iki katmanda birden yazılı ve bu kapı ikisini de
            arıyor:

              · CSS: `.site-stage` üzerinde `touch-action: pan-y` — tarayıcıya
                dikey kaydırmanın HER ZAMAN onun olduğunu söyler.
              · Betik: `preventDefault` sahne motorunun HİÇBİR yerinde geçmez.

            Kaydırmanın gerçekten kaldığı ayrıca gerçek Chrome'da ölçülüyor
            (`scripts/scene-perf-gate`, SAHNE-DOKUNMA).
        */
        $shell = (string) file_get_contents(base_path('resources/css/site-shell.css'));

        self::assertMatchesRegularExpression(
            '#\.site-stage\s*\{[^}]*touch-action:\s*pan-y#s',
            $shell,
            'SAHNE-B8: `.site-stage` üzerinde `touch-action: pan-y` yok — kamera '
            .'sürüklemesi dikey kaydırmayı yutabilir.'
        );

        $offenders = [];

        foreach ($this->sceneSources() as $path) {
            /* Yorumlar ÇIKARILIR: bu dosyaların kendi gerekçe metinleri
               `preventDefault`ı adıyla anıyor ve bir kapı, kendi gerekçesini
               ihlal saymamalı. Aranan şey ÇAĞRIDIR. */
            $code = (string) preg_replace(
                ['#/\*.*?\*/#s', '#//[^\n]*#'],
                '',
                (string) file_get_contents($path)
            );

            if (str_contains($code, 'preventDefault')) {
                $offenders[] = str_replace(base_path().'/', '', $path);
            }
        }

        self::assertSame(
            [],
            $offenders,
            'SAHNE-B8: sahne motoru `preventDefault` çağırıyor: '.implode(', ', $offenders)
            .' — bir sahne, sayfayı okumanın önüne geçemez.'
        );
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

    // --- HOME-SCENE-01 : dar ekran TABAN, YAZIM SIRASI da -----------------

    /**
     * Sahibin cümlesi (2026-09-08): *"Sadece media query değil, gerçek
     * mobile first."*
     *
     * Ölçülebilir karşılığı budur: geniş ekran için yazılıp dar ekranda geri
     * alınan bir kural YOKTUR. `max-width` bir medya sorgusu, `max-*:` bir
     * Tailwind varyantı olarak tam bunu yapar — geniş ekranın kuralını taban
     * sayıp dar ekranda bastırır. Çıktı benzese bile borç oradan birikir:
     * ikinci düzen yine indirilir, yine odaklanılabilir, yine bakım ister.
     *
     * ÖLÇÜLEN DOSYA DEĞİŞTİ, KURAL DEĞİŞMEDİ. Kapı `site-motion.css`i
     * arıyordu; o dosya bu dalda silindi ve işini üç dosya devraldı. Üçü de
     * kendi başlık yorumunda "tek bir `@media (min-width: …)` yok" diye
     * yazıyor; bu kapı o cümleyi bir ölçüye çeviriyor.
     */
    public function test_no_corporate_rule_is_written_for_a_wide_screen_and_undone_on_a_narrow_one(): void
    {
        foreach ([
            'resources/css/site-scene.css',
            'resources/css/site-home.css',
            'resources/css/site-pages.css',
        ] as $relative) {
            /* Yorumlar ÇIKARILIR: bu üç dosyanın kendi başlık yorumu
               "tek bir `@media (min-width: …)` yok" cümlesini KELİMESİ
               KELİMESİNE taşıyor ve bir kapı, kendi gerekçesini ihlal
               saymamalı. Aranan şey KURALDIR. */
            $css = (string) preg_replace(
                '#/\*.*?\*/#s',
                '',
                (string) file_get_contents(base_path($relative))
            );

            self::assertDoesNotMatchRegularExpression(
                '/@media[^{]*\bmax-width\b/i',
                $css,
                "HOME-SCENE-01: [{$relative}] `max-width` medya sorgusu taşıyor — geniş ekran "
                .'kuralı taban sayılıp dar ekranda bastırılıyor. Taban 320 pikseldir; geniş '
                .'ekran onun ÜSTÜNE eklenir.'
            );

            self::assertDoesNotMatchRegularExpression(
                '/@media[^{]*\bmin-width\b/i',
                $css,
                "HOME-SCENE-01: [{$relative}] genişlik kırılma noktası taşıyor — düzen "
                .'`clamp()`, `min()` ve `repeat(auto-fit, minmax(…))` ile akışkan yazılır.'
            );
        }
    }

    public function test_the_home_page_hides_nothing_on_a_narrow_screen(): void
    {
        $template = (string) file_get_contents(base_path('resources/views/public/home.blade.php'));

        preg_match_all('/class="([^"]*)"/', $template, $matches);

        self::assertNotSame([], $matches[1], 'HOME-SCENE-01: şablonda sınıf bulunamadı — ölçüm dayanaksız.');

        foreach ($matches[1] as $classList) {
            self::assertDoesNotMatchRegularExpression(
                '/(^|\s)max-(sm|md|lg|xl|2xl):/',
                $classList,
                'HOME-SCENE-01: `max-*` bastırması bulundu: '.$classList
            );

            self::assertDoesNotMatchRegularExpression(
                '/(^|\s)hidden(\s|$)/',
                $classList,
                'HOME-SCENE-01: "dar ekranda gizle" bulundu: '.$classList
                .' — gizlenen şey yine indirilir, yine odaklanılabilir, yine bakım ister.'
            );
        }
    }

    // --- HOME-SCENE-03 : süsleme hiçbir şeyi örtemez ----------------------

    /**
     * Dekoratif hiçbir katman gezinmenin (20), çerez şeridinin (30) ya da
     * atlama bağlantısının (50) üstüne çıkamaz.
     *
     * İKİ ÖLÇÜM, TEK İDDİA:
     *
     *   1. Sahnenin katman merdiveni `--layer-scene-*` jetonlarından gelir
     *      (`site-shell.css`). Ham bir sayı yazan biri, o merdiveni
     *      görmeden aşabilirdi.
     *   2. Kurumsal yüzey dosyalarının hiçbiri POZİTİF ham bir `z-index`
     *      icat etmiyor. Negatif değer serbesttir ve olmalıdır: `.site-field`
     *      ışığı kendi kabının `isolation: isolate` bağlamında `-1`de
     *      yaşıyor, yani içeriğin ÜSTÜNE hiçbir koşulda çıkamaz.
     */
    public function test_no_scene_layer_rises_above_the_content(): void
    {
        $shell = (string) file_get_contents(base_path('resources/css/site-shell.css'));

        foreach (['back', 'mid', 'front'] as $depth) {
            self::assertStringContainsString(
                "z-index: var(--layer-scene-{$depth})",
                $shell,
                "HOME-SCENE-03: sahne katman merdiveninin [{$depth}] basamağı `--layer-scene-*` "
                .'jetonundan gelmiyor.'
            );
        }

        foreach ([
            'resources/css/site-scene.css',
            'resources/css/site-home.css',
            'resources/css/site-pages.css',
            'resources/css/site-identity.css',
        ] as $relative) {
            $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(base_path($relative)));

            preg_match_all('/z-index\s*:\s*([^;}]+)/', $css, $matches);

            foreach ($matches[1] as $value) {
                $value = trim($value);

                if (str_starts_with($value, 'var(--layer-') || preg_match('/^-\d+$/', $value) === 1) {
                    continue;
                }

                self::fail(
                    "HOME-SCENE-03: [{$relative}] ham bir yığın seviyesi icat ediyor: `{$value}` — "
                    .'katman sırası `--layer-*` jetonlarından gelir; hiçbir dekoratif katman '
                    .'gezinmeyi ya da hukuki bir seçimi örtemez.'
                );
            }
        }
    }

    // --- HOME-SCENE-04 : dekor ekran okuyucuya okunmaz --------------------

    /**
     * Ölçüm ÇİZİLEN belge üzerinde, şablon metni üzerinde değil: bir Blade
     * döngüsünün ürettiği katmanı da görür.
     */
    public function test_every_decorative_layer_is_invisible_to_a_screen_reader(): void
    {
        $xpath = $this->document();
        $layers = $xpath->query('//*[contains(concat(" ", @class, " "), " site-stage-layer ")]');

        self::assertNotFalse($layers);
        self::assertGreaterThan(0, $layers->length, 'Sahne katmanı bulunamadı — ölçüm dayanaksız.');

        foreach ($layers as $layer) {
            if (! $layer instanceof DOMElement) {
                continue;
            }

            self::assertSame(
                'true',
                $layer->getAttribute('aria-hidden'),
                'HOME-SCENE-04: dekoratif katman ekran okuyucuya okunuyor: '
                .$layer->getAttribute('class')
                .' — boş bir kutu, sesli okunduğunda bir engeldir.'
            );
        }
    }

    // --- HOME-SCENE-05 : betiksiz sayfa EKSİKSİZ --------------------------

    /**
     * `docs/118` E8: taban HTML, tavan serbest. Betik hareketin ÜSTÜNE
     * eklenir; hiçbir metni ve hiçbir hedefi o taşımaz.
     *
     * Ölçüm gerçekçidir: gövde, betik etiketleri atılmış hâlde taranır —
     * arama motoru ve JavaScript çalıştırmayan bir bot için varsayılan gövde
     * budur.
     */
    public function test_the_page_is_complete_without_any_script(): void
    {
        $withoutScripts = (string) preg_replace('#<script\b[^>]*>.*?</script>#is', '', $this->html());
        $withoutScripts = (string) preg_replace('#<script\b[^>]*/?>#is', '', $withoutScripts);

        $strings = app(SiteText::class)->all('en');

        foreach ([
            $strings['homeHeroHeading'],
            $strings['homeChainHeading'],
            $strings['homePartsHeading'],
            $strings['homeLimitsHeading'],
        ] as $heading) {
            self::assertStringContainsString(
                e($heading),
                $withoutScripts,
                "HOME-SCENE-05: betiksiz gövdede \"{$heading}\" yok."
            );
        }

        foreach (['/register', '/app', '/pricing', '/contact'] as $target) {
            self::assertStringContainsString(
                'href="'.$target.'"',
                $withoutScripts,
                "HOME-SCENE-05: betiksiz gövdede {$target} hedefi yok."
            );
        }

        /*
            SORU-CEVAP KAPALIYKEN DE OKUNUR. `<details>` içeriği HTML'de
            durur; bir arama motoru onu görür. Betiğe bağlı bir açılır bölme,
            kapalıyken HİÇ var olmazdı.
        */
        self::assertStringContainsString(
            e($strings['homeFaqPosAnswer']),
            $withoutScripts,
            'HOME-SCENE-05: kapalı bir soru-cevap bölmesinin cevabı betiksiz gövdede yok.'
        );
    }

    // --- HOME-REAL-07 : iddia uydurulmaz, envantere bağlı ----------------

    /**
     * Ana sayfanın yetenek listesi, ürünün kendi genel bakış sayfasının
     * (`/urun/`) envanteriyle **aynı sırada ve aynı terimlerle** durur.
     *
     * Bu, "sahte özellik listesi" sorununun kod düzeyindeki cevabı: bir
     * yetenek üründen düşerse ya da adı değişirse, pazarlama sayfası eski
     * iddiayı SESSİZCE taşımaya devam edemez. Bir pazarlama sayfasında bir
     * satır silmeyi kimse hatırlamaz; kırmızı bir test hatırlatır.
     */
    public function test_every_capability_named_on_the_home_page_exists_in_the_product_inventory(): void
    {
        $this->assertMirrors(HomeStory::PARTS, BlockType::Capabilities, 'yetenek');
    }

    public function test_every_limit_named_on_the_home_page_exists_in_the_product_inventory(): void
    {
        $this->assertMirrors(HomeStory::LIMITS, BlockType::Limitations, 'sınır');
    }

    public function test_the_chain_on_the_home_page_is_the_products_own_order(): void
    {
        $this->assertMirrors(HomeStory::CHAIN, BlockType::HowItWorks, 'adım');
    }

    /**
     * Her yetenek iddiasının arkasında ONU ÜRETEN bir dosya var.
     *
     * Envanterdeki `source` alanı bir yorum değil bir adres: dosya silinirse
     * iddia dayanaksız kalır ve bu test onu bulur.
     */
    public function test_every_claim_points_at_a_file_that_exists(): void
    {
        $missing = [];

        foreach ([BlockType::Capabilities, BlockType::HowItWorks, BlockType::Limitations] as $type) {
            foreach ($this->block($type)->entries as $entry) {
                if ($entry->source !== null && ! file_exists(base_path($entry->source))) {
                    $missing[] = $entry->source;
                }
            }
        }

        self::assertSame(
            [],
            $missing,
            'HOME-REAL-07: iddianın dayandığı dosya yok: '.implode(', ', $missing)
        );
    }

    /**
     * VE İDDİALAR GERÇEKTEN SAYFADA. Envanterle aynı olmak yetmez: liste
     * çizilmezse kapı yine yeşil kalırdı ve sayfa iddiasını sessizce
     * düşürebilirdi.
     */
    public function test_the_inventory_is_actually_drawn_on_the_page(): void
    {
        $html = $this->html();
        $text = app(SiteText::class);

        foreach ([HomeStory::CHAIN, HomeStory::PARTS, HomeStory::LIMITS] as $stems) {
            foreach ($stems as $stem) {
                $title = $text->get($stem.'.title', 'en');

                self::assertStringContainsString(
                    e($title),
                    $html,
                    "HOME-REAL-07: envanterde olan \"{$title}\" sayfada çizilmiyor."
                );
            }
        }
    }

    // --- HOME-HONEST-08 : uydurma kanıt yok ------------------------------

    public function test_the_page_invents_no_proof_it_does_not_have(): void
    {
        $html = strtolower($this->html());

        foreach ([
            'testimonial',
            'trusted by',
            'customers served',
            'award',
            'as seen in',
            'coming soon',
            'launching soon',
            'join thousands',
            'rated 5',
            'money-back',
        ] as $claim) {
            self::assertStringNotContainsString(
                $claim,
                $html,
                "HOME-HONEST-08: \"{$claim}\" — yatırımcıya hazırlık uydurmayla değil, "
                .'yapılmış işi göstererek kurulur.'
            );
        }

        /*
            "1000+ restoran" ailesi. Ürünün bugün kaç müşterisi olduğu bu
            depoda ÖLÇÜLEMEZ; ölçülemeyen bir sayıyı sayfaya yazmak, ilk
            soruda çöken bir iddiadır.

            Fiyat ve sürüm numaraları muaf değil ÇÜNKÜ desen artı işareti
            arıyor: "1000+" kırar, "24,90 TRY" kırmaz.
        */
        self::assertDoesNotMatchRegularExpression(
            '/\b\d[\d.,]*\s*\+\s*(restaurant|customer|business|user|venue|table)/i',
            $html,
            'HOME-HONEST-08: sayılmamış bir müşteri sayısı ilan ediliyor.'
        );

        /*
            SAHTE LOGO DA BİR İDDİADIR. Sayfada tek bir dış görsel yok: her
            piksel ya CSS'ten ya tuvalden doğuyor. Bir `<img>` etiketi
            eklendiğinde bu kapı konuşur ve o gün birinin "bu logo kimin?"
            diye sorması gerekir.
        */
        self::assertStringNotContainsString(
            '<img',
            $html,
            'HOME-HONEST-08: ana sayfada bir görsel var — kaynağı ve iddiası sorulmadan '
            .'bir logo, bir referans ya da bir ekran görüntüsü sayfaya giremez.'
        );
    }

    /**
     * EMEKLİYE AYRILAN KAPILARIN HESABI.
     *
     * Bu test bir şey ölçmüyor; SİLİNEN kapıların nereye gittiğini yazıya
     * döküyor. Bir kapı sessizce kaybolabiliyorsa, kapılar bir sözleşme
     * değil bir alışkanlıktır.
     *
     * · HOME-SCENE-01 (yükseklik maddesi) — TAŞINMADI. Eski kapı
     *   *"hiçbir sahne görüntü alanı yüksekliği istemez"* diyordu ve
     *   `\d+(vh|svh)` arıyordu. Sahne sürümünde kahraman bilerek
     *   `clamp(21rem, 72svh, 44rem)` taşıyor: ORTA terim görüntü alanına
     *   bağlı ama TABAN 21 rem ve o taban ölçülerek seçildi (320×480'de üst
     *   çubuğun 65 pikseli düşüldükten sonra iki düğme de ilk ekranda
     *   kalıyor). Yani kuralın koruduğu şey — "ilk ekran içeriği katlanmanın
     *   altına itemez" — artık bir düzenli ifadeyle değil, GERÇEK bir tarayıcıda
     *   320×480'de ölçülüyor (`scripts/mobile-ux-audit`). Bir düzenli ifade
     *   burada yalnız doğru yazılmış bir kuralı kırardı.
     *
     * · HOME-SCENE-02 (hareket kapısı) — SAHNE-B3 tarafından AŞILDI ve daha
     *   sıkı ölçülüyor: ayraç sayarak, bileşik medya sorgularını da görerek.
     *
     * · HOME-SCENE-02 (`data-motion='on'` ikinci kancası) — TAŞINMADI.
     *   Eski kapı, hareket bloğundaki HER kuralın betiğin yazdığı kancayı
     *   taşımasını şart koşuyordu. Sahne sürümünde bu artık bir kusur olurdu:
     *   nebula, hüzme ve akan bant BETİKSİZ de yaşıyor ve bu bilerek böyle
     *   (`site-scene.css` §3). `prefers-reduced-motion` kapısı tek başına
     *   mutlaktır ve betik onu geri açamaz — koruma kaybolmadı, ikinci
     *   kanca gereksizleşti.
     *
     * · HOME-SCENE-02 (`sayfada tek baskın sahne`) — TAŞINMADI. Eski kapı
     *   `class="site-stage site-scene"` dizesini SAYIYORDU ve sahne sürümünde
     *   o sınıf çifti hiç yok. Sahne sürümü bilerek üç derin bant taşıyor;
     *   ölçülmemiş maliyeti olan şey bant değil TUVAL ve onu SAHNE-B7 sayıyor:
     *   sayfa başına tam bir `canvas`.
     *
     * · HOME-FLUID-04 — TAŞINMADI çünkü hiç kaybolmadı: `PublicHomeContractTest`
     *   içinde duruyor ve şablonu değil ÇİZİLEN HTML'i tarıyor.
     */
    public function test_the_retired_home_scene_gates_are_accounted_for(): void
    {
        self::assertFileDoesNotExist(
            base_path('tests/Feature/PublicSite/HomeSceneContractTest.php'),
            'Emekli kapı dosyası geri gelmiş: iki sözleşme aynı sayfayı ölçerse, ilk '
            .'ayrıştıklarında hangisinin doğru olduğu belirsiz kalır.'
        );

        self::assertFileDoesNotExist(
            base_path('resources/css/site-motion.css'),
            'FF-233 hareket paketi geri gelmiş: sahne motoru aynı işi yapıyor ve iki '
            .'hareket dili aynı sayfada iki ayrı ürün gibi okunur.'
        );
    }

    // --- Yardımcılar -------------------------------------------------------

    /**
     * @param  list<string>  $stems
     */
    private function assertMirrors(array $stems, BlockType $type, string $noun): void
    {
        $text = app(SiteText::class);
        $inventory = array_values(array_map(
            static fn ($entry): ?string => $entry->term,
            $this->block($type)->entries,
        ));

        $onPage = array_map(
            static fn (string $stem): string => $text->get($stem.'.title', 'en'),
            $stems,
        );

        self::assertSame(
            $inventory,
            $onPage,
            "HOME-REAL-07: ana sayfadaki {$noun} listesi ürünün kendi envanteriyle "
            .'(`ProductOverviewPage`) ayrışmış. Ürüne bir parça eklendiğinde ya da bir '
            .'parça düştüğünde bu liste onunla birlikte döner; ikinci bir gerçek kaynak '
            .'doğamaz.'
        );
    }

    private function block(BlockType $type): ContentBlock
    {
        foreach (ProductOverviewPage::content()->blocks as $block) {
            if ($block->type === $type) {
                return $block;
            }
        }

        self::fail("`ProductOverviewPage` içinde {$type->value} bloğu yok — ölçüm dayanaksız.");
    }

    private function html(string $uri = '/'): string
    {
        return (string) $this->get($uri)->assertOk()->getContent();
    }

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
