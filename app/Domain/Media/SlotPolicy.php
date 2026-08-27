<?php

declare(strict_types=1);

namespace App\Domain\Media;

use InvalidArgumentException;

/**
 * Tek bir slotun politikası — o yerde bir görselin ne olması gerektiği.
 *
 * Domain katmanındadır ve çerçeve bilmez: `config()` okumaz, veritabanına
 * bakmaz. Yapılandırmadan kurulur, kararı kendisi verir.
 */
final readonly class SlotPolicy
{
    /**
     * @param  list<string>  $formats
     * @param  list<int>  $renditions
     */
    public function __construct(
        public string $key,
        public MediaSurface $surface,
        public int $minWidth,
        public int $minHeight,
        public ?string $aspect,
        public array $formats,
        public string $transparency,
        public array $renditions,
        public bool $altRequired,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromArray(string $key, array $row): self
    {
        return new self(
            key: $key,
            surface: $row['surface'] instanceof MediaSurface
                ? $row['surface']
                : MediaSurface::from((string) $row['surface']),
            minWidth: (int) $row['min_width'],
            minHeight: (int) $row['min_height'],
            aspect: $row['aspect'] === null ? null : (string) $row['aspect'],
            formats: array_values(array_map('strval', (array) $row['formats'])),
            transparency: (string) $row['transparency'],
            renditions: array_values(array_map('intval', (array) $row['renditions'])),
            altRequired: (bool) $row['alt_required'],
        );
    }

    /**
     * Bu ölçüler bu slot için yeterli mi?
     *
     * Upscale YASAKTIR (INV-01): giriş en büyük rendition'dan küçükse
     * büyütülmez, menüde bulanık görünür. Bu yüzden reddedilir — ve
     * reddedilme sebebi kullanıcıya ÖNCEDEN söylenir.
     */
    public function acceptsDimensions(int $width, int $height): bool
    {
        return $width >= $this->minWidth && $height >= $this->minHeight;
    }

    public function acceptsFormat(string $format): bool
    {
        return in_array(strtolower($format), $this->formats, true);
    }

    /**
     * Oran toleransı.
     *
     * Kesin eşitlik aranmaz: 1000×1001 bir kullanıcı için 1:1'dir ve onu
     * reddetmek, kırpma aracı zaten varken anlamsız bir engel olurdu.
     * %2 sapma kabul edilir.
     */
    public function acceptsAspect(int $width, int $height): bool
    {
        if ($this->aspect === null || $height === 0) {
            return true;
        }

        [$targetW, $targetH] = array_map('floatval', explode(':', $this->aspect));

        if ($targetH === 0.0) {
            throw new InvalidArgumentException("Bozuk oran: {$this->aspect}");
        }

        $target = $targetW / $targetH;
        $actual = $width / $height;

        return abs($actual - $target) <= $target * 0.02;
    }

    /** En büyük rendition — alt sınırın gerekçesi budur. */
    public function largestRendition(): int
    {
        return $this->renditions === [] ? 0 : max($this->renditions);
    }
}
