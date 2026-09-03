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

    /** @return list<string> Depo kökündeki her compose dosyası, tabanı dahil. */
    private function composeFiles(): array
    {
        $files = glob(base_path('docker-compose*.yml')) ?: [];

        self::assertNotEmpty($files, 'Depo kökünde hiç compose dosyası yok.');

        return array_map(static fn (string $path): string => basename($path), $files);
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

    // --- DEPLOY-MAIL-TRANSPORT-08 -----------------------------------------

    /**
     * Posta ayarları konteynere GEÇMELİ.
     *
     * `config/services.php` Mailgun anahtarını `env('MAILGUN_SECRET')` ile
     * okur; ama üretimde `.env` konteynerin İÇİNDE yaşamaz — değerler
     * `docker-compose` `environment:` bloğundan gelir ve giriş betiği
     * `config:cache`'i o değerler enjekte edildikten SONRA çalıştırır.
     * Aktarım satırı olmadan sunucunun `.env`'i ne kadar doğru doldurulsa
     * da uygulama `MAIL_MAILER=log`'a düşer: mesaj kaydedilir ama hiçbir
     * yere gitmez. Bu, birim testlerinin göremeyeceği bir arıza — hata
     * kodda değil, aktarımda.
     *
     * Ve gizli değer BURAYA yazılamaz: her satır `${...}` başvurusu
     * olmalı, deponun içine sızan tek bir Mailgun anahtarı olmamalı.
     * Bunu `ContactDeliveryTest` de ayrıca korur; buradaki kapı aktarım
     * kanalının kendisini bekçiler.
     */
    public function test_the_mail_transport_reaches_the_container(): void
    {
        $compose = $this->read('docker-compose.yml');

        $variables = [
            'MAIL_MAILER',
            'MAIL_FROM_ADDRESS',
            'MAIL_FROM_NAME',
            'MAILGUN_DOMAIN',
            'MAILGUN_SECRET',
            'MAILGUN_ENDPOINT',
            'CONTACT_NOTIFICATION_ADDRESS',
        ];

        foreach ($variables as $variable) {
            self::assertMatchesRegularExpression(
                '/^\s*'.$variable.':\s*\$\{'.$variable.'\b/m',
                $compose,
                "DEPLOY-MAIL-TRANSPORT-08: `{$variable}` konteynere "
                .'`${...}` başvurusu olarak aktarılmıyor; sunucunun `.env`\'i '
                .'doğru olsa bile posta `log` sürücüsüne düşer.'
            );
        }

        // Gizli değerin kendisi ASLA burada olmamalı: canlı bir Mailgun
        // anahtarı ya da sandbox alan adı depoya sızmasın.
        self::assertDoesNotMatchRegularExpression(
            '/[a-z0-9]+\.mailgun\.org|MAILGUN_SECRET:\s*[\'\"]?[0-9a-f]{16,}/i',
            $compose,
            'DEPLOY-MAIL-TRANSPORT-08: docker-compose.yml içinde düz gizli değer var.'
        );
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

    // --- DEPLOY-GATED-04 --------------------------------------------------

    /**
     * Deploy OTOMATİKTİR ama KAPILIDIR.
     *
     * Bu kapı önce "deploy elle tetiklenmeli" diyordu. Sahibi kararı
     * değiştirdi (2026-08-27): repo güncellendiğinde site kendini
     * güncellemeli, kimseye haber vermek gerekmemeli. Karar sahibinindir ve
     * kapı sessizce silinmek yerine yeniden yazıldı.
     *
     * Değişmeyen şey: **kırık bir sürüm yayına çıkmamalı.** Akış doğrudan
     * `push`'a bağlansaydı deploy testlerle yarışırdı. `workflow_run` ile
     * CI'ın bitmesini bekler, ve `conclusion` kontrolü ile GEÇTİĞİNİ
     * doğrular — `workflow_run` başarısız koşumlarda da tetiklenir.
     *
     * Ve deploy kanalı hâlâ üretim sunucusuna kök erişimdir: host anahtarı
     * sabitlenmiş olmalı.
     */
    public function test_a_failed_ci_run_can_never_reach_production(): void
    {
        $workflow = $this->read('.github/workflows/deploy.yml');

        self::assertStringContainsString(
            'workflow_run:',
            $workflow,
            'DEPLOY-GATED-04: deploy CI ile yarışmamalı; `workflow_run` ile beklemeli.'
        );

        self::assertStringContainsString(
            'github.event.workflow_run.conclusion',
            $workflow,
            "DEPLOY-GATED-04: CI'ın GEÇTİĞİ doğrulanmıyor; `workflow_run` başarısız koşumlarda da tetiklenir."
        );

        // Yayına çıkan, CI'ın geçtiği commit olmalı — dalın o anki ucu değil.
        self::assertStringContainsString(
            'workflow_run.head_sha',
            $workflow,
            'DEPLOY-GATED-04: test edilen commit değil, başka bir commit deploy ediliyor.'
        );
    }

    public function test_the_deploy_channel_talks_to_a_pinned_host(): void
    {
        $workflow = $this->read('.github/workflows/deploy.yml');

        self::assertStringContainsString(
            'DEPLOY_KNOWN_HOSTS',
            $workflow,
            'DEPLOY-GATED-04: host anahtarı sabitlenmeli.'
        );

        // Yasağı dizgenin GEÇTİĞİ yerde değil, KULLANILDIĞI yerde ara:
        // ilk yazılışta bu iddia, kuralın neden var olduğunu anlatan kendi
        // yorumuma takılmıştı.
        self::assertSame(
            0,
            preg_match('/-o\s*StrictHostKeyChecking\s*=\s*no/', $workflow),
            'DEPLOY-GATED-04: host anahtarı doğrulaması kapatılmış.'
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

    // --- DEPLOY-TOPOLOGY-TRACKED-07 ---------------------------------------

    /**
     * Her dağıtım topolojisinin bir dosyası OLMALI ve o dosya depoda
     * yaşamalı.
     *
     * Bu kapı, 2026-08-29'da zabuno.com yayına alınırken doğdu. Sunucuda
     * hazır bir Caddy vardı, yani yığının kendi vekili başlatılmamalı ve
     * uygulama portu ana makineye yayımlanmalıydı. O fark, yalnız o
     * sunucuda duran, hiçbir yerde kayıtlı olmayan bir override dosyasına
     * yazılmıştı.
     *
     * Tehlike şuydu: dosya kaybolduğunda `up -d` yığının Caddy'sini
     * başlatmaya kalkar, 80 ve 443'ü ister ve o portları tutan sistem
     * vekilini devirir — yalnız bu uygulamayı değil, aynı sunucudaki her
     * siteyi. Topolojiyi takipsiz bırakmak, sırrı gizlemek değil, kurulumu
     * unutmaktır.
     */
    public function test_every_deployment_topology_is_tracked_in_the_repository(): void
    {
        $files = $this->composeFiles();

        foreach (['docker-compose.yml', 'docker-compose.local.yml', 'docker-compose.edge-proxy.yml'] as $expected) {
            self::assertContains(
                $expected,
                $files,
                "DEPLOY-TOPOLOGY-TRACKED-07: {$expected} depoda yok; bir topoloji yalnız bir makinede yaşıyor."
            );
        }
    }

    // --- DEPLOY-OVERRIDE-LOOPBACK-08 --------------------------------------

    /**
     * Taban dosya dışındaki hiçbir compose dosyası bir portu tüm
     * arayüzlere açamaz.
     *
     * Taban dosyanın 80/443'ü istisnadır ve orası zaten
     * DEPLOY-DB-NOT-PUBLIC-03 ile ayrıca korunuyor: o portlar gerçekten
     * herkese açık olmalı. Override'ların yayımladığı port ise bir ters
     * vekilin arkasına girmek içindir; `0.0.0.0`'a bağlanırsa TLS,
     * güvenlik başlıkları ve vekilin sınırları atlanarak doğrudan
     * uygulamaya ulaşılır.
     */
    public function test_overrides_publish_only_to_loopback(): void
    {
        foreach ($this->composeFiles() as $file) {
            if ($file === 'docker-compose.yml') {
                continue;
            }

            preg_match_all('/^\s*-\s*[\x27"]([^\x27"]*:)?[^\x27"]*:\d+[\x27"]\s*$/m', $this->read($file), $matches);

            foreach ($matches[0] as $mapping) {
                self::assertMatchesRegularExpression(
                    '/[\x27"]127\.0\.0\.1:/',
                    $mapping,
                    "DEPLOY-OVERRIDE-LOOPBACK-08: {$file} içindeki port yayını loopback'e bağlı değil: {$mapping}"
                );
            }
        }
    }

    // --- DEPLOY-NO-SITE-SPECIFICS-09 --------------------------------------

    /**
     * Override'lar tek bir kuruluma ait değer GÖMEMEZ.
     *
     * Alan adı ve port o makinenin `.env`'ine aittir. Dosyaya gömülürse
     * ikinci kurulum dosyayı kopyalayıp düzenler ve o kopya, düzeltmeler
     * geldikçe sessizce ayrışır. Aynı dosya her kurulumda çalışmalı;
     * değişen tek şey `.env` olmalı.
     */
    public function test_overrides_carry_no_installation_specific_values(): void
    {
        foreach ($this->composeFiles() as $file) {
            if ($file === 'docker-compose.yml') {
                continue;
            }

            $contents = preg_replace('/^\s*#.*$/m', '', $this->read($file)) ?? '';

            self::assertDoesNotMatchRegularExpression(
                '/\bzabuno\.com\b/',
                $contents,
                "DEPLOY-NO-SITE-SPECIFICS-09: {$file} bir alan adı gömüyor; değer .env'e ait."
            );

            preg_match_all('/^\s*-\s*[\x27"]127\.0\.0\.1:(\d+):/m', $contents, $matches);

            self::assertSame(
                [],
                $matches[1],
                "DEPLOY-NO-SITE-SPECIFICS-09: {$file} ana makine portunu sabitliyor; \${...} ile .env'den gelmeli."
            );
        }
    }

    // --- DEPLOY-SENDS-EVERY-TOPOLOGY-10 -----------------------------------

    /**
     * Deploy akışı, kurulumun GERÇEKTEN bulunduğu yere ve her topoloji
     * dosyasını göndermeli.
     *
     * Üç kusur birlikte bulundu ve üçü de tek başına deploy'u sessizce
     * anlamsız kılıyordu:
     *
     * 1. Akış `~/zabuno`'ya bağlanıyordu; `DEPLOY_USER=root` olduğu için
     *    bu `/root/zabuno` demek, kurulum ise `install.sh` ile
     *    `/opt/zabuno`'ya yapılıyor. Deploy boş bir dizinde çalışırdı.
     * 2. Yalnız taban compose gönderiliyordu. Sunucu `COMPOSE_FILE` ile
     *    bir override seçtiğinde o dosya deploy anında yok olur ve yığın
     *    ya hiç açılmaz ya da portu yayımlamayı bırakıp 502'ye düşer.
     * 3. `docker/Caddyfile` düz kopyalanıyordu; kökte kalıyor, taban
     *    compose'un `./docker/Caddyfile` bağlaması bir DİZİN yaratıyordu.
     */
    public function test_the_deploy_workflow_matches_the_installed_layout(): void
    {
        // Yorumlar ELENİR. Kapı, dosyanın ne YAPTIĞINA bakar; bir yorumun
        // eski hatayı anlatmak için o yolu anması, hatanın geri geldiği
        // anlamına gelmez. Aynı dosyadaki DEPLOY-DB-NOT-PUBLIC-03 kapısı
        // bu dersi biçimlendiriciyle kavga ederek öğrenmişti.
        $workflow = preg_replace('/^\\s*#.*$/m', '', $this->read('.github/workflows/deploy.yml')) ?? '';

        self::assertStringContainsString(
            'docker-compose*.yml',
            $workflow,
            'DEPLOY-SENDS-EVERY-TOPOLOGY-10: akış yalnız bazı compose dosyalarını gönderiyor.'
        );

        self::assertStringContainsString(
            '$DEPLOY_DIR/docker/',
            $workflow,
            'DEPLOY-SENDS-EVERY-TOPOLOGY-10: Caddyfile bağlama noktasının beklediği dizine gitmiyor.'
        );

        preg_match('/^readonly INSTALL_DIR="\\$\{ZABUNO_DIR:-([^}]+)\}"/m', $this->read('install.sh'), $installed);

        self::assertArrayHasKey(
            1,
            $installed,
            'DEPLOY-SENDS-EVERY-TOPOLOGY-10: install.sh kurulum dizini okunamadı.'
        );

        self::assertStringContainsString(
            $installed[1],
            $workflow,
            "DEPLOY-SENDS-EVERY-TOPOLOGY-10: akış {$installed[1]} dışında bir dizine deploy ediyor."
        );

        self::assertStringNotContainsString(
            '~/zabuno',
            $workflow,
            'DEPLOY-SENDS-EVERY-TOPOLOGY-10: ev dizinine göreli yol, root olarak /root/zabuno demektir.'
        );

        // SSH her sunucuda 22'de değil. İlk hâl portu hiç geçirmiyordu ve
        // sertleştirilmiş bir kurulumda (sshd 5055'te) deploy 22'ye
        // bağlanmaya çalışıp zaman aşımına düşerdi. Kapı sayıya bakar:
        // eklenen her yeni `ssh`/`scp` çağrısı portu taşımak zorunda,
        // yoksa tek bir unutulmuş satır kanalı sessizce kopartır.
        self::assertSame(
            preg_match_all('/\\bssh -i /', $workflow),
            preg_match_all('/\\bssh -i \\S+ -p "\\$DEPLOY_PORT"/', $workflow),
            'DEPLOY-SENDS-EVERY-TOPOLOGY-10: bir ssh çağrısı yapılandırılmış portu taşımıyor.'
        );

        self::assertSame(
            preg_match_all('/\\bscp -i /', $workflow),
            preg_match_all('/\\bscp -i \\S+ -P "\\$DEPLOY_PORT"/', $workflow),
            'DEPLOY-SENDS-EVERY-TOPOLOGY-10: bir scp çağrısı yapılandırılmış portu taşımıyor.'
        );
    }

    // --- DEPLOY-NO-REGISTRY-11 --------------------------------------------

    /**
     * İmaj sunucuya, deploy'un ZATEN sahip olduğu kanaldan gider.
     *
     * İlk hâl imajı GHCR'a itiyor ve sunucuya oradan çektiriyordu. İki
     * bedeli vardı ve ikisi de ilk gerçek deploy'da göründü: sunucu
     * `unauthorized` aldı, çünkü private bir paketi çekmek için orada
     * uzun ömürlü bir registry kimlik bilgisi durması gerekiyordu — SSH
     * kanalının yanında ikinci bir güven ilişkisi. Dahası private paketin
     * depolaması ve her çekim, hesabın paket kotasına yazılıyordu.
     *
     * Deploy'un sabitlenmiş bir SSH kanalı zaten var. İmaj oradan akar.
     */
    public function test_the_image_never_travels_through_a_registry(): void
    {
        $workflow = preg_replace('/^\s*#.*$/m', '', $this->read('.github/workflows/deploy.yml')) ?? '';

        self::assertStringNotContainsString(
            'ghcr.io',
            $workflow,
            'DEPLOY-NO-REGISTRY-11: akış bir kayıt defterine dönmüş; sunucuda saklanacak bir kimlik bilgisi doğar.'
        );

        self::assertStringNotContainsString(
            'push: true',
            $workflow,
            'DEPLOY-NO-REGISTRY-11: imaj bir kayıt defterine itiliyor.'
        );

        self::assertStringContainsString(
            'docker load',
            $workflow,
            'DEPLOY-NO-REGISTRY-11: imaj sunucuya SSH ile yüklenmiyor.'
        );
    }

    /**
     * ÜRETİM HANGİ COMMIT'İ SUNUYOR — `docs/87`.
     *
     * Deploy `.image.env`'e yalnız imaj etiketini yazıyordu ve konteynere
     * hiçbir revizyon geçmiyordu: canlı sayfa boş bir build kimliği
     * basıyordu. "Baktığım şey gerçekten yazdığım kod mu" sorusu —
     * `preview-truth` kapısının cevaplamak için var olduğu soru — üretimde
     * cevapsızdı, ve geri alma kördü.
     */
    public function test_the_deploy_hands_the_commit_to_the_container(): void
    {
        $workflow = $this->read('.github/workflows/deploy.yml');

        self::assertStringContainsString(
            'ZABUNO_BUILD_REVISION: ${{ github.sha }}',
            $workflow,
            'Deploy, çalıştırdığı commit\'i imaja geçirmeli.'
        );

        self::assertStringContainsString(
            'ZABUNO_BUILD_REVISION=$3',
            $workflow,
            'Revizyon `.image.env` içine yazılmalı; yoksa compose onu göremez.'
        );

        // Ve compose onu konteynere GEÇİRMELİ: akışa yazmak tek başına
        // yetmez, iki ucun da bağlı olması gerekir.
        self::assertMatchesRegularExpression(
            '/ZABUNO_BUILD_REVISION:\s*\$\{ZABUNO_BUILD_REVISION/',
            $this->read('docker-compose.yml'),
            'Compose, revizyonu uygulamanın ortamına geçirmeli.'
        );
    }

    /**
     * `robots.txt` UYGULAMADA üretilir — `docs/87`.
     *
     * Laravel'in nginx şablonundan gelen `location = /robots.txt` satırı
     * isteği statik dosya olarak arıyor; `public/robots.txt` olmadığı için
     * 404 dönüyor ve istek Laravel'e HİÇ ULAŞMIYORDU. Rota yerelde
     * (`artisan serve`, nginx yok) çalıştığı için kusur yalnız canlıda
     * görünüyordu.
     */
    public function test_nginx_does_not_swallow_routes_the_application_owns(): void
    {
        $nginx = $this->read('docker/nginx.conf');

        // Yorumlar önce düşer: bir açıklama kural değildir.
        $config = (string) preg_replace('/^\s*#.*$/m', '', $nginx);

        self::assertDoesNotMatchRegularExpression(
            '/location\s*=\s*\/robots\.txt/',
            $config,
            'robots.txt uygulamada üretiliyor; nginx onu statik dosya olarak aramamalı.'
        );

        // `favicon.ico` GERÇEK bir dosya, o blok kalmalı.
        self::assertFileExists(public_path('favicon.ico'));
    }

    /**
     * ÜRETİMDE PLAN KATALOĞU BOŞ KALMAZ — `docs/90`.
     *
     * Katalog şema değil VERİDİR ve ayrı bir tohumdur. Giriş betiği onu
     * çalıştırmasaydı üretimde fiyat sayfası "henüz yayımlanmadı" demeye
     * devam ederdi — ve bunu kimse fark etmezdi, çünkü göçler geçmiş ve
     * dağıtım yeşil görünürdü.
     */
    public function test_the_container_seeds_the_plan_catalogue_after_migrating(): void
    {
        $entrypoint = $this->read('docker/entrypoint.sh');

        self::assertStringContainsString(
            'PlanCatalogueSeeder',
            $entrypoint,
            'Giriş betiği plan kataloğunu tohumlamalı; yoksa üretimde fiyat sayfası boş kalır.'
        );

        // SIRA önemli: tohum, tablonun var olmasını bekler.
        self::assertLessThan(
            strpos($entrypoint, 'PlanCatalogueSeeder'),
            (int) strpos($entrypoint, 'artisan migrate --force'),
            'Tohum göçlerden SONRA çalışmalı.'
        );
    }
}
