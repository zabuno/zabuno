<?php

declare(strict_types=1);

namespace Tests\Feature\Build;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DIŞARIDAN OKUNABİLİR DAĞITIM KANITI — `docs/142`.
 *
 * Bu uç, bir sürüm rozeti değildir. Ölçülmüş bir olayın tekrarını
 * imkânsızlaştırmak için var (2026-09-08): dağıtım her adımı yeşil
 * tamamladı, sağlık kontrolü GEÇTİ, ve canlı site yine de birleştirilen
 * commit'i çalıştırmıyordu. Sağlık kontrolünün sorduğu soru — "bir şey 200
 * dönüyor mu?" — eski sürüm için de "evet"tir.
 *
 * Eksik olan tespit değil, KARŞILAŞTIRILABİLİR bir cevaptı. `/login` zaten
 * bir `<meta>` etiketi taşıyordu ama:
 *
 *  - HTML kazımak bir dağıtım kapısı için kırılgan bir sözleşmedir,
 *  - kurumsal ana sayfa o etiketi hiç basmıyor,
 *  - ve etiket YALNIZ PHP tarafını söylüyor. Bugünkü arıza tam da öteki
 *    yarısıydı: PHP bir noktaya kadar güncelken derlenmiş varlıklar
 *    bayattı.
 *
 * Bu yüzden uç İKİ ayrı gerçeği ayrı ayrı bildirir ve ikisini birbirinin
 * yerine koymaz.
 */
final class BuildProofEndpointTest extends TestCase
{
    private function stampPath(): string
    {
        return public_path('build/revision.txt');
    }

    /** Damgayı yaz, testi çalıştır, ne bulduysan o hâle geri koy. */
    private function withAssetStamp(?string $contents, callable $body): void
    {
        $path = $this->stampPath();
        $existed = is_file($path);
        $previous = $existed ? (string) file_get_contents($path) : null;

        try {
            if ($contents === null) {
                if ($existed) {
                    unlink($path);
                }
            } else {
                if (! is_dir(dirname($path))) {
                    mkdir(dirname($path), 0o755, true);
                }

                file_put_contents($path, $contents);
            }

            $body();
        } finally {
            if ($previous === null) {
                if (is_file($path)) {
                    unlink($path);
                }
            } else {
                file_put_contents($path, $previous);
            }
        }
    }

    /**
     * Uç, uygulamanın çalıştırdığı commit'i söyler.
     *
     * Dağıtım kapısının karşılaştırdığı sol taraf budur: yayına alınan
     * commit ile burada okunan değer ayrıştığı an dağıtım KIRMIZI döner.
     */
    #[Test]
    public function the_endpoint_reports_the_commit_the_application_runs(): void
    {
        config(['build.revision' => str_repeat('a', 40)]);

        $response = $this->getJson('/up/build');

        $response->assertOk();
        $response->assertJsonPath('revision', str_repeat('a', 40));
        $response->assertJsonPath('short_revision', 'aaaaaaa');
    }

    /**
     * Ve AYRICA, sunulan varlıkların hangi commit'ten derlendiğini söyler.
     *
     * İki alanın ayrı olması şart. 2026-09-08'de canlı sitede PHP tarafı
     * ileri, varlıklar geriydi; tek bir "sürüm" alanı bu durumu göstermeye
     * yapısal olarak yetersizdir — hangisini söylerse söylesin öbür yarıyı
     * gizler.
     */
    #[Test]
    public function the_endpoint_reports_the_commit_the_served_assets_were_built_from(): void
    {
        $assets = str_repeat('b', 40);

        $this->withAssetStamp($assets, function () use ($assets): void {
            config(['build.revision' => str_repeat('a', 40)]);

            $response = $this->getJson('/up/build');

            $response->assertOk();
            $response->assertJsonPath('revision', str_repeat('a', 40));
            $response->assertJsonPath('assets_revision', $assets);
            // Ayrışma AYRICA tek kelimeyle söylenir: kapının kabuk kodu
            // iki alanı kendi karşılaştırır, ama sahibi tarayıcıdan bakan
            // bir insandır ve ona cevabı hazır vermek gerekir.
            $response->assertJsonPath('assets_match', false);
        });
    }

    #[Test]
    public function matching_halves_are_reported_as_matching(): void
    {
        $revision = str_repeat('c', 40);

        $this->withAssetStamp($revision, function () use ($revision): void {
            config(['build.revision' => $revision]);

            $this->getJson('/up/build')->assertJsonPath('assets_match', true);
        });
    }

    /**
     * Bilinmeyen UYDURULMAZ.
     *
     * `BuildIdentity`'nin kurucu kararı burada da geçerli: uydurulmuş bir
     * sürüm, sürüm olmamasından kötüdür — çünkü karşılaştırmayı her zaman
     * "eşit" yapıp dedektörü sessizce işe yaramaz hâle getirir. Damga yoksa
     * `assets_revision` null'dır ve `assets_match` bir iddiada BULUNMAZ.
     */
    #[Test]
    public function an_unknown_half_is_reported_as_unknown_not_invented(): void
    {
        $this->withAssetStamp(null, function (): void {
            config(['build.revision' => str_repeat('a', 40)]);

            $response = $this->getJson('/up/build');

            $response->assertOk();
            $response->assertJsonPath('assets_revision', null);
            $response->assertJsonPath('assets_match', null);
        });
    }

    /**
     * Damga bir commit kimliğidir; başka ne varsa YOK SAYILIR.
     *
     * Dosya `public/` altında duruyor ve içeriği yanıt gövdesine giriyor.
     * Ham içeriği geri yansıtan bir uç, o dosyaya yazabilen herkese küçük
     * bir yayın kanalı verirdi. Biçim dışındaki her şey `null`.
     */
    #[Test]
    public function a_malformed_asset_stamp_is_never_echoed_back(): void
    {
        $this->withAssetStamp("<script>alert(1)</script>\n", function (): void {
            $response = $this->getJson('/up/build');

            $response->assertOk();
            $response->assertJsonPath('assets_revision', null);
            $response->assertDontSee('script', escape: false);
        });
    }

    /**
     * Cevap ÖNBELLEĞE ALINMAZ.
     *
     * Bayatlığı ölçen bir ucun kendisinin bayat cevap vermesi, kapıyı
     * sessizce yanlış yöne çevirirdi: dağıtım eski bir cevabı okuyup
     * "yeşil" derdi. Aynısı sahibin tarayıcısı için de geçerli.
     */
    #[Test]
    public function the_answer_is_never_cached(): void
    {
        $response = $this->get('/up/build');

        $response->assertOk();
        self::assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
            'Dağıtım kanıtı önbelleğe alınırsa kapı eski bir cevabı okur.'
        );
    }

    /**
     * Uç, iç ayrıntı SIZDIRMAZ.
     *
     * Commit kısa kimliği bir sır değil — depo açık kaynak ve kimlik zaten
     * `/login` HTML'inde duruyor. Sunucudaki mutlak yol, PHP/çatı sürümü,
     * ortam adı ya da yapılandırma ise sır: kimliği ilan etmek için hiçbiri
     * gerekmiyor ve saldırgana bedava bilgi olurdu.
     */
    #[Test]
    public function the_endpoint_does_not_leak_the_environment_around_it(): void
    {
        $response = $this->getJson('/up/build');

        $response->assertOk();

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        self::assertSame(
            ['assets_match', 'assets_revision', 'revision', 'short_revision'],
            $this->sortedKeys($payload),
            'Uç yalnız karşılaştırma için gereken alanları taşımalı.'
        );

        $body = $response->getContent() ?: '';

        self::assertStringNotContainsString(base_path(), $body);
        self::assertStringNotContainsString(PHP_VERSION, $body);
        self::assertStringNotContainsString(app()->version(), $body);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function sortedKeys(array $payload): array
    {
        $keys = array_keys($payload);
        sort($keys);

        return $keys;
    }
}
