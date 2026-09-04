<?php

declare(strict_types=1);

namespace App\Domain\Media;

use InvalidArgumentException;

/**
 * Tek bir ADLANDIRILMIŞ türev kuralı — "Boyut motoru" (`docs/108` §6.1).
 *
 * `SlotPolicy` bir YERİN ihtiyacını anlatır ("menü kartı en az 1000×1000
 * ister"). Bu sınıf bir ÖLÇÜNÜN işini anlatır ("small · 320 px · sığdır ·
 * menü kartı ve telefon"). İkisi ayrı sorulardır ve tek nesnede toplanınca
 * ikisi de yarım kalıyordu: slot listesi bir sayı yığınıydı, sayının ne işe
 * yaradığını hiçbir yer söylemiyordu.
 *
 * Domain katmanındadır ve çerçeve bilmez: `config()` okumaz, veritabanına
 * bakmaz.
 */
final readonly class DerivativeRule
{
    /** @param list<string> $formats */
    public function __construct(
        public string $name,
        public int $width,
        /**
         * Sabit çerçeveli kuralın YÜKSEKLİĞİ; yoksa `null`.
         *
         * Yalnız `social` taşır (1200×630) ve o çerçeve bizim kararımız
         * değil, paylaşılan yerin dayatmasıdır.
         */
        public ?int $height,
        /** `crop` (kırp) ya da `contain` (sığdır). */
        public string $fit,
        public array $formats,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromArray(string $name, array $row): self
    {
        $fit = (string) ($row['fit'] ?? 'contain');

        if ($fit !== 'crop' && $fit !== 'contain') {
            throw new InvalidArgumentException("Bilinmeyen sığdırma: {$fit}");
        }

        $height = $row['height'] ?? null;

        return new self(
            name: $name,
            width: (int) $row['width'],
            height: $height === null ? null : (int) $height,
            fit: $fit,
            formats: array_values(array_map('strval', (array) ($row['formats'] ?? []))),
        );
    }

    /**
     * Bu kural, verilen slot türev listesinde gerçekten üretiliyor mu?
     *
     * Kuralın ADLANDIRILMIŞ olması ÜRETİLDİĞİ anlamına gelmez: boru hattı
     * bugün slot başına genişlik listesinden üretiyor. Ekranda "print ·
     * 2480 px" yazıp o dosyanın hiç var olmadığını söylememek, sahibi
     * olmayan bir yeteneğe güvendirirdi.
     *
     * @param  list<int>  $slotRenditionWidths
     */
    public function isProducedBy(array $slotRenditionWidths): bool
    {
        return in_array($this->width, $slotRenditionWidths, true);
    }
}
