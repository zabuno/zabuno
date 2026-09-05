<?php

declare(strict_types=1);

namespace App\Application\Platform\UseCase;

use App\Application\Platform\Port\HostCapabilityProbePort;
use Illuminate\Support\Facades\DB;

/**
 * Host yeteneklerini ölçer, bağlayıcı sonucu ve devreye giren
 * graceful-degradation planını tek bir kayıt olarak yazar — `docs/16` MED-01.
 *
 * Neden kayıt? Çünkü "bizim sunucuda çalışıyordu" cümlesi bir kanıt değildir.
 * Bir host değiştiğinde neyin değiştiğini ancak iki kayıt yan yana
 * konduğunda görebiliriz.
 *
 * Eksik bir yetenek HATA DEĞİLDİR: `skills/shared-host-capability.md`
 * hard-fail'i yasaklar. Eksik yetenek, planlı bir düşüşü tetikler ve o düşüş
 * burada adıyla yazılır.
 */
final class RecordHostCapabilityEvidence
{
    public function __construct(private readonly HostCapabilityProbePort $probe) {}

    /** @return array{capabilities: array<string, bool|string>, degradations: list<string>, id: int} */
    public function execute(): array
    {
        $capabilities = $this->probe->probe();
        $degradations = self::degradationsFor($capabilities);

        $id = (int) DB::table('host_capability_evidence')->insertGetId([
            'php_version' => (string) $capabilities['php_version'],
            'capabilities' => json_encode($capabilities, JSON_THROW_ON_ERROR),
            'degradations' => json_encode($degradations, JSON_THROW_ON_ERROR),
            'claim' => 'Read-only capability probe of the host running this process. '
                .'It is not a claim about any other environment, and it leaves nothing behind on the host.',
            'ran_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['capabilities' => $capabilities, 'degradations' => $degradations, 'id' => $id];
    }

    /**
     * Eksik yeteneğin ürün üzerindeki KARŞILIĞI.
     *
     * Burada "imagick yok" demek yetmez; restoran sahibinin ne yaşayacağını
     * söylemek gerekir. Bir işletim kararı ancak o zaman verilebilir.
     *
     * @param  array<string, bool|string>  $capabilities
     * @return list<string>
     */
    public static function degradationsFor(array $capabilities): array
    {
        $degradations = [];

        if ($capabilities['imagick'] === false && $capabilities['gd'] === true) {
            $degradations[] = 'image-derivatives:gd — Imagick yok; görsel türevleri GD ile üretilir. '
                .'Kalite biraz düşer, akış çalışmaya devam eder.';
        }

        if ($capabilities['imagick'] === false && $capabilities['gd'] === false) {
            $degradations[] = 'image-derivatives:none — ne Imagick ne GD var; yüklenen görsel '
                .'karantinada kalır ve türev üretilmez. Menüye görsel eklenemez.';
        }

        if ($capabilities['exec_enabled'] === false) {
            $degradations[] = 'malware-scan:unavailable — `exec`/`proc_open` kapalı; ClamAV çağrılamaz. '
                .'Tarama yapılamadığı için yüklenen dosya asla public olmaz (fail-closed).';
        }

        /*
            TARAYICININ İKİ AYRI ARIZASI, İKİ AYRI SATIR.

            Sonuç ikisinde de aynı: dosya beklemede kalır ve asla public
            olmaz. Ama SEBEP farklı ve düzeltme farklı yerde:

            - `not-configured` bir ortam kararıdır; sürücü hiç açılmamış,
              aranacak bir ikili de yok.
            - `binary-unusable` bir KURULUM KAZASIDIR; birileri sürücüyü
              açmış ama ikili yerinde değil ya da çalıştırma biti yok. Bu
              en sinsi hâldir: kurulum yapılmış sanılır, sonuç ise hiç
              kurulmamış hâlle birebir aynıdır.

            İkisini tek cümleye indirmek, operatörü yanlış yerde düzeltme
            aratırdı.
        */
        if (($capabilities['malware_scanner_driver'] ?? 'unavailable') === 'unavailable') {
            $degradations[] = 'malware-scan:not-configured — tarayıcı sürücüsü açılmamış. '
                .'Yüklenen dosya taranamadığı için beklemede kalır ve menüde kullanılamaz (fail-closed).';
        } elseif (($capabilities['malware_scanner_binary_usable'] ?? false) === false) {
            $degradations[] = 'malware-scan:binary-unusable — sürücü açık ama yapılandırılan ikili '
                .'yok ya da çalıştırılabilir değil. Tarama hiç DENENMEZ; sonuç, tarayıcının hiç '
                .'kurulmamış hâlinden ayırt edilemez. Yol ve `chmod +x` kontrol edilmeli.';
        }

        if ($capabilities['ffmpeg'] === false) {
            $degradations[] = 'video-derivatives:none — ffmpeg yok; video türevi üretilmez. '
                .'Stage 1 kapsamında video zaten yoktur, bu beklenen durumdur.';
        }

        if ($capabilities['symlink_supported'] === false) {
            $degradations[] = 'public-storage:copy — symlink desteklenmiyor; `storage:link` yerine '
                .'kopyalama/alternatif servis yolu gerekir.';
        }

        if ($capabilities['redis'] === false) {
            $degradations[] = 'cache/queue:database — Redis yok; önbellek ve kuyruk veritabanı '
                .'sürücüsüyle çalışır. Paylaşımlı barındırmada beklenen durumdur.';
        }

        $uploadBytes = self::toBytes((string) $capabilities['upload_max_filesize']);

        if ($uploadBytes > 0 && $uploadBytes < 5 * 1024 * 1024) {
            $degradations[] = 'upload-cap:host — yükleme sınırı host tarafından '
                .$capabilities['upload_max_filesize'].' ile belirleniyor. Bir restoran telefonuyla '
                .'çektiği fotoğrafı yükleyemeyebilir; uygulamanın kendi sınırı yoktur.';
        }

        return $degradations;
    }

    /** `2M`, `512K` gibi PHP ini değerlerini bayta çevirir. */
    public static function toBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
