<?php

declare(strict_types=1);

namespace App\Domain\Rating;

use InvalidArgumentException;

/**
 * PUANLAMA ALGORİTMASI BİR DOSYADIR — `docs/116` §2 (sahibin 2026-09-05
 * kararı): *"Puanlamanın KPI'ları, OKR'ları bir algoritma dosyasına
 * bağlıdır."*
 *
 * ═══ NEDEN DOSYA, NEDEN AYAR EKRANI DEĞİL ═══
 *
 * Ağırlıkları panelden düzenlenebilir yapmak cazip görünür ve yanlıştır:
 * ölçüm ve para etkileyen bir kural, gözden geçirme ve testten geçmeden
 * değişmemeli. "Panelden ayarlanabilir algoritma", ilk yanlış değerde
 * sessizce HER ÜRÜNÜN puanını değiştirir — ve bunu ancak aylar sonra,
 * "menüm neden böyle sıralanıyor?" sorusuyla fark ederiz.
 *
 * ═══ NEDEN LARAVEL'İN `config()` YARDIMCISI KULLANILMIYOR ═══
 *
 * Domain katmanı çerçeveden bağımsızdır (`docs/03` ADR-L02) ve
 * `OnionBoundaryTest` bunu zorlar. Ama asıl sebep mimari saflık değil:
 * `config()` çalışma zamanında ÜSTÜNE YAZILABİLİR. Bir test ya da bir
 * servis sağlayıcı `config(['rating-algorithm.v1.weights' => ...])`
 * diyebilseydi, "algoritma depoda yaşar" iddiası bir cümleden ibaret
 * kalırdı. Burada okunan şey diskteki dosyanın kendisidir.
 *
 * ═══ ESKİ SÜRÜM SİLİNMEZ ═══
 *
 * Sürüm yükseltmek bir PAKETTİR: yeni dosya, yeni numara, eski dosya yerinde
 * kalır. `rating_scores.algorithm_version` o dosyayı işaret eder; dosya
 * silinirse "bu puan neden böyleydi?" sorusunun cevabı da silinir.
 */
final class RatingAlgorithm
{
    /**
     * Bugün yürürlükte olan sürüm.
     *
     * Yeni bir sürüm yazmak bu sabiti değiştirmeyi gerektirir ve bu
     * kasıtlıdır: dizine bir dosya bırakmak, o dosyanın yürürlüğe girmesi
     * anlamına gelmez. Yürürlük bir karardır, bir yan etki değil.
     */
    public const CURRENT_VERSION = 1;

    /** @var array<int, self> Aynı süreçte aynı dosyayı ikinci kez okumamak için. */
    private static array $loaded = [];

    /** Tipli kötüye kullanım kuralları — ilk sorulduğunda türetilir. */
    private ?RatingAbuse $abuseRules = null;

    /**
     * @param  array<string, float>  $weights  kaynak değeri => ağırlık
     * @param  array<string, mixed>  $abuse
     */
    private function __construct(
        public readonly int $version,
        /** Neyi iyileştirmeye çalışıyoruz — yazılı olmazsa algoritma kimsenin kabul etmediği bir hedefe kayar. */
        public readonly string $kpi,
        public readonly RatingOkr $okr,
        public readonly array $weights,
        public readonly RatingRecency $recency,
        public readonly RatingThresholds $thresholds,
        /** Gösterilen puanın ölçeği (ör. 5 üzerinden). Sinyalin kendi ölçeği buna çevrilir. */
        public readonly int $scaleMax,
        public readonly array $abuse,
    ) {}

    public static function current(): self
    {
        $algorithm = self::version(self::CURRENT_VERSION);

        if (! $algorithm instanceof self) {
            throw new InvalidArgumentException(
                'Rating algorithm v'.self::CURRENT_VERSION.' is missing from config/rating-algorithm.'
            );
        }

        return $algorithm;
    }

    /** Yoksa `null` — çağıran "bu sürüm artık açıklanamıyor" diyebilsin. */
    public static function version(int $version): ?self
    {
        if (isset(self::$loaded[$version])) {
            return self::$loaded[$version];
        }

        $path = self::directory().'/v'.$version.'.php';

        if (! is_file($path)) {
            return null;
        }

        /** @var mixed $definition */
        $definition = require $path;

        if (! is_array($definition)) {
            throw new InvalidArgumentException($path.' must return an array.');
        }

        return self::$loaded[$version] = self::fromArray($version, $definition);
    }

    public function weightForSource(RatingSource $source): float
    {
        return $this->weights[$source->value];
    }

    /**
     * Kötüye kullanım kurallarının TİPLİ hâli — `docs/116` §4 (P4).
     *
     * Ham `$abuse` dizisi yerinde bırakıldı ve bu kasıtlı: dizi, dosyanın
     * o bölümünün olduğu gibi okunabilmesini sağlıyor. Ama uç bir diziye
     * `$abuse['burst_window_minutes'] ?? 15` diye sorsaydı, dosyadaki bir
     * yazım hatası sessizce kodun varsayılanına düşerdi. Tipli okuma eksik
     * alanı gürültüyle reddeder.
     */
    public function abuseRules(): RatingAbuse
    {
        return $this->abuseRules ??= RatingAbuse::fromArray($this->version, $this->abuse);
    }

    /**
     * Bir sinyalin bugünkü ağırlığı: kaynak ağırlığı × zaman sönümü.
     *
     * İki çarpanın çarpımı olması bir tercih değil, iki ayrı sorunun
     * cevabıdır: "bu oy ne kadar güvenilir?" (kaynak) ve "hâlâ bugünü
     * anlatıyor mu?" (zaman). Toplasaydık, üç yıllık bir masa oyu dünkü bir
     * Google oyundan ağır kalırdı — çünkü kaynak puanı sönümü kurtarırdı.
     */
    public function weightForSignal(RatingSource $source, float $ageInDays): float
    {
        return $this->weightForSource($source) * $this->recency->weightAfterDays($ageInDays);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private static function fromArray(int $version, array $definition): self
    {
        $weights = [];

        foreach (RatingSource::cases() as $source) {
            /*
                SESSİZ SIFIR YASAK. Enum'a yeni bir kaynak eklenip ağırlık
                dosyasına eklenmeyi unutulsaydı, o kaynaktan gelen her oy
                hesaba katılmamış olurdu ve hiçbir yerde bir hata görünmezdi.
                Eksik ağırlık burada gürültüyle patlar.
            */
            if (! isset($definition['weights'][$source->value])) {
                throw new InvalidArgumentException(
                    'Rating algorithm v'.$version.' has no weight for source `'.$source->value.'`.'
                );
            }

            $weights[$source->value] = (float) $definition['weights'][$source->value];
        }

        /*
            MASADAN GELEN OY HER DIŞ KAYNAKTAN AĞIRDIR ve bu ürünün ayırt
            edici iddiasıdır: oy veren kişi gerçekten oradaydı. Sıralama
            dosyada bozulabilseydi, iddia bir pazarlama cümlesine dönerdi.
        */
        foreach (RatingSource::cases() as $source) {
            if ($source->provesPresenceAtTheTable()) {
                continue;
            }

            if ($weights[$source->value] >= $weights[RatingSource::GuestScan->value]) {
                throw new InvalidArgumentException(
                    'Rating algorithm v'.$version.': `'.$source->value
                    .'` cannot weigh as much as a signal given at the table.'
                );
            }
        }

        $kpi = (string) ($definition['kpi'] ?? '');

        if (trim($kpi) === '') {
            throw new InvalidArgumentException('Rating algorithm v'.$version.' does not say what it optimises.');
        }

        $scaleMax = (int) ($definition['scale_max'] ?? 0);

        if ($scaleMax <= 0) {
            throw new InvalidArgumentException('Rating algorithm v'.$version.' must declare a positive scale.');
        }

        return new self(
            $version,
            $kpi,
            RatingOkr::fromArray(is_array($definition['okr'] ?? null) ? $definition['okr'] : []),
            $weights,
            RatingRecency::fromArray(is_array($definition['recency'] ?? null) ? $definition['recency'] : []),
            RatingThresholds::fromArray(is_array($definition['thresholds'] ?? null) ? $definition['thresholds'] : []),
            $scaleMax,
            is_array($definition['abuse'] ?? null) ? $definition['abuse'] : [],
        );
    }

    /**
     * Depo kökündeki `config/rating-algorithm`.
     *
     * Yol `__DIR__`'den türer, çerçeveden değil: bu sınıf `config_path()`'i
     * çağırsaydı Domain katmanı Laravel'e bağlanırdı ve dosyanın yeri
     * uygulamanın kurulumuna göre değişebilirdi.
     */
    private static function directory(): string
    {
        return dirname(__DIR__, 3).'/config/rating-algorithm';
    }
}
