<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * DEPLOY-CONTRACT — üretim imajı ile deponun iddiaları ayrışmasın.
 *
 * Buradaki kapıların her biri, imaj ilk kez kurulurken GERÇEKTEN yaşanan
 * bir arızadan doğdu. Hiçbiri varsayımsal değil.
 */
final class DeploymentContractTest extends TestCase
{
    private function read(string $relative): string
    {
        $path = base_path($relative);

        self::assertFileExists($path, "Dağıtım dosyası yok: {$relative}");

        return (string) file_get_contents($path);
    }

    /** @return list<string> */
    private function declaredExtensions(): array
    {
        /** @var array{require: array<string, string>} $composer */
        $composer = json_decode($this->read('composer.json'), true, 512, JSON_THROW_ON_ERROR);

        $extensions = [];

        foreach (array_keys($composer['require']) as $package) {
            if (str_starts_with($package, 'ext-')) {
                $extensions[] = substr($package, 4);
            }
        }

        sort($extensions);

        return $extensions;
    }

    // --- DEPLOY-EXT-PARITY-01 ---------------------------------------------

    /**
     * `composer.json` ne şart koşuyorsa imaj onu kurmalı.
     *
     * Ayrıştıkları gün uygulama açılır ve ancak QR üretilirken ya da PDF
     * dışa aktarılırken patlar — en geç fark edilecek yerde. İlk
     * yazıldığında tam olarak ayrıştılar: `composer` imajında gd yoktu ve
     * derleme durdu. Durması doğruydu.
     */
    public function test_the_image_installs_exactly_the_extensions_composer_requires(): void
    {
        $dockerfile = $this->read('docker/Dockerfile');

        self::assertSame(
            1,
            preg_match('/install-php-extensions\s*\\\\\s*\n(.*?)\n\n/s', $dockerfile, $match),
            'DEPLOY-EXT-PARITY-01: Dockerfile içinde eklenti listesi okunamadı.'
        );

        preg_match_all('/^\s+([a-z0-9_]+)\s*\\\\?$/m', $match[1], $found);
        $installed = $found[1];
        sort($installed);

        // Kural KAPSAMA, eşitlik değil: imaj bildirilen her eklentiyi
        // kurmak zorundadır, ama fazlasını kurabilir. `ext-pdo` motordan
        // bağımsız bildirilir çünkü uygulama SQLite ile de çalışır; imaj
        // ise PostgreSQL'i hedeflediği için `pdo_pgsql` sürücüsünü ekler.
        // Eşitlik dayatmak, ya bildirimi motora bağlardı (paylaşımlı
        // barındırmada kurulum kırılırdı) ya da sürücüyü imajdan atardı.
        foreach ($this->declaredExtensions() as $required) {
            if ($required === 'pdo') {
                continue;
            }

            self::assertContains(
                $required,
                $installed,
                "DEPLOY-EXT-PARITY-01: composer.json `ext-{$required}` şart koşuyor ama imaj kurmuyor."
            );
        }

        // Hedef motorun sürücüsü olmadan imaj açılır ve ilk sorguda ölür.
        self::assertContains(
            'pdo_pgsql',
            $installed,
            'DEPLOY-EXT-PARITY-01: dağıtım hedefi PostgreSQL; sürücü imajda olmalı.'
        );
    }

    // --- DEPLOY-NO-DEV-CACHE-02 -------------------------------------------

    /**
     * Geliştirme makinesinin paket önbelleği imaja girmemeli.
     *
     * `bootstrap/cache/packages.php` yerelde kurulu geliştirme paketlerini
     * listeler. Üretim imajı `--no-dev` ile kurulur, o sınıflar yoktur ve
     * uygulama ilk komutta "Class Laravel\Pail\PailServiceProvider not
     * found" diyerek ölür. Bu tam olarak oldu.
     */
    public function test_the_image_does_not_inherit_the_development_package_cache(): void
    {
        self::assertStringContainsString(
            'bootstrap/cache/*',
            $this->read('.dockerignore'),
            'DEPLOY-NO-DEV-CACHE-02: bootstrap/cache imaja kopyalanıyor.'
        );
    }

    // --- DEPLOY-DB-NOT-PUBLIC-03 ------------------------------------------

    /**
     * Veritabanı internete açılmamalı: bir port yayımlamak, parolayı tek
     * savunma hattı yapar.
     */
    public function test_the_database_is_not_published_to_the_internet(): void
    {
        $compose = $this->read('docker-compose.yml');

        // Kapı BİÇİME değil ANLAMA bakar. İlk hâli `\n  db:\n` deseniyle
        // servis bloğunu kesiyordu; Prettier girintiyi 2'den 4 boşluğa
        // çevirince kapı, hiçbir şey değişmemiş olmasına rağmen düştü.
        // Bir kapının biçimlendiriciyle kavga etmesi, kapıya güveni bitirir.
        //
        // Doğrulanan şey şu: ana makineye yayımlanan portlar YALNIZ HTTP ve
        // HTTPS olmalı. Veritabanı iç ağdan konuşur; dışarıya açılan bir
        // 5432, parolayı tek savunma hattı yapar.
        preg_match_all('/^\s*-\s*[\x27"]?(\d+):(\d+)[\x27"]?\s*$/m', $compose, $matches);

        $publishedPorts = array_map('intval', $matches[1]);
        sort($publishedPorts);

        self::assertSame(
            [80, 443],
            $publishedPorts,
            'DEPLOY-DB-NOT-PUBLIC-03: yalnız 80 ve 443 yayımlanmalı; başka bir port dışarı açılmış.'
        );

        self::assertStringContainsString(
            'internal: true',
            $compose,
            'DEPLOY-DB-NOT-PUBLIC-03: veritabanının bulunduğu ağ `internal` işaretli olmalı.'
        );
    }

    // --- DEPLOY-URL-POLICY-06 ---------------------------------------------

    /**
     * URL politikası konteynere GEÇMELİ.
     *
     * Bu, yığın yerelde gerçekten çalıştırılırken bulundu. İkinci alan adı
     * 400 dönüyordu: `URL_TRUSTED_HOSTS` compose'da aktarılmadığı için
     * güvenilir host listesi boş kalıyor, uygulama `APP_URL` host'una
     * düşüyor ve diğer her host'u reddediyordu.
     *
     * Birim testleri bunu yakalayamazdı — sorun kodda değil, kod ile
     * çalıştığı ortam arasındaki aktarımdaydı. Bu yüzden kapı compose'a
     * bakıyor.
     */
    public function test_the_url_policy_reaches_the_container(): void
    {
        $compose = $this->read('docker-compose.yml');

        foreach (['URL_TRUSTED_HOSTS', 'URL_ENFORCE_HOST', 'URL_CANONICAL_SCHEME'] as $variable) {
            self::assertStringContainsString(
                $variable.':',
                $compose,
                "DEPLOY-URL-POLICY-06: `{$variable}` konteynere aktarılmıyor; "
                .'ikinci alan adı 400 döner.'
            );
        }
    }

    // --- DEPLOY-MULTI-DOMAIN-07 -------------------------------------------

    /**
     * Vekil TEK alan adına kilitlenmemeli.
     *
     * Bu bir SaaS: aynı yazılım birden çok alan adında ve birden çok
     * sunucuda çalışır. Caddy site adresini çoğul bir listeden okumalı,
     * yoksa ikinci alan adı için sertifika hiç alınmaz.
     */
    public function test_the_proxy_serves_a_list_of_domains_not_a_single_one(): void
    {
        $caddyfile = $this->read('docker/Caddyfile');

        self::assertStringContainsString(
            '{$ZABUNO_DOMAINS}',
            $caddyfile,
            'DEPLOY-MULTI-DOMAIN-07: vekil tek alan adına kilitli.'
        );

        // Uygulamanın hangi şema ve host ile gelindiğini bilmesi gerekir;
        // bilmezse ürettiği her mutlak adres yanlış olur.
        self::assertStringContainsString('X-Forwarded-Proto', $caddyfile);
        self::assertStringContainsString('X-Forwarded-Host', $caddyfile);
    }

    // --- DEPLOY-MANUAL-ONLY-04 --------------------------------------------

    /**
     * Deploy otomatik olmamalı ve SSH host anahtarı sabitlenmeli.
     *
     * `main`'e her birleşme yayına dönerse, bir yazım düzeltmesi ile bir
     * davranış değişikliği aynı riski taşır. Ve deploy kanalı üretim
     * sunucusuna kök erişimdir: ilk bağlantıda host anahtarını körlemesine
     * kabul etmek o kanalı ortadaki adama açar.
     */
    public function test_deploying_is_a_deliberate_act_on_a_pinned_host(): void
    {
        $workflow = $this->read('.github/workflows/deploy.yml');

        self::assertStringContainsString('workflow_dispatch:', $workflow);
        self::assertStringNotContainsString(
            'branches: [main]',
            $workflow,
            'DEPLOY-MANUAL-ONLY-04: deploy `main` push ile tetikleniyor.'
        );

        self::assertStringContainsString(
            'DEPLOY_KNOWN_HOSTS',
            $workflow,
            'DEPLOY-MANUAL-ONLY-04: host anahtarı sabitlenmeli.'
        );
        // Yasağı dizgenin GEÇTİĞİ yerde değil, KULLANILDIĞI yerde ara:
        // ilk yazılışta bu iddia, kuralın neden var olduğunu anlatan kendi
        // yorumuma takıldı. Bir kapının kendi gerekçesini ihlal sayması,
        // gerekçenin silinmesine yol açar.
        self::assertSame(
            0,
            preg_match('/-o\s*StrictHostKeyChecking\s*=\s*no/', $workflow),
            'DEPLOY-MANUAL-ONLY-04: host anahtarı doğrulaması kapatılmış.'
        );
    }

    // --- DEPLOY-TARGET-ARCH-05 --------------------------------------------

    /**
     * Hedef netcup AMD EPYC — linux/amd64. Runner mimarisinin sessizce
     * değişmesi, hedefte çalışmayan bir imaj üretir.
     */
    public function test_the_image_is_built_for_the_target_architecture(): void
    {
        self::assertStringContainsString(
            'platforms: linux/amd64',
            $this->read('.github/workflows/deploy.yml'),
            'DEPLOY-TARGET-ARCH-05: hedef mimari açıkça yazılmalı.'
        );
    }
}
