<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * Tek bir dönüştürme hedefi — kaynağın kartlarından biri.
 *
 * `claimedSavingPercent` bir ÖLÇÜM DEĞİLDİR ve adı bunu söylemek zorunda:
 * kaynak "AVIF ~%74 küçük" derken biçimin GENEL iddiasını yazıyor, bu
 * kiracının dosyalarının tartısını değil. Ölçülmüş bayt ayrı bir yerden
 * (`MediaConversionPort::measuredByFormat`) gelir ve ikisi asla aynı alanda
 * buluşmaz — buluşurlarsa ekranda hangisinin gerçek olduğu okunmaz olur.
 */
final readonly class ConversionTarget
{
    /** @param 'image'|'video' $family */
    public function __construct(
        public string $format,
        public string $family,
        public int $claimedSavingPercent,
    ) {}

    public function isVideo(): bool
    {
        return $this->family === 'video';
    }
}
