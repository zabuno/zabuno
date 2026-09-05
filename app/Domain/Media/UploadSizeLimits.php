<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * Tür bazlı bayt sınırları (FF-158).
 *
 * Domain katmanındadır ve çerçeve bilmez: `config()` okumaz. Yapılandırma
 * dizisinden kurulur, kararı kendisi verir — `SlotPolicy` ile aynı ayrım.
 *
 * İKİ SAYI, İKİ FARKLI İŞ:
 *
 *   - `max_bytes` MUTLAK TAVANDIR. Hiçbir tür onu aşamaz; aşmaya çalışan
 *     bir tür sınırı sessizce ona kırpılır (`min`). Bu, tek satırla acil
 *     daraltma yapılabilmesi içindir: aktarım zinciri (Caddy → nginx → PHP
 *     → ClamAV) daralırsa üç ayrı satır düzeltmek gerekmez, tavanı indirmek
 *     yeter ve türlerin hiçbiri onun üstünde kalamaz.
 *   - `max_bytes_by_kind` her türün KENDİ sınırıdır ve tavandan küçüktür.
 *
 * Tanımlanmamış bir tür tavana düşer. Bu bir kaçak değil, bilinçli bir
 * varsayılandır: sınırsız kalmaktansa mutlak tavana takılsın. Yapılandırmada
 * eksik kalan bir tür ayrıca kapıda görünür (`UploadSizeCeilingTest`).
 */
final readonly class UploadSizeLimits
{
    /** @param array<string, int> $byKind */
    private function __construct(
        public int $ceilingBytes,
        private array $byKind,
    ) {}

    /** @param array<string, mixed> $limits `config('media-slots.limits')` */
    public static function fromArray(array $limits): self
    {
        $ceiling = (int) ($limits['max_bytes'] ?? 0);
        $byKind = [];

        foreach ((array) ($limits['max_bytes_by_kind'] ?? []) as $kind => $bytes) {
            $byKind[(string) $kind] = (int) $bytes;
        }

        return new self($ceiling, $byKind);
    }

    /**
     * Bu tür için gerçekten uygulanan sınır.
     *
     * Tavan HER ZAMAN kazanır: `min` burada bir savunma değil, sözleşmenin
     * kendisidir (bkz. sınıf başlığı).
     */
    public function bytesFor(MediaSizeKind $kind): int
    {
        $declared = $this->byKind[$kind->value] ?? $this->ceilingBytes;

        return min($declared, $this->ceilingBytes);
    }

    /**
     * Uygulanan sınırların tamamı — kapı ve uç nokta için.
     *
     * Yapılandırmada YAZAN değil, GEÇERLİ OLAN değerler döner: ekranın
     * yazdığı sayı ile kapının uyguladığı sayı bir gün ayrışmasın.
     *
     * @return array<string, int>
     */
    public function all(): array
    {
        $all = [];

        foreach (MediaSizeKind::cases() as $kind) {
            $all[$kind->value] = $this->bytesFor($kind);
        }

        return $all;
    }
}
