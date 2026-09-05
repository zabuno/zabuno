<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Domain\Media\MediaSizeKind;
use App\Domain\Media\UploadSizeLimits;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * TÜR BAZLI YÜKLEME SINIRI, AKTARIM ZİNCİRİNİN TAVANINI AŞAMAZ (FF-158).
 *
 * MÜŞTERİ SORUNU. Sahip basılı menüsünü tarayıp yüklüyor; tek düz bir bayt
 * sınırı (30 MB, her tür için aynı) hem meşru bir A3 taramasını hem de
 * kırk sayfalık bir fiyat listesini aynı cümleyle reddediyordu. Sınır artık
 * TÜRE göre veriliyor (`config/media-slots.php` → `limits.max_bytes_by_kind`).
 *
 * BU DOSYANIN KORUDUĞU ŞEY O SAYILAR DEĞİL, ONLARIN TAVANI.
 *
 * Bir yükleme uygulamaya ulaşmadan önce dört halkadan geçer ve her halkanın
 * kendi gövde sınırı vardır:
 *
 *   Caddy (`request_body max_size`) → nginx (`client_max_body_size`)
 *   → PHP (`upload_max_filesize` / `post_max_size`) → ClamAV
 *   (`StreamMaxLength`, `MaxFileSize`).
 *
 * Uygulamanın ilan ettiği bir tür sınırı bu halkaların EN KÜÇÜĞÜNÜ aşarsa
 * iki arıza doğar ve ikisi de sessizdir:
 *
 *   1. Dosya doğrulamaya HİÇ ULAŞMAZ. `post_max_size` aşıldığında PHP
 *      gövdeyi tamamen atar; `$_FILES` boş gelir ve kullanıcıya yüklediği
 *      dosyayı yüklemediği söylenir (`docker/Dockerfile` bu tuzağı kendi
 *      yorumunda anlatıyor).
 *   2. DOSYA TARANMADAN GEÇER. ClamAV, `MaxFileSize` üstündeki bir gövdeyi
 *      hata vermeden ATLAR — sonuç "temiz" görünür. DevOps bu deliği
 *      2026-09-05'te kapattı (`a85f95d`, `docker/clamd.conf`); uygulama
 *      tarafında ilan edilen bir sayı onu tek başına geri açabilir.
 *
 * TAVAN ELLE KOPYALANMAZ, OKUNUR. DevOps'un dosyalarındaki sayıyı buraya
 * yazmak, bir gün sessizce ayrışacak ikinci bir gerçek üretirdi — kapının
 * kendisi de o zaman yalan söylerdi. Bu yüzden her tavan kendi kaynak
 * dosyasından ayrıştırılır. Bu dosyalar DevOps'un alanıdır; burada YALNIZ
 * OKUNURLAR.
 *
 * `MaxScanSize` (200M) BİLEREK bir tavan sayılmadı. O, tek bir taramada
 * açılan TOPLAM bayt bütçesidir (arşiv/gömülü akış genişlediğinde işler) —
 * gövdenin kendi boyutu değil. Bir PDF'in genişleyince kaç bayt olacağı
 * içeriğine bağlıdır ve yapılandırmadan türetilemez; oradan bir dosya
 * sınırı çıkarmak uydurma olurdu. Yine de bütçenin akıştan küçük olmaması
 * bir tutarlılık şartıdır ve aşağıda o hâliyle sınanır.
 *
 * Requirement IDs: MEDIA-SIZE-KIND-LIMIT-01, MEDIA-SIZE-TRANSPORT-CEILING-01,
 * MEDIA-SIZE-NO-VIDEO-NUMBER-01.
 */
final class UploadSizeCeilingTest extends TestCase
{
    /**
     * Aktarım zincirindeki tavanlar — hangi dosyadan, hangi desenle.
     *
     * Anahtar rapora ve kırılma mesajına aynen çıkar: kapı kırıldığında
     * hangi halkanın konuştuğu tek bakışta görünmeli.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const CEILINGS = [
        'PHP upload_max_filesize (docker/Dockerfile)' => [
            'docker/Dockerfile',
            "/upload_max_filesize\s*=\s*([0-9]+\s*[KMG]?B?)/i",
        ],
        'PHP post_max_size (docker/Dockerfile)' => [
            'docker/Dockerfile',
            "/post_max_size\s*=\s*([0-9]+\s*[KMG]?B?)/i",
        ],
        'nginx client_max_body_size (docker/nginx.conf)' => [
            'docker/nginx.conf',
            '/client_max_body_size\s+([0-9]+\s*[kmgKMG]?[bB]?)\s*;/',
        ],
        'Caddy request_body max_size (docker/Caddyfile)' => [
            'docker/Caddyfile',
            '/max_size\s+([0-9]+\s*[kmgKMG]?[bB]?)/',
        ],
        'ClamAV StreamMaxLength (docker/clamd.conf)' => [
            'docker/clamd.conf',
            '/^\s*StreamMaxLength\s+([0-9]+\s*[kmgKMG]?[bB]?)\s*$/mi',
        ],
        'ClamAV MaxFileSize (docker/clamd.conf)' => [
            'docker/clamd.conf',
            '/^\s*MaxFileSize\s+([0-9]+\s*[kmgKMG]?[bB]?)\s*$/mi',
        ],
    ];

    #[Test]
    public function no_type_limit_exceeds_the_smallest_ceiling_in_the_transport_chain(): void
    {
        $ceilings = $this->ceilings();
        $tightestName = (string) array_search(min($ceilings), $ceilings, true);
        $tightest = $ceilings[$tightestName];

        /*
            YAZILAN ve UYGULANAN değerlerin İKİSİ birden sınanır.

            `UploadSizeLimits` bir tür sınırını mutlak tavana kırpar, yani
            yapılandırmaya tavanın üstünde bir sayı yazmak bugün sessizce
            etkisiz kalır. Yalnız uygulanan değere bakan bir kapı bunu hiç
            görmezdi — ve o sayı yapılandırmada bir SÖZ olarak durur: bir
            gün tavan yükselir, kimse o satırı gözden geçirmez ve sınır
            aniden yürürlüğe girer.
        */
        $limits = UploadSizeLimits::fromArray((array) config('media-slots.limits', []));
        $declared = [];

        foreach ((array) config('media-slots.limits.max_bytes_by_kind', []) as $kind => $bytes) {
            $declared[] = [(string) $kind, 'yapılandırmada yazan', (int) $bytes];
        }

        foreach ($limits->all() as $kind => $bytes) {
            $declared[] = [$kind, 'uygulanan', $bytes];
        }

        foreach ($declared as [$kind, $source, $bytes]) {
            self::assertLessThanOrEqual(
                $tightest,
                $bytes,
                sprintf(
                    "`%s` türünün sınırı %s (%s); aktarım zincirinin en dar halkası %s (%s).\n".
                    "YAPILACAK: `config/media-slots.php` → `limits.max_bytes_by_kind.%s` değerini o tavanın ALTINA çekin;\n".
                    "sayıyı yükseltmek isteniyorsa önce DevOps tarafında Caddy/nginx/PHP/ClamAV sınırları birlikte yükseltilir.\n".
                    'NEDEN TEHLİKELİ: tavanı aşan bir dosya ya doğrulamaya hiç ulaşmaz (PHP gövdeyi atar, kullanıcıya '.
                    '"dosya yüklemediniz" denir) ya da ClamAV `MaxFileSize` üstünde kaldığı için TARANMADAN "temiz" sayılır — '.
                    'DevOps `a85f95d` ile tam olarak bu deliği kapattı.',
                    $kind,
                    $this->humanBytes($bytes),
                    $source,
                    $this->humanBytes($tightest),
                    $tightestName,
                    $kind,
                ),
            );
        }
    }

    #[Test]
    public function the_absolute_cap_also_stays_under_the_smallest_ceiling(): void
    {
        $ceilings = $this->ceilings();
        $tightest = min($ceilings);
        $cap = (int) config('media-slots.limits.max_bytes');

        self::assertLessThanOrEqual(
            $tightest,
            $cap,
            sprintf(
                "`limits.max_bytes` (%s) aktarım zincirinin en dar halkasını (%s) aşıyor.\n".
                "YAPILACAK: `config/media-slots.php` içindeki değeri düşürün.\n".
                'NEDEN TEHLİKELİ: bu sayı `StoreMediaRequest` içindeki `max:` kuralını besler; tavanın üstünde bir '.
                'değer, doğrulama kapısının HİÇ ÇALIŞMADIĞI bir aralık açar.',
                $this->humanBytes($cap),
                $this->humanBytes($tightest),
            ),
        );
    }

    /**
     * Tarama bütçesi, akıştan küçük olamaz.
     *
     * `MaxScanSize` bir aktarım tavanı değildir (bkz. sınıf başlığı), ama
     * `StreamMaxLength`in altına düşerse ClamAV kendi kabul ettiği akışı
     * bitiremez: dosya tam taranmadan sonuç döner.
     */
    #[Test]
    public function the_scan_budget_is_not_smaller_than_the_stream_it_accepts(): void
    {
        $stream = $this->parseBytes(
            $this->matchIn('docker/clamd.conf', '/^\s*StreamMaxLength\s+(\S+)\s*$/mi'),
        );
        $budget = $this->parseBytes(
            $this->matchIn('docker/clamd.conf', '/^\s*MaxScanSize\s+(\S+)\s*$/mi'),
        );

        self::assertGreaterThanOrEqual(
            $stream,
            $budget,
            'ClamAV `MaxScanSize` (%s) `StreamMaxLength` (%s) altında. YAPILACAK: `docker/clamd.conf` DevOps alanıdır; '.
            'bunu onlara bildirin. NEDEN TEHLİKELİ: kabul edilen bir akış tam taranamadan sonuçlanır, yani dosya '.
            '"temiz" görünürken gövdesinin bir kısmına hiç bakılmamıştır.',
        );
    }

    /**
     * VİDEO İÇİN SAYI YOKTUR — ve olmaması sınanır.
     *
     * `docs/109` §8.2: "Depo video kabul etmiyor; eksik olan ffmpeg değil,
     * video hattı hiç yok." Yapılandırmaya "video: n MB" yazmak, olmayan
     * bir yeteneği ilan etmek olurdu: sahip sayıyı okur, MP4'ünü yükler ve
     * ret cümlesiyle karşılaşır. Sınır, hat açıldığı GÜN eklenir.
     */
    #[Test]
    public function no_size_limit_is_declared_for_a_type_the_product_cannot_accept(): void
    {
        $configured = array_keys((array) config('media-slots.limits.max_bytes_by_kind', []));
        $known = array_map(static fn (MediaSizeKind $kind): string => $kind->value, MediaSizeKind::cases());

        foreach ($configured as $kind) {
            self::assertContains(
                (string) $kind,
                $known,
                sprintf(
                    "`limits.max_bytes_by_kind.%s` tanınmayan bir tür için sınır ilan ediyor.\n".
                    "YAPILACAK: ya `App\\Domain\\Media\\MediaSizeKind` içine gerçek bir alım yolu ile birlikte ekleyin, ya satırı silin.\n".
                    'NEDEN TEHLİKELİ: bir sayı bir SÖZDÜR. Kabul edilmeyen bir tür için sınır yazmak, ekranda '.
                    '"şu boyuta kadar yükleyebilirsiniz" diye görünür ve sahip yükleyene kadar bunun yalan olduğunu bilmez.',
                    (string) $kind,
                ),
            );
        }

        // Her slotun `formats` listesi de aynı sözü taşır: kabul edilen her
        // biçimin bir tür karşılığı OLMALI, yoksa o dosya sınırsız kalır.
        foreach ((array) config('media-slots.slots', []) as $slot => $row) {
            foreach ((array) ($row['formats'] ?? []) as $format) {
                self::assertNotNull(
                    MediaSizeKind::tryFromFormat((string) $format),
                    sprintf(
                        "`%s` slotu `%s` biçimini kabul ediyor ama o biçimin bayt sınırı yok.\n".
                        "YAPILACAK: `MediaSizeKind::tryFromFormat` içine eşleyin ve `max_bytes_by_kind` altında sınırını verin.\n".
                        'NEDEN TEHLİKELİ: eşlenmemiş bir biçim yalnız mutlak tavana takılır; tür bazlı kapı onun için hiç çalışmaz.',
                        (string) $slot,
                        (string) $format,
                    ),
                );
            }
        }
    }

    // --- Tavanların okunması ------------------------------------------------

    /** @return array<string, int> */
    private function ceilings(): array
    {
        $found = [];

        foreach (self::CEILINGS as $name => [$file, $pattern]) {
            $found[$name] = $this->parseBytes($this->matchIn($file, $pattern));
        }

        return $found;
    }

    private function matchIn(string $relative, string $pattern): string
    {
        $body = $this->devopsFile($relative);

        if (preg_match($pattern, $body, $matches) !== 1) {
            self::fail(sprintf(
                "`%s` içinde beklenen sınır satırı bulunamadı (desen: %s).\n".
                'YAPILACAK: satır DevOps tarafında yeniden adlandırıldıysa bu testteki deseni güncelleyin; '.
                "silindiyse sınırın nereye taşındığını DevOps'a sorun.\n".
                'NEDEN TEHLİKELİ: okunamayan bir tavan, olmayan bir tavan gibi davranır ve kapı sessizce her sayıyı kabul eder.',
                $relative,
                $pattern,
            ));
        }

        return trim($matches[1]);
    }

    /**
     * DevOps dosyasını, DAĞITIMDA olacağı hâliyle okur.
     *
     * Önce çalışma ağacı; orada yoksa `origin/main`. Sebep somut: ClamAV
     * yapılandırması `a85f95d` ile main'e girdi ve bu paketin dalı o
     * işlemenin gerisinde olabilir. Dağıtımın hedefi main'dir — yani
     * tavan, dalın tabanından bağımsız olarak main'in dosyasıdır. CI'da
     * bu satır zaten hiç çalışmaz: pull request kontrolü main ile
     * birleştirilmiş ağacı çıkarır ve dosya orada gerçekten vardır.
     *
     * İkisi de yoksa test DURUR: okunamayan bir tavan sessizce
     * atlanamaz.
     */
    private function devopsFile(string $relative): string
    {
        $path = base_path($relative);

        if (is_readable($path)) {
            return (string) file_get_contents($path);
        }

        $command = sprintf(
            'git -C %s show origin/main:%s 2>/dev/null',
            escapeshellarg(base_path()),
            escapeshellarg($relative),
        );
        $body = (string) @shell_exec($command);

        if (trim($body) !== '') {
            return $body;
        }

        throw new RuntimeException(sprintf(
            "`%s` ne çalışma ağacında ne de `origin/main` üzerinde okunabildi.\n".
            "YAPILACAK: `git fetch origin` çalıştırın; dal ClamAV işlemesinin (`a85f95d`) gerisindeyse tabanı güncelleyin.\n".
            'NEDEN TEHLİKELİ: bu dosya aktarım zincirinin tavanını taşıyor. Okunamadan geçilirse, tür sınırlarının '.
            'tavanı aşıp aşmadığı HİÇ sınanmamış olur.',
            $relative,
        ));
    }

    /** `50M`, `52m`, `52MB`, `60M`, `1024` → bayt. */
    private function parseBytes(string $raw): int
    {
        $value = strtoupper(str_replace(' ', '', $raw));

        if (preg_match('/^([0-9]+)(K|KB|M|MB|G|GB)?$/', $value, $parts) !== 1) {
            self::fail(sprintf(
                "Sınır değeri okunamadı: `%s`.\n".
                "YAPILACAK: birim eki beklenmedik; bu testteki ayrıştırıcıya ekleyin.\n".
                'NEDEN TEHLİKELİ: yanlış ayrıştırılan bir tavan, gerçekte olduğundan büyük görünebilir.',
                $raw,
            ));
        }

        $number = (int) $parts[1];

        return match ($parts[2] ?? '') {
            'K', 'KB' => $number * 1024,
            'M', 'MB' => $number * 1024 * 1024,
            'G', 'GB' => $number * 1024 * 1024 * 1024,
            default => $number,
        };
    }

    private function humanBytes(int $bytes): string
    {
        return round($bytes / 1048576, 1).' MB';
    }
}
