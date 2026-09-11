<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * BACKUP-PRODUCE-01 — "tatbikat edilen yedek" ile "duran yedek" aynı şey
 * değildir (`docs/107` Faz 1.5).
 *
 * ÖLÇÜLEN EKSİK. Bu depoda günlük bir yedek/geri yükleme TATBİKATI var
 * (`security:evidence:backup-restore`, `docs/124`) ve o tatbikat kendi
 * dökümünü işi bitince SİLER — doğrusu da budur, çünkü tatbikatın ölçtüğü
 * şey "geri gelebiliyor mu", "duruyor mu" değil. Sonuç şu: `docker-compose`
 * `db-backups` adlı kalıcı bir hacim tanımlar, kimse oraya bir şey yazmaz
 * ve sunucu bugün kaybedilse geri dönülecek TEK BİR DOSYA yoktur.
 *
 * SAHİBİN YOLCULUĞU. Kadıköy'deki restoranın sahibi menüsünü, fotoğraflarını
 * ve altı aylık siparişini bu sunucuya emanet etti. Yeşil yanan tatbikat
 * kaydı ona "verin güvende" diyor; oysa bugün disk giderse geri yüklenecek
 * bir arşiv yok. Bu paket o arşivi üreten komutun SÖZLEŞMESİNİ dondurur.
 *
 * BU DOSYA NE DEĞİLDİR. Retention (eski yedeğin silinmesi), offsite kopya
 * ve maliyet bu paketin dışındadır; buradaki kapı yalnız "yedek üretiliyor
 * mu, güvenli mi üretiliyor mu" sorusunu sorar. Silme hiç yoktur: madde
 * `test_an_existing_archive_is_never_deleted_by_a_new_run` bunu tersinden
 * kilitler.
 *
 * KANIT MAKİNE OKUNUR. `--json` koşunun raporunu (`path`, `bytes`,
 * `sha256`) basar ve aynı özet arşivin yanındaki `.sha256` kardeş
 * dosyasında da durur; ikisi ayrışırsa kanıt şüphelidir.
 *
 * ÜRETİM VERİSİNE TEK BİR YAZMA YOKTUR. Hiçbir madde `pg_restore`'u canlı
 * veritabanına çalıştırmaz; geri yüklenebilirlik `pg_restore --list` ile,
 * yani arşivi OKUYARAK doğrulanır.
 *
 * BİLİNMİYOR ≠ GEÇTİ. Canlı PostgreSQL isteyen maddeler, PostgreSQL yokken
 * "geçti" demez; atlanır ve sebebini söyler. Ölçüm CI'ın PostgreSQL işinde
 * yapılır ve orada eksik istemci aracı BAŞARISIZLIKTIR, atlama değil —
 * `tests/Feature/Security/PostgresBackupRestoreDrillTest.php` ile aynı
 * ilke.
 *
 * Requirement IDs: BACKUP-DB-DESTINATION-01, BACKUP-DB-DRIVER-02,
 * BACKUP-DB-DISK-03, BACKUP-DB-CLIENT-04, BACKUP-DB-ATOMIC-05,
 * BACKUP-DB-NEVER-DELETES-06, BACKUP-DB-RESTORABLE-07.
 */
final class DatabaseBackupCommandTest extends TestCase
{
    private const COMMAND = 'zabuno:backup:database';

    /** Tamamlanmış bir arşivin adı; yarım kalan bir döküm bu adı ALMAZ. */
    private const ARCHIVE_GLOB = '*.dump';

    /** @var list<string> */
    private array $tempDirsToClean = [];

    /** @var list<string> */
    private array $probeTablesToDrop = [];

    protected function tearDown(): void
    {
        foreach ($this->probeTablesToDrop as $table) {
            try {
                DB::statement('DROP TABLE IF EXISTS '.$table);
            } catch (\Throwable) {
                // Bağlantı yoksa düşürülecek tablo da yoktur.
            }
        }
        $this->probeTablesToDrop = [];

        foreach ($this->tempDirsToClean as $dir) {
            $this->removeDirectoryRecursively($dir);
        }
        $this->tempDirsToClean = [];

        parent::tearDown();
    }

    private function removeDirectoryRecursively(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$item;
            is_dir($path) ? $this->removeDirectoryRecursively($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zabuno-backup-produce-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($dir, 0700, true));
        $this->tempDirsToClean[] = $dir;

        return $dir;
    }

    /**
     * Komut KAYITLI olmalı. Bu iddia her maddenin başındadır: komut yokken
     * `Artisan::call()` istisna fırlatır ve kırmızı, sözleşmeyi değil
     * altyapıyı anlatırdı.
     */
    private function requireCommand(): void
    {
        self::assertArrayHasKey(
            self::COMMAND,
            Artisan::all(),
            'BACKUP-PRODUCE-01: `'.self::COMMAND.'` diye bir komut yok; '
            .'`db-backups` hacmi tanımlı ama ona yazan hiçbir şey bulunmuyor.'
        );
    }

    /**
     * `pg_dump` yerine geçen sahte ikili.
     *
     * `--version` sorusuna verilen cevap dışında NE YAPTIĞI önemli değil;
     * önemli olan ÇAĞRILIP ÇAĞRILMADIĞIDIR. Gerçek bir döküm denemesi
     * `$marker` dosyasını bırakır, böylece "komut üretim veritabanına hiç
     * dokunmadan reddetti mi" sorusu ölçülebilir hâle gelir.
     *
     * @param  string  $version  `--version` çıktısındaki sürüm dizgesi.
     * @param  int  $dumpExitCode  Gerçek döküm denemesinin çıkış kodu.
     * @param  bool  $writePartial  Döküm hedefine yarım bir dosya bıraksın mı.
     */
    private function fakePgDump(string $version, int $dumpExitCode = 0, bool $writePartial = false): string
    {
        $home = $this->makeTempDir();
        $binary = $home.DIRECTORY_SEPARATOR.'pg_dump';
        $marker = $home.DIRECTORY_SEPARATOR.'invoked';

        $partial = $writePartial
            ? <<<'SH'
            for arg in "$@"; do
                case "$arg" in
                    --file=*) printf 'PGDMP-YARIM' > "${arg#--file=}" ;;
                esac
            done
            printf 'PGDMP-YARIM'
            SH
            : '';

        file_put_contents($binary, <<<SH
        #!/bin/sh
        for arg in "\$@"; do
            case "\$arg" in
                --version|-V) echo "pg_dump (PostgreSQL) {$version}"; exit 0 ;;
            esac
        done
        echo "invoked" > "{$marker}"
        {$partial}
        exit {$dumpExitCode}
        SH);
        chmod($binary, 0700);

        $this->fakeBinaryMarker = $marker;

        return $binary;
    }

    private string $fakeBinaryMarker = '';

    private function assertDumpWasNeverAttempted(): void
    {
        clearstatcache();
        self::assertFileDoesNotExist(
            $this->fakeBinaryMarker,
            'Komut reddettiğini söyledi ama `pg_dump`ı yine de çalıştırdı: '
            .'ön kontroller üretim veritabanına dokunmadan ÖNCE bitmeli.'
        );
    }

    /** @return list<string> */
    private function archivesIn(string $dir): array
    {
        clearstatcache();

        return array_values(glob($dir.DIRECTORY_SEPARATOR.self::ARCHIVE_GLOB) ?: []);
    }

    /**
     * Üretim motoru PostgreSQL'dir; ön kontrol maddeleri komutu o motora
     * bakarken ölçer. Canlı sunucu GEREKMEZ: reddin tam olarak sunucuya
     * bağlanmadan önce gerçekleşmesi ölçülen şeyin kendisidir.
     */
    private function pretendProductionDriver(): void
    {
        config(['database.default' => 'pgsql']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configureBackup(string $directory, string $pgDumpBinary, array $overrides = []): void
    {
        config(array_merge([
            'backup.database.path' => $directory,
            'backup.database.pg_dump_binary' => $pgDumpBinary,
        ], $overrides));
    }

    // --- BACKUP-DB-DESTINATION-01 -----------------------------------------

    /**
     * Yazılamayan bir hedef, döküm BAŞLAMADAN durdurulur.
     *
     * Sırası önemli: hedef doğrulanmadan `pg_dump` çalıştırılırsa üretim
     * veritabanı gigabaytlarca okunur ve sonuç hiçbir yere yazılamaz —
     * canlı sunucuya bedeli ödetilmiş, karşılığında hiçbir yedek
     * alınmamış olur.
     */
    public function test_a_destination_that_is_not_a_writable_directory_stops_before_the_database_is_touched(): void
    {
        $this->requireCommand();
        $this->pretendProductionDriver();

        $home = $this->makeTempDir();
        $notADirectory = $home.DIRECTORY_SEPARATOR.'backups';
        file_put_contents($notADirectory, 'bu bir dizin değil');

        $this->configureBackup($notADirectory, $this->fakePgDump('17.4'));

        $exitCode = Artisan::call(self::COMMAND);

        self::assertNotSame(
            0,
            $exitCode,
            'BACKUP-DB-DESTINATION-01: yazılamayan bir hedefte komut BAŞARILI diyemez; '
            .'zamanlayıcı her gece "yedek alındı" der ve ortada dosya olmaz.'
        );
        $this->assertDumpWasNeverAttempted();
        self::assertSame([], $this->archivesIn($home));
    }

    // --- BACKUP-DB-DRIVER-02 ----------------------------------------------

    /**
     * PostgreSQL dışında bir motorda komut "yapılmadı" der, "yapıldı" demez.
     *
     * Üretim PostgreSQL koşar (`docker-compose.yml`, `postgres:17-alpine`).
     * Başka bir sürücüde üretilecek dosya üretime GERİ YÜKLENEMEZ; onu
     * yedek diye saymak, yedeği olduğunu sanmanın en pahalı hâlidir —
     * hiç yedeği olmadığını bilmekten kötüdür.
     */
    public function test_a_non_postgresql_connection_is_reported_as_not_applicable_not_as_a_backup(): void
    {
        $this->requireCommand();
        config(['database.default' => 'sqlite']);

        $destination = $this->makeTempDir();
        $this->configureBackup($destination, $this->fakePgDump('17.4'));

        $exitCode = Artisan::call(self::COMMAND);

        self::assertNotSame(
            0,
            $exitCode,
            'BACKUP-DB-DRIVER-02: uygulanamayan bir koşu "geçti" değildir.'
        );
        $this->assertDumpWasNeverAttempted();
        self::assertSame([], $this->archivesIn($destination));
    }

    // --- BACKUP-DB-DISK-03 ------------------------------------------------

    /**
     * Yer kalmadıysa döküm hiç başlamaz.
     *
     * Dolan bir diskte yarım yazılan dosya iki kez zarar verir: yedek
     * alınmamış olur ve aynı diskteki VERİTABANI da yazamaz hâle gelir —
     * yani yedekleme işinin kendisi siteyi düşürür.
     */
    public function test_a_disk_without_room_for_the_archive_is_refused_before_the_dump_starts(): void
    {
        $this->requireCommand();
        $this->pretendProductionDriver();

        $destination = $this->makeTempDir();
        $free = (int) max(0, (float) disk_free_space($destination));

        $this->configureBackup($destination, $this->fakePgDump('17.4'), [
            'backup.database.minimum_free_bytes' => $free + 1_000_000_000,
        ]);

        $exitCode = Artisan::call(self::COMMAND);

        self::assertNotSame(
            0,
            $exitCode,
            'BACKUP-DB-DISK-03: boş alan eşiğin altındayken komut yine de yedek aldığını söylüyor.'
        );
        $this->assertDumpWasNeverAttempted();
        self::assertSame([], $this->archivesIn($destination));
    }

    // --- BACKUP-DB-CLIENT-04 ----------------------------------------------

    /**
     * `pg_dump` yoksa ya da sunucudan eskiyse: dosya YOK.
     *
     * Bu, bu depoda bir kez ölçülmüş bir arıza sınıfıdır — tatbikat
     * koşucusu aynı sebeple 127/126 ile "bilinmiyor" der (`docs/124`).
     * Eski bir istemcinin ürettiği arşiv geri yüklenemez; üretilip
     * duruyor olması onu yedek yapmaz, yalnız yedeği olduğu YANILSAMASINI
     * üretir.
     */
    public function test_a_missing_or_too_old_pg_dump_is_refused_instead_of_producing_an_unrestorable_archive(): void
    {
        $this->requireCommand();
        $this->pretendProductionDriver();

        // (a) İkili hiç yok.
        $destination = $this->makeTempDir();
        $this->configureBackup($destination, $destination.DIRECTORY_SEPARATOR.'pg_dump-yok');

        $missingExit = Artisan::call(self::COMMAND);

        self::assertNotSame(0, $missingExit, 'BACKUP-DB-CLIENT-04: `pg_dump` yokken sonuç "geçti" olamaz.');
        self::assertSame([], $this->archivesIn($destination), 'BACKUP-DB-CLIENT-04: araç yokken dosya üretilmemeli.');

        // (b) İkili var ama üretim sunucusundan (PostgreSQL 17) eski.
        $olderDestination = $this->makeTempDir();
        $this->configureBackup($olderDestination, $this->fakePgDump('9.6.24'));

        $staleExit = Artisan::call(self::COMMAND);

        self::assertNotSame(0, $staleExit, 'BACKUP-DB-CLIENT-04: eski istemcinin ürettiği arşiv geri yüklenemez.');
        $this->assertDumpWasNeverAttempted();
        self::assertSame([], $this->archivesIn($olderDestination));
    }

    // --- BACKUP-DB-ATOMIC-05 ----------------------------------------------

    /**
     * Yarım kalan döküm FİNAL ADI almaz.
     *
     * Geri yükleme günü kimse dosyanın içine bakmaz; en yeni `.dump`
     * dosyasına bakar. Yarım bir dökümün final adıyla durması, o gün
     * "yedeğimiz var" deyip geri yükleyememek demektir. Bu yüzden dosya
     * önce geçici bir adla yazılır ve YALNIZ döküm başarıyla bittiğinde
     * final adına taşınır.
     */
    public function test_a_dump_that_dies_halfway_leaves_no_file_under_the_final_name(): void
    {
        $this->requireCommand();
        $this->pretendProductionDriver();

        $destination = $this->makeTempDir();
        $this->configureBackup($destination, $this->fakePgDump('17.4', dumpExitCode: 1, writePartial: true));

        $exitCode = Artisan::call(self::COMMAND);

        self::assertNotSame(0, $exitCode, 'BACKUP-DB-ATOMIC-05: kırılan bir döküm sıfırla çıkamaz.');
        self::assertSame(
            [],
            $this->archivesIn($destination),
            'BACKUP-DB-ATOMIC-05: yarım döküm final adıyla duruyor; geri yükleme günü "yedek var" sanılır.'
        );
    }

    // --- BACKUP-DB-NEVER-DELETES-06 ---------------------------------------

    /**
     * Yeni koşu, duran hiçbir yedeği SİLMEZ.
     *
     * Retention bu paketin dışındadır ve bu bilinçlidir: silme kuralı
     * yazılmadan önce silme YETENEĞİ de olmamalı. Bir hata yüzünden
     * bozuk üretilen yeni bir arşivin, sağlam duran eskisini silmesi bu
     * paketin engellediği tek gerçek felaket senaryosudur.
     */
    public function test_an_existing_archive_is_never_deleted_by_a_new_run(): void
    {
        $this->requireCommand();
        $this->requireLivePostgres();

        $destination = $this->makeTempDir();
        $this->configureBackup($destination, (string) (new ExecutableFinder)->find('pg_dump'));

        $older = $destination.DIRECTORY_SEPARATOR.'zabuno-2026-01-01-000000.dump';
        file_put_contents($older, 'ESKİ AMA SAĞLAM YEDEK');
        $note = $destination.DIRECTORY_SEPARATOR.'README.txt';
        file_put_contents($note, 'operatör notu');

        $exitCode = Artisan::call(self::COMMAND);

        self::assertSame(0, $exitCode, Artisan::output());
        self::assertFileExists($older, 'BACKUP-DB-NEVER-DELETES-06: duran bir yedek silinmiş.');
        self::assertSame('ESKİ AMA SAĞLAM YEDEK', (string) file_get_contents($older));
        self::assertFileExists($note, 'BACKUP-DB-NEVER-DELETES-06: hedef dizinde yedek olmayan dosyalar da silinmemeli.');
        self::assertCount(
            2,
            $this->archivesIn($destination),
            'BACKUP-DB-NEVER-DELETES-06: yeni arşiv eskisinin YANINA gelmeli.'
        );
    }

    // --- BACKUP-DB-RESTORABLE-07 ------------------------------------------

    /**
     * Üretilen arşiv gerçekten okunabilir bir arşivdir ve KAYNAK
     * VERİTABANI DEĞİŞMEZ.
     *
     * Doğrulama `pg_restore --list` ile yapılır: arşivin içindekiler
     * listelenir, hiçbir şey geri YÜKLENMEZ. Üretim verisine yazma bu
     * paketin en sert yasağıdır ve bu madde onu tersinden kanıtlar —
     * yedekleme sonrası kaynaktaki satırlar ve içerikleri birebir aynıdır.
     */
    public function test_the_archive_is_listable_evidence_and_the_source_database_is_untouched(): void
    {
        $this->requireCommand();
        $this->requireLivePostgres();

        $table = $this->seedProbeTable();
        $before = $this->probeDigest($table);

        $destination = $this->makeTempDir();
        $this->configureBackup($destination, (string) (new ExecutableFinder)->find('pg_dump'));

        $exitCode = Artisan::call(self::COMMAND, ['--json' => true]);
        $output = Artisan::output();

        self::assertSame(0, $exitCode, $output);

        $archives = $this->archivesIn($destination);
        self::assertCount(1, $archives, 'BACKUP-DB-RESTORABLE-07: tam olarak bir arşiv beklenir.');
        $archive = $archives[0];
        self::assertGreaterThan(0, (int) filesize($archive), 'BACKUP-DB-RESTORABLE-07: boş bir dosya yedek değildir.');

        // Arşivin içi okunur — hiçbir şey geri yüklenmez.
        $list = new Process([(string) (new ExecutableFinder)->find('pg_restore'), '--list', $archive]);
        $list->run();

        self::assertTrue(
            $list->isSuccessful(),
            'BACKUP-DB-RESTORABLE-07: `pg_restore --list` arşivi okuyamadı: '.$list->getErrorOutput()
        );
        self::assertStringContainsString(
            $table,
            $list->getOutput(),
            'BACKUP-DB-RESTORABLE-07: arşiv, yedek alındığı anda var olan tabloyu içermiyor.'
        );

        // ÖZET, OPERATÖRÜN ELİNDE KALAN TEK DOĞRULAMA ARACIDIR.
        //
        // Geri yükleme günü sorulacak soru "dosya duruyor mu" değil,
        // "dosya BOZULMADAN duruyor mu"dur. Sessizce çürüyen bir diskte
        // fark ancak `pg_restore` patladığında anlaşılır — yani en geç.
        // Bu yüzden özet iki yerde yaşar: koşunun raporunda (`--json`,
        // günlüğe/izlemeye akan yer) ve arşivin YANINDA duran kardeş
        // dosyada (arşivle birlikte taşınan yer). İkisi ayrışırsa
        // kanıtın kendisi şüphelidir.
        $digest = (string) hash_file('sha256', $archive);

        /** @var array<string, mixed> $report */
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        foreach (['path', 'bytes', 'sha256'] as $field) {
            self::assertArrayHasKey(
                $field,
                $report,
                "BACKUP-DB-RESTORABLE-07: `--json` raporunda `{$field}` yok; "
                .'operatör hangi dosyanın alındığını raporundan okuyamaz.'
            );
        }

        self::assertSame($archive, $report['path'], 'BACKUP-DB-RESTORABLE-07: rapor başka bir dosyayı gösteriyor.');
        self::assertSame((int) filesize($archive), $report['bytes'], 'BACKUP-DB-RESTORABLE-07: bildirilen boyut diskteki boyut değil.');
        self::assertSame($digest, $report['sha256'], 'BACKUP-DB-RESTORABLE-07: bildirilen özet dosyanın özeti değil.');

        $sidecar = $archive.'.sha256';

        self::assertFileExists(
            $sidecar,
            'BACKUP-DB-RESTORABLE-07: arşivin yanında `.sha256` kardeş dosyası yok; '
            .'arşiv taşındığında özet geride kalır ve bütünlük bir daha doğrulanamaz.'
        );
        self::assertStringContainsString(
            $digest,
            (string) file_get_contents($sidecar),
            'BACKUP-DB-RESTORABLE-07: kardeş dosyadaki özet arşivin özetiyle aynı değil.'
        );

        self::assertSame(
            $before,
            $this->probeDigest($table),
            'BACKUP-DB-RESTORABLE-07: yedekleme kaynak veritabanını DEĞİŞTİRDİ.'
        );
    }

    // --- Canlı PostgreSQL yardımcıları ------------------------------------

    /**
     * PostgreSQL yoksa sonuç "geçti" değil BİLİNMİYOR'dur; CI'ın PostgreSQL
     * işinde eksik istemci aracı ise başarısızlıktır.
     */
    private function requireLivePostgres(): void
    {
        if (getenv('DB_CONNECTION') !== 'pgsql') {
            $this->markTestSkipped(
                'PostgreSQL bağlantısı yok: bu makinede sonuç BİLİNMİYOR ("geçti" değil). '
                .'Ölçüm CI\'ın DB_CONNECTION=pgsql işinde yapılır.'
            );
        }

        $finder = new ExecutableFinder;
        $missing = array_values(array_filter(
            ['pg_dump', 'pg_restore'],
            static fn (string $tool): bool => $finder->find($tool) === null,
        ));

        if ($missing === []) {
            return;
        }

        if (getenv('CI') === 'true') {
            $this->fail('CI yedek üretimini ÖLÇMELİ; PATH üzerinde eksik: '.implode(', ', $missing));
        }

        $this->markTestSkipped('pg_dump/pg_restore yok: sonuç bilinmiyor. Eksik: '.implode(', ', $missing));
    }

    /**
     * Göç dosyalarından bağımsız, kendi kendine yeten bir sonda tablosu:
     * yedeğin içinde ARANACAK ve kaynakta DEĞİŞMEDİĞİ ölçülecek satırlar.
     */
    private function seedProbeTable(): string
    {
        $table = 'zabuno_backup_probe_'.bin2hex(random_bytes(6));
        $this->probeTablesToDrop[] = $table;

        DB::statement('CREATE TABLE '.$table.' (id integer primary key, ad text not null)');
        DB::table($table)->insert([
            ['id' => 1, 'ad' => 'Restoran Kadıköy'],
            ['id' => 2, 'ad' => 'Mangal Beşiktaş'],
        ]);

        return $table;
    }

    private function probeDigest(string $table): string
    {
        $rows = DB::table($table)->orderBy('id')->get()->map(
            static fn (object $row): string => $row->id.':'.$row->ad,
        )->all();

        return hash('sha256', implode('|', $rows));
    }
}
