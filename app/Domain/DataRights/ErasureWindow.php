<?php

declare(strict_types=1);

namespace App\Domain\DataRights;

use DateTimeImmutable;

/**
 * Silmenin geri alınabilir penceresi — FF-226 (`docs/138` §4).
 *
 * SÜRE YAPILANDIRMADAN GELİR ama OKUNAMAYAN bir değer pencereyi kapatmaz:
 * bir yazım hatası ("otuz", boş dize, sıfır) silmeyi anında yürütecek
 * olsaydı, bir yapılandırma hatası doğrudan veri kaybına dönüşürdü. Alt
 * sınır bir gündür ve bu sınıfın var olma sebebi tam olarak odur —
 * "gecikmeli ve geri alınabilir" sözü bir `.env` satırıyla kaldırılamaz.
 *
 * ÜST SINIR da var: aylarca sürüncemede kalan bir silme talebi, sahibin
 * unuttuğu ve bir gün kendiliğinden patlayan bir mayındır.
 */
final readonly class ErasureWindow
{
    public const MINIMUM_DAYS = 1;

    public const MAXIMUM_DAYS = 180;

    public const FALLBACK_DAYS = 30;

    private function __construct(public int $days) {}

    public static function fromConfig(mixed $configured): self
    {
        $days = is_int($configured) ? $configured : (is_numeric($configured) ? (int) $configured : self::FALLBACK_DAYS);

        if ($days < self::MINIMUM_DAYS) {
            $days = self::FALLBACK_DAYS;
        }

        if ($days > self::MAXIMUM_DAYS) {
            $days = self::MAXIMUM_DAYS;
        }

        return new self($days);
    }

    public function endsAt(DateTimeImmutable $from): DateTimeImmutable
    {
        return $from->modify('+'.$this->days.' days');
    }
}
