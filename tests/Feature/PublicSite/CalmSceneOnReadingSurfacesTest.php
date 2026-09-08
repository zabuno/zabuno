<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Domain\Content\PagePublicationStatus;
use App\Models\ContentPage;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SAHNE-B9…B12 — sahne OKUNAN yüzeylere de ulaştı, ama SUSARAK (`docs/146` §12).
 *
 * ── DÖNGÜ 2 NEYİ BIRAKMIŞTI ───────────────────────────────────────────────
 *
 * `docs/146` §11 madde 3, kelimesi kelimesine:
 *
 *   *"Sahne hâlâ her kurumsal sayfada değil. Dışarıda kalanlar: yardım
 *   makaleleri (`/help`), yasal belgeler ve kütükten çizilen 386 sayfa.
 *   Yasal belgelerde bandı denemeden koymak yanlış olurdu: sayfanın en
 *   üstünde `role="alert"` taşıyan bir eksik-sözleşme bandı var ve bir uyarı,
 *   bir dekorun arkasında duramaz."*
 *
 * Bu dosya o maddeyi kapatan kapıdır ve dört ayrı iddiayı ölçer.
 *
 * ── NEDEN "SAKİN" AYRI BİR SÖZLEŞME ───────────────────────────────────────
 *
 * Sahibin emri (*"abartı dursun, görünsün, hissettirsin"*) bir kompozisyon
 * emridir, bir "her piksele hareket koy" emri değil. Bir sözleşmenin ya da
 * bir yardım makalesinin OKUNDUĞU yerde hareket bir süs değil bir engeldir:
 * göz satırı kaybeder, uzun bir belge okunamaz olur.
 *
 * Ama "sakin" bir niyet cümlesi olarak yazılırsa bir gün sessizce bozulur —
 * biri bandın yüzünü `orbit` yapar, kimse fark etmez. Bu yüzden sakinlik
 * burada üç SAYIYA bağlandı: tuval 0, düzlem 0, ve `site-prologue-calm` var.
 *
 * ── NEDEN JSDOM'DA DEĞİL, BURADA ──────────────────────────────────────────
 *
 * Ölçülen şey BELGE SIRASI ve VARLIK; ikisi de düzen hesabı gerektirmez ve
 * sunucunun bastığı HTML üzerinde kesin olarak sorulabilir. "Bant kırpıyor
 * mu, uyarı ekranda mı" soruları düzen sorularıdır ve onlar gerçek Chrome'da
 * ölçülüyor (`scripts/scene-visual-gate`, `scripts/mobile-ux-audit`).
 */
final class CalmSceneOnReadingSurfacesTest extends TestCase
{
    use RefreshDatabase;

    private function xpath(string $uri): DOMXPath
    {
        $html = (string) $this->get($uri)->getContent();

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($dom);
    }

    /** @return list<array{0:string}> */
    public static function readingSurfaces(): array
    {
        return [
            ['/help'],
            ['/terms'],
            ['/privacy'],
            ['/kvkk'],
            ['/distance-sales'],
            ['/pre-information'],
            ['/delivery'],
            ['/refund-policy'],
            ['/cookies'],
            ['/marketing-consent'],
            ['/data-processing'],
            ['/sla'],
            ['/acceptable-use'],
            ['/third-party-licenses'],
        ];
    }

    // --- SAHNE-B9 ----------------------------------------------------------

    #[DataProvider('readingSurfaces')]
    public function test_every_reading_surface_carries_the_scene_and_it_is_calm(string $uri): void
    {
        $xpath = $this->xpath($uri);

        $prologue = $xpath->query('//*[contains(concat(" ", @class, " "), " site-prologue ")]');
        self::assertNotFalse($prologue);
        self::assertSame(
            1,
            $prologue->length,
            "SAHNE-B9: [{$uri}] önsöz bandını çizmiyor (ya da birden çok çiziyor). Sahnesiz "
            .'bir sayfa, sitenin geri kalanından başka bir ürün gibi görünür.'
        );

        $calm = $xpath->query('//*[contains(concat(" ", @class, " "), " site-prologue-calm ")]');
        self::assertNotFalse($calm);
        self::assertSame(
            1,
            $calm->length,
            "SAHNE-B9: [{$uri}] bandı SAKİN kipte değil. Okunan bir metnin üstünde hareket "
            .'bir süs değil bir engeldir.'
        );
    }

    // --- SAHNE-B10 ---------------------------------------------------------

    #[DataProvider('readingSurfaces')]
    public function test_a_reading_surface_opens_no_webgl_context_and_no_parallax_plane(string $uri): void
    {
        $xpath = $this->xpath($uri);

        /*
            TUVAL SIFIR.

            İkinci bir WebGL bağlamının kare süresi maliyeti Döngü 3'te
            ölçüldü (`scripts/scene-webgl-context-cost`) ve sıfır çıktı — ama
            GPU belleği hiçbir tarayıcı API'sinden görünmüyor, yani maliyetin
            asıl kalemi HÂLÂ ölçülemedi. Burada mesele zaten o değil: bir
            sözleşmenin arkasında yıldız boyamak, ölçülse de yanlış olurdu.
        */
        $canvases = $xpath->query('//canvas[@data-scene="field"]');
        self::assertNotFalse($canvases);
        self::assertSame(
            0,
            $canvases->length,
            "SAHNE-B10: [{$uri}] {$canvases->length} tuval taşıyor. Okunan bir sayfada boyanan "
            .'bir yıldız alanı hem dikkat hem pil harcar.'
        );

        /*
            DÜZLEM SIFIR.

            `data-plane` taşıyan bir öğeyi parallax okur (`depth.ts`) ve
            kaydırdıkça öteler. Uzun bir belgeyi okurken kıpırdayan bir
            başlık, satır kaybettiren şeydir.
        */
        $planes = $xpath->query('//*[@data-plane]');
        self::assertNotFalse($planes);
        self::assertSame(
            0,
            $planes->length,
            "SAHNE-B10: [{$uri}] {$planes->length} parallax düzlemi taşıyor — sakin bir bant "
            .'kaydırmayla ötelenmez.'
        );
    }

    // --- SAHNE-B11 ---------------------------------------------------------

    public function test_the_legal_warning_stays_above_the_scene(): void
    {
        /*
            BİR UYARI, BİR DEKORUN ARKASINDA DURAMAZ.

            Döngü 2 bu maddeyi ölçülmemiş bıraktı ve doğru yaptı. Ölçüm şu:
            eksik satıcı kimliği bandı (`role="alert"`) sayfada sahne
            bandından ÖNCE gelmek zorunda. Belge sırası burada bir estetik
            tercih değil — ekran okuyucu sayfayı sırayla okur ve `role="alert"`
            tam olarak "önce beni duy" demektir.

            Şirket bilgisi girilmemiş bir kurulumda bant çizilir; girilmiş bir
            kurulumda hiç çizilmez ve o zaman ölçülecek bir şey de yoktur.
            İkisi de burada ayrı ayrı sorgulanıyor.
        */
        config([
            'legal.company' => [
                'legal_name' => null, 'address' => null, 'mersis' => null,
                'tax_office' => null, 'tax_number' => null, 'email' => null, 'phone' => null,
            ],
        ]);

        $seen = 0;

        foreach (['/distance-sales', '/pre-information', '/delivery', '/data-processing', '/sla'] as $uri) {
            $xpath = $this->xpath($uri);

            $alerts = $xpath->query('//*[@role="alert"]');
            self::assertNotFalse($alerts);

            if ($alerts->length === 0) {
                continue;
            }

            $seen += 1;

            $order = $xpath->query(
                '//*[@role="alert"]/following::*[contains(concat(" ", @class, " "), " site-prologue ")]'
            );
            self::assertNotFalse($order);
            self::assertGreaterThan(
                0,
                $order->length,
                "SAHNE-B11: [{$uri}] sahne bandı `role=\"alert\"` uyarısının ÖNÜNE geçmiş. "
                .'Bir uyarı, sayfada gördüğü ilk şey olmak için vardır.'
            );
        }

        /*
            HİÇ UYARI ÇIKMADIYSA BU TEST HİÇBİR ŞEY ÖLÇMEMİŞTİR ve "geçti"
            demesi bir yalan olurdu.
        */
        self::assertGreaterThan(
            0,
            $seen,
            'SAHNE-B11: hiçbir yasal sayfada uyarı bandı çizilmedi — ölçüm YAPILMADI.'
        );
    }

    // --- SAHNE-B12 ---------------------------------------------------------

    public function test_the_ledger_pages_wear_the_scene_from_one_template(): void
    {
        /*
            386 SAYFA, TEK KARAR.

            Kütükten çizilen sayfaların hepsi `content/page.blade.php`den
            geliyor. Bandın oraya girmesi, sayfa sayısından bağımsız bir
            karardır — ve bandın YÜZÜ sayfanın hiyerarşideki derinliğinden
            türüyor, rastgele değil: kök `orbit`, ikinci seviye `grid`, daha
            derini `conduit`.

            Burada iki sayfa çiziliyor ve ikisi FARKLI yüz taşımalı; aynı
            yüzü taşısalardı "derinlikten türüyor" cümlesi ölçülmemiş olurdu.
        */
        ContentPage::query()->create([
            'page_key' => 'urun', 'locale' => 'en', 'canonical_path' => '/en/product/',
            'content_type' => 'urun', 'template_key' => 'urun', 'parent_key' => null,
            'title' => 'Product overview', 'priority' => 'P0',
            'publication_status' => PagePublicationStatus::Published->value,
            'was_ever_published' => true,
        ]);
        ContentPage::query()->create([
            'page_key' => 'urun.qr-menu', 'locale' => 'en', 'canonical_path' => '/en/product/qr-menu/',
            'content_type' => 'urun', 'template_key' => 'urun', 'parent_key' => 'urun',
            'title' => 'QR menu', 'priority' => 'P0',
            'publication_status' => PagePublicationStatus::Published->value,
            'was_ever_published' => true,
        ]);

        $root = $this->xpath('/en/product');
        $child = $this->xpath('/en/product/qr-menu');

        foreach (['/en/product' => $root, '/en/product/qr-menu' => $child] as $uri => $xpath) {
            $prologue = $xpath->query('//*[contains(concat(" ", @class, " "), " site-prologue ")]');
            self::assertNotFalse($prologue);
            self::assertSame(
                1,
                $prologue->length,
                "SAHNE-B12: [{$uri}] önsöz bandını çizmiyor — kütük sayfaları sitenin geri "
                .'kalanından başka bir ürün gibi görünürdü.'
            );

            /* Sayfa başına TEK tuval kuralı burada da geçerli (`SAHNE-B7`). */
            $canvases = $xpath->query('//canvas[@data-scene="field"]');
            self::assertNotFalse($canvases);
            self::assertSame(1, $canvases->length, "SAHNE-B12: [{$uri}] tuval sayısı 1 değil.");

            /* Şablonun tek H1 kuralı bant onu taşırken de bozulmadı. */
            $headings = $xpath->query('//main//h1');
            self::assertNotFalse($headings);
            self::assertSame(1, $headings->length, "SAHNE-B12: [{$uri}] birden çok h1 var.");
        }

        $faces = [];

        foreach ([$root, $child] as $xpath) {
            foreach (['orbit', 'grid', 'conduit'] as $face) {
                if (($xpath->query('//*[contains(@class, "scene-'.$face.'")]')?->length ?? 0) > 0) {
                    $faces[] = $face;

                    break;
                }
            }
        }

        self::assertCount(
            2,
            $faces,
            'SAHNE-B12: bir kütük sayfası sahne dağarcığından hiçbir yüz taşımıyor.'
        );
        self::assertNotSame(
            $faces[0],
            $faces[1],
            'SAHNE-B12: iki farklı derinlikteki sayfa AYNI yüzü taşıyor — "yüz derinlikten '
            .'türer" cümlesi ölçülmemiş olurdu.'
        );
    }
}
